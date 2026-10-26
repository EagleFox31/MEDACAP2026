<?php
require_once __DIR__ . '/../core/medacap_config.php';
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

    $conn = new MongoDB\Client(medacapMongoUri());
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

    if (!function_exists('profileUserFieldList')) {
        function profileUserFieldList($userDoc, $field)
        {
            $val = null;
            if (is_object($userDoc)) {
                if (isset($userDoc->$field)) {
                    $val = $userDoc->$field;
                } elseif ($userDoc instanceof ArrayAccess && isset($userDoc[$field])) {
                    $val = $userDoc[$field];
                }
            } elseif (is_array($userDoc) && array_key_exists($field, $userDoc)) {
                $val = $userDoc[$field];
            }
            if ($val === null || $val === '') {
                return array();
            }
            if ($val instanceof MongoDB\Model\BSONArray) {
                return $val->getArrayCopy();
            }
            if (is_array($val)) {
                return array_values($val);
            }
            return array((string) $val);
        }
    }

    if (!function_exists('profileBrandLogoMap')) {
        function profileBrandLogoMap()
        {
            return array(
                'RENAULT TRUCK' => 'renaultTrucks.png',
                'HINO' => 'hino_logo.png',
                'TOYOTA BT' => 'bt.png',
                'SINOTRUK' => 'sinotruk.png',
                'JCB' => 'jcb.png',
                'MERCEDES TRUCK' => 'mercedestruck.png',
                'TOYOTA FORKLIFT' => 'forklift.png',
                'FUSO' => 'fuso.png',
                'LOVOL' => 'lovol.png',
                'KING LONG' => 'kl2.png',
                'MERCEDES' => 'mercedestruck.png',
                'TOYOTA' => 'toyota-logo.png',
                'SUZUKI' => 'suzuki-logo.png',
                'PEUGEOT' => 'peugeot-logo.png',
                'MITSUBISHI' => 'mitsubishi-logo.png',
                'FORD' => 'ford-logo.png',
            );
        }

        function profileBrandLogoSrc($brand)
        {
            $brand = trim((string) $brand);
            if ($brand === '') {
                return '';
            }

            $map = profileBrandLogoMap();
            $upper = strtoupper($brand);
            if (isset($map[$upper])) {
                return '../../public/images/' . $map[$upper];
            }
            if (isset($map[$brand])) {
                return '../../public/images/' . $map[$brand];
            }
            foreach ($map as $name => $file) {
                if (strcasecmp($name, $brand) === 0) {
                    return '../../public/images/' . $file;
                }
            }

            return '';
        }
    }

    if (!function_exists('profileCountryFlagFile')) {
        function profileCountryFlagFile($country)
        {
            $country = trim((string) $country);
            if ($country === '' || $country === '—') {
                return '';
            }

            $map = array(
                'Burkina Faso' => 'bf.svg',
                'Cameroun' => 'cm.svg',
                'Cameroon' => 'cm.svg',
                "Cote d'Ivoire" => 'ci.svg',
                "Côte d'Ivoire" => 'ci.svg',
                'Gabon' => 'ga.svg',
                'Mali' => 'ml.svg',
                'RCA' => 'cf.svg',
                'Central African Republic' => 'cf.svg',
                'RDC' => 'cd.svg',
                'Congo, the Democratic Republic of the' => 'cd.svg',
                'Democratic Republic of the Congo' => 'cd.svg',
                'Senegal' => 'sn.svg',
                'France' => 'fr.svg',
                'Germany' => 'de.svg',
                'United Kingdom' => 'gb.svg',
                'United States' => 'us.svg',
                'Belgium' => 'be.svg',
                'Spain' => 'es.svg',
                'Italy' => 'it.svg',
                'Portugal' => 'pt.svg',
                'Netherlands' => 'nl.svg',
                'Switzerland' => 'ch.svg',
                'China' => 'cn.svg',
                'Japan' => 'jp.svg',
                'India' => 'in.svg',
                'Morocco' => 'ma.svg',
                'Algeria' => 'dz.svg',
                'Tunisia' => 'tn.svg',
                'Egypt' => 'eg.svg',
                'South Africa' => 'za.svg',
                'Nigeria' => 'ng.svg',
                'Ghana' => 'gh.svg',
                'Kenya' => 'ke.svg',
                'Madagascar' => 'mg.svg',
                'Congo' => 'cg.svg',
                'Angola' => 'ao.svg',
                'Benin' => 'bj.svg',
                'Togo' => 'tg.svg',
                'Niger' => 'ne.svg',
                'Chad' => 'td.svg',
                'Guinea' => 'gn.svg',
                'Rwanda' => 'rw.svg',
                'Burundi' => 'bi.svg',
                'Uganda' => 'ug.svg',
                'Tanzania, United Republic of' => 'tz.svg',
                'Ethiopia' => 'et.svg',
                'Canada' => 'ca.svg',
                'Brazil' => 'br.svg',
                'Mexico' => 'mx.svg',
                'Australia' => 'au.svg',
                'Russia' => 'ru.svg',
                'Russian Federation' => 'ru.svg',
            );

            if (isset($map[$country])) {
                return $map[$country];
            }

            foreach ($map as $name => $file) {
                if (strcasecmp($name, $country) === 0) {
                    return $file;
                }
            }

            return 'xx.svg';
        }

        function profileRenderCountryFlag($country, $extraClass = '')
        {
            $country = trim((string) $country);
            $flagFile = profileCountryFlagFile($country);
            if ($flagFile === '') {
                return '';
            }

            $src = '../define/flags/1x1/' . $flagFile;
            $classes = 'profile-country-flag';
            if ($extraClass !== '') {
                $classes .= ' ' . $extraClass;
            }

            return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
                . ' alt="" class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"'
                . ' title="' . htmlspecialchars($country, ENT_QUOTES, 'UTF-8') . '"'
                . ' width="22" height="22"'
                . ' onerror="this.style.display=\'none\'">';
        }

        function profileBuildBrandMaxLevelList($brandJunior, $brandSenior, $brandExpert)
        {
            $brands = array();
            $levels = array(
                array('Junior', 1, $brandJunior),
                array('Senior', 2, $brandSenior),
                array('Expert', 3, $brandExpert),
            );

            foreach ($levels as $levelMeta) {
                $levelName = $levelMeta[0];
                $levelOrder = $levelMeta[1];
                $items = $levelMeta[2];
                if (!is_array($items)) {
                    continue;
                }
                foreach ($items as $brandName) {
                    $brandName = trim((string) $brandName);
                    if ($brandName === '') {
                        continue;
                    }
                    $key = strtoupper($brandName);
                    if (!isset($brands[$key]) || $brands[$key]['order'] < $levelOrder) {
                        $brands[$key] = array(
                            'name' => $brandName,
                            'level' => $levelName,
                            'order' => $levelOrder,
                        );
                    }
                }
            }

            $list = array_values($brands);
            usort($list, function ($a, $b) {
                if ($a['order'] === $b['order']) {
                    return strcasecmp($a['name'], $b['name']);
                }
                return $b['order'] - $a['order'];
            });

            return $list;
        }
    }

    $profileUserLevel = (isset($user->level) && $user->level !== '') ? (string) $user->level : '';
    $profileBrandJunior = profileUserFieldList($user, 'brandJunior');
    $profileBrandSenior = profileUserFieldList($user, 'brandSenior');
    $profileBrandExpert = profileUserFieldList($user, 'brandExpert');
    $profileSpecSenior = profileUserFieldList($user, 'specialitySenior');
    $profileSpecExpert = profileUserFieldList($user, 'specialityExpert');
    $profileShowBrands = in_array($_SESSION['profile'], array('Technicien', 'Manager'))
        && ($_SESSION['profile'] !== 'Manager' || !empty($_SESSION['test']));
    $profileHasBrands = !empty($profileBrandJunior) || !empty($profileBrandSenior) || !empty($profileBrandExpert);
    $profileHasSpecs = !empty($profileSpecSenior) || !empty($profileSpecExpert);
    $profileShowSkillsPanel = $profileHasSpecs || $profileHasBrands || $profileShowBrands;
    $profileBrandMaxList = profileBuildBrandMaxLevelList($profileBrandJunior, $profileBrandSenior, $profileBrandExpert);
    $profileBrandLevelLabels = array(
        'Junior' => array('label' => $junior, 'class' => 'junior'),
        'Senior' => array('label' => $senior, 'class' => 'senior'),
        'Expert' => array('label' => $expert, 'class' => 'expert'),
    );

?>
    <?php include_once "./partials/header.php"; ?>
    <!--begin::Title-->
    <title><?php echo $my_info ?> | CFAO Mobility Academy</title>
    <!--end::Title-->

    <link rel="stylesheet" href="../../public/css/core/variables.css">
    <link rel="stylesheet" href="../../public/css/core/utilities.css">
    <link rel="stylesheet" href="../../public/css/modules/skeleton-loader.css">
    <link rel="stylesheet" href="../../public/css/modules/training-plan.css">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">

    <style>
        .profile-page-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0 0 2rem;
        }
        .profile-content-card {
            background: rgba(255, 255, 255, 0.7) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1) !important;
            overflow: hidden;
        }
        .profile-content-card:hover {
            transform: none !important;
            background: rgba(255, 255, 255, 0.7) !important;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1) !important;
        }
        .profile-content-card .card-body {
            padding: 0;
        }
        #kt_content {
            position: relative;
            border-radius: 25px;
            overflow: hidden;
        }
        #kt_content > .alert {
            position: relative;
            z-index: 1;
        }
        .profile-surface {
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            overflow: hidden;
            font-family: 'Roboto', system-ui, sans-serif;
        }
        .profile-hero {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1.25rem 1.5rem;
            align-items: center;
            padding: 1.5rem 1.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.28);
        }
        .profile-avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 1px 3px rgba(60, 64, 67, 0.25);
        }
        .profile-hero-name {
            margin: 0 0 0.4rem;
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1.2;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.55rem;
        }
        .profile-country-flag {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.18);
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.65);
        }
        .profile-hero-flag {
            width: 26px;
            height: 26px;
        }
        .md-value-with-flag {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }
        .profile-hero-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem 0.85rem;
            color: #3c4043;
            font-size: 1rem;
        }
        .profile-badge-role {
            background: #e6f4ea;
            color: #137333;
        }
        .profile-badge-level {
            font-weight: 700;
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            border: 1px solid transparent;
        }
        .profile-badge-level.level-junior {
            background: linear-gradient(135deg, #f8ead8 0%, #d4a574 100%);
            color: #4a3010;
            border-color: rgba(180, 120, 60, 0.45);
        }
        .profile-badge-level.level-senior {
            background: linear-gradient(135deg, #f5f5f5 0%, #c8c8c8 100%);
            color: #333;
            border-color: rgba(130, 130, 130, 0.45);
        }
        .profile-badge-level.level-expert {
            background: linear-gradient(135deg, #fffbeb 0%, #f0cc3d 100%);
            color: #5c4800;
            border-color: rgba(190, 150, 0, 0.45);
        }
        .profile-edit-btn {
            border-radius: 999px;
            padding: 0.5rem 1.15rem;
            font-weight: 500;
            box-shadow: none;
        }
        .profile-body {
            display: grid;
            grid-template-columns: minmax(200px, 0.82fr) minmax(220px, 1fr) minmax(340px, 1.75fr);
            gap: 0;
        }
        .panel-identity .profile-fields {
            grid-template-columns: 1fr;
        }
        .profile-panel {
            padding: 1.5rem 1.65rem;
            border-right: 1px solid rgba(255, 255, 255, 0.55);
            min-width: 0;
        }
        .profile-panel.panel-identity {
            background: rgba(52, 168, 83, 0.06);
        }
        .profile-panel.panel-pro {
            background: rgba(26, 115, 232, 0.05);
        }
        .profile-panel.panel-skills {
            background: rgba(234, 67, 53, 0.05);
        }
        .profile-panel.panel-identity .profile-panel-title { color: #137333; border-left-color: #34a853; }
        .profile-panel.panel-pro .profile-panel-title { color: #1967d2; border-left-color: #1a73e8; }
        .profile-panel.panel-skills .profile-panel-title { color: #c5221f; border-left-color: #ea4335; }
        .panel-identity .md-label { color: #188038; }
        .panel-pro .md-label { color: #1967d2; }
        .panel-skills .profile-skill-block h6 { color: #3c4043; }
        .profile-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .profile-panel:last-child {
            border-right: none;
        }
        .profile-panel-title {
            margin: 0 0 1.15rem;
            padding-left: 0.75rem;
            border-left: 4px solid #5f6368;
            font-size: 0.9375rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #3c4043;
        }
        .profile-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.15rem;
        }
        .profile-field-full {
            grid-column: 1 / -1;
        }
        .md-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #5f6368;
            margin-bottom: 0.25rem;
        }
        .md-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a2e;
            word-break: break-word;
            line-height: 1.35;
        }
        .profile-skills-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.65rem;
        }
        .profile-brands-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .profile-brands-list .md-chip-brand {
            width: auto;
            min-width: 13rem;
            flex: 1 1 calc(50% - 0.75rem);
            padding: 0.7rem 0.85rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
            border-left-width: 4px;
        }
        .profile-brands-list .md-chip-brand-logo {
            width: 44px;
            height: 32px;
        }
        .profile-brands-list .md-chip-brand-label {
            font-size: 1rem;
            line-height: 1.2;
        }
        .profile-brand-level-tag {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-left: auto;
            white-space: nowrap;
        }
        .profile-brand-level-tag.junior { background: #f8ead8; color: #6b4518; }
        .profile-brand-level-tag.senior { background: #ececec; color: #444; }
        .profile-brand-level-tag.expert { background: #fff6cc; color: #6b5600; }
        .profile-skill-block {
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(0, 0, 0, 0.07);
            border-radius: 12px;
            padding: 0.85rem 0.9rem;
            min-height: 100%;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }
        .profile-skill-block.block-junior { border-top: 3px solid #cd7f32; }
        .profile-skill-block.block-senior { border-top: 3px solid #9e9e9e; }
        .profile-skill-block.block-expert { border-top: 3px solid #e6b800; }
        .profile-skill-block h6 {
            margin: 0 0 0.85rem;
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #5f6368;
            line-height: 1.3;
        }
        .profile-skill-block .md-chips {
            flex-direction: column;
            align-items: stretch;
            gap: 0.45rem;
        }
        .level-metal-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-right: 0.35rem;
            vertical-align: middle;
        }
        .level-metal-dot.junior { background: #cd7f32; }
        .level-metal-dot.senior { background: #9e9e9e; }
        .level-metal-dot.expert { background: #e6b800; }
        .profile-empty-chip {
            font-size: 1rem;
            color: #80868b;
            font-style: italic;
        }
        .md-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .md-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            font-size: 0.9375rem;
            font-weight: 600;
            background: rgba(232, 240, 254, 0.92);
            color: #1967d2;
            line-height: 1.2;
            border: 1px solid rgba(255, 255, 255, 0.55);
        }
        .md-chip.spec {
            background: #fff;
            color: #3c4043;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 8px;
        }
        .md-chip.spec.level-senior { border-left: 3px solid #9e9e9e; }
        .md-chip.spec.level-expert { border-left: 3px solid #e6b800; }
        .md-chip-brand {
            gap: 0.55rem;
            padding: 0.5rem 0.65rem;
            width: 100%;
            justify-content: flex-start;
            align-items: center;
            background: #fff;
            color: #202124;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            font-size: 0.8125rem;
            font-weight: 500;
            box-shadow: none;
        }
        .md-chip-brand.junior { border-left: 3px solid #cd7f32; }
        .md-chip-brand.senior { border-left: 3px solid #9e9e9e; }
        .md-chip-brand.expert { border-left: 3px solid #e6b800; }
        .md-chip-brand-logo {
            width: 36px;
            height: 26px;
            object-fit: contain;
            flex-shrink: 0;
            filter: none;
            background: transparent;
        }
        .md-chip-brand-label {
            line-height: 1.15;
        }
        .profile-body-two {
            grid-template-columns: 1fr 1fr;
        }
        @media (max-width: 1100px) {
            .profile-body { grid-template-columns: 1fr 1fr; }
            .profile-panel.skills-panel { grid-column: 1 / -1; border-right: none; border-top: 1px solid rgba(255, 255, 255, 0.55); }
        }
        @media (max-width: 768px) {
            .profile-hero { grid-template-columns: 1fr; text-align: center; justify-items: center; }
            .profile-body { grid-template-columns: 1fr; }
            .profile-panel { border-right: none; border-bottom: 1px solid #e8eaed; }
            .profile-skills-grid { grid-template-columns: 1fr; }
            .profile-fields { grid-template-columns: 1fr; }
        }
    </style>


    <div class="content fs-6 d-flex flex-column flex-column-fluid p-6 p-lg-8" id="kt_content">
        <div class="background-overlay" aria-hidden="true"></div>
        <?php if (isset($success_msg)) : ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <strong><?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_msg)) : ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <strong><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="profile-page-wrap">
            <?php
            $profileTitleSectionPath = __DIR__ . '/measure/components/ui/title-section.php';
            if (is_file($profileTitleSectionPath)) {
                require_once $profileTitleSectionPath;
                renderTitleSection('ki-duotone ki-profile-user fs-2 me-3', 2, $my_info);
            } else {
                ?>
                <div class="row mb-5">
                    <div class="col-12">
                        <div class="card glass-effect depth-effect">
                            <div class="card-body text-center py-3">
                                <h1 class="text-contrast mb-0">
                                    <i class="ki-duotone ki-profile-user fs-2 me-3"><span class="path1"></span><span class="path2"></span></i><?= htmlspecialchars($my_info, ENT_QUOTES, 'UTF-8') ?>
                                </h1>
        </div>
                        </div>
                    </div>
                            </div>
                <?php
            }
            ?>

            <div class="card glass-effect depth-effect profile-content-card mb-5">
                <div class="card-body">
            <div class="profile-surface">
                <div class="profile-hero">
                    <img class="profile-avatar" src="../../public/assets/media/avatars/300-1.png" alt="">
                    <div>
                        <h2 class="profile-hero-name">
                            <span><?= htmlspecialchars($user->firstName . ' ' . $user->lastName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($user->country)) : ?>
                                <?= profileRenderCountryFlag($user->country, 'profile-hero-flag') ?>
                            <?php endif; ?>
                        </h2>
                        <div class="profile-hero-meta">
                            <?php if ($profileUserLevel !== '') : ?>
                                <?php
                                    $levelBadgeClass = 'profile-badge profile-badge-level';
                                    if ($profileUserLevel === 'Senior') {
                                        $levelBadgeClass .= ' level-senior';
                                    } elseif ($profileUserLevel === 'Expert') {
                                        $levelBadgeClass .= ' level-expert';
                                    } else {
                                        $levelBadgeClass .= ' level-junior';
                                    }
                                ?>
                                <span class="<?= $levelBadgeClass ?>"><?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($profileUserLevel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <span class="profile-badge profile-badge-role"><?= htmlspecialchars($user->profile, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($user->email)) : ?>
                                <span><i class="ki-duotone ki-sms fs-6 me-1"><span class="path1"></span><span class="path2"></span></i><?= htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($user->agency)) : ?>
                                <span><?= htmlspecialchars($user->agency, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($_SESSION["profile"] != "Super Admin") : ?>
                        <button class="btn btn-primary profile-edit-btn" data-bs-toggle="modal" data-bs-target="#kt_modal_update_details">
                            <i class="ki-duotone ki-pencil fs-5 me-1"><span class="path1"></span><span class="path2"></span></i><?= $edit ?>
                        </button>
                    <?php endif; ?>
        </div>

                <div class="profile-body<?php echo $profileShowSkillsPanel ? '' : ' profile-body-two'; ?>">
                    <div class="profile-panel panel-identity">
                        <h3 class="profile-panel-title"><?= $lang_lower === 'en' ? 'Identity' : 'Identité' ?></h3>
                        <div class="profile-fields">
                            <div><span class="md-label"><?= $username ?></span><div class="md-value"><?= htmlspecialchars($user->username ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div><span class="md-label"><?= $matricule ?></span><div class="md-value"><?= htmlspecialchars($user->matricule ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div><span class="md-label"><?= $gender ?></span><div class="md-value"><?= htmlspecialchars($user->gender ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div><span class="md-label"><?= $birthdate ?></span><div class="md-value"><?= htmlspecialchars($user->birthdate ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div class="profile-field-full"><span class="md-label"><?= $phoneNumber ?></span><div class="md-value"><?= htmlspecialchars($user->phone ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div class="profile-field-full"><span class="md-label"><?= $pays ?></span><div class="md-value md-value-with-flag"><?php if (!empty($user->country)) : ?><?= profileRenderCountryFlag($user->country) ?><?php endif; ?><span><?= htmlspecialchars($user->country ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div></div>
                                </div>
                            </div>

                    <div class="profile-panel panel-pro">
                        <h3 class="profile-panel-title"><?= $lang_lower === 'en' ? 'Professional' : 'Professionnel' ?></h3>
                        <div class="profile-fields">
                            <div><span class="md-label"><?= $role ?></span><div class="md-value"><?= htmlspecialchars($user->role ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div><span class="md-label"><?= $department ?></span><div class="md-value"><?= htmlspecialchars($user->department ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div><span class="md-label"><?= $certificat ?></span><div class="md-value"><?= htmlspecialchars($user->certificate ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div><span class="md-label"><?= $filiale ?></span><div class="md-value"><?= htmlspecialchars($user->subsidiary ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            <div class="profile-field-full"><span class="md-label"><?= $recrutmentDate ?></span><div class="md-value"><?= htmlspecialchars($user->recrutmentDate ?? '—', ENT_QUOTES, 'UTF-8') ?></div></div>
                            </div>
                        </div>

                    <?php if ($profileShowSkillsPanel) : ?>
                    <div class="profile-panel panel-skills skills-panel">
                        <h3 class="profile-panel-title"><?= $lang_lower === 'en' ? 'Skills & brands' : 'Spécialités & marques' ?></h3>
                        <?php if ($profileHasSpecs) : ?>
                            <div class="profile-skill-block mb-3">
                                <h6><?= $speciality ?></h6>
                                <div class="md-chips">
                                    <?php foreach ($profileSpecSenior as $specialitySenior) : ?>
                                        <span class="md-chip spec level-senior"><?= htmlspecialchars($specialitySenior, ENT_QUOTES, 'UTF-8') ?> · <?= $senior ?></span>
                                    <?php endforeach; ?>
                                    <?php foreach ($profileSpecExpert as $specialityExpert) : ?>
                                        <span class="md-chip spec level-expert"><?= htmlspecialchars($specialityExpert, ENT_QUOTES, 'UTF-8') ?> · <?= $expert ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                        <?php if ($profileShowBrands) : ?>
                            <div class="profile-skill-block">
                                <h6><?= $brand ?></h6>
                                <div class="profile-brands-list">
                                    <?php if (!empty($profileBrandMaxList)) : ?>
                                        <?php foreach ($profileBrandMaxList as $brandRow) : ?>
                                            <?php
                                                $brandLevelMeta = isset($profileBrandLevelLabels[$brandRow['level']])
                                                    ? $profileBrandLevelLabels[$brandRow['level']]
                                                    : array('label' => $brandRow['level'], 'class' => 'junior');
                                                $brandLogoSrc = profileBrandLogoSrc($brandRow['name']);
                                            ?>
                                            <span class="md-chip md-chip-brand <?= $brandLevelMeta['class'] ?>">
                                                <?php if ($brandLogoSrc !== '') : ?>
                                                    <img src="<?= htmlspecialchars($brandLogoSrc, ENT_QUOTES, 'UTF-8') ?>"
                                                         alt=""
                                                         class="md-chip-brand-logo"
                                                         title="<?= htmlspecialchars($brandRow['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                         onerror="this.style.display='none'">
                                                <?php endif; ?>
                                                <span class="md-chip-brand-label"><?= htmlspecialchars($brandRow['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="profile-brand-level-tag <?= $brandLevelMeta['class'] ?>"><?= $brandLevelMeta['label'] ?></span>
                                            </span>
                                                    <?php endforeach; ?>
                                    <?php else : ?>
                                        <span class="profile-empty-chip">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                    </div>
                                </div>
                        </div>
                </div>
            </div>

            <!-- Modal de modification -->
            <div class="modal fade" id="kt_modal_update_details" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 8px 28px rgba(60,64,67,.25);">
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
                                            <input type="text" class="form-control form-control-solid" name="matricule" value="<?= $user->matricule ?? '' ?>" />
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
                                            <input type="email" class="form-control form-control-solid" name="email" value="<?= $user->email ?? '' ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $phoneNumber ?></label>
                                            <input type="text" class="form-control form-control-solid" name="phone" value="<?= $user->phone ?? '' ?>" />
                                        </div>
                                    </div>

                                    <!-- Colonne droite -->
                                    <div class="col-md-6">
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $birthdate ?></label>
                                            <input type="text" class="form-control form-control-solid" name="birthdate" value="<?= $user->birthdate ?? '' ?>" />
                                        </div>
                                        <div class="fv-row mb-5">
                                            <label class="fs-6 fw-bold mb-2"><?= $certificat ?></label>
                                            <input type="text" class="form-control form-control-solid" name="certificate" value="<?= $user->certificate ?? '' ?>" />
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

    <?php include_once "partials/footer.php"; ?>
<?php } ?>
