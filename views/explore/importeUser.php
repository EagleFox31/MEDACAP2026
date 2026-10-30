<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();
include_once "../language.php";

$correctBrands = ['BYD', 'CITROEN', 'FUSO', 'HINO', 'JCB', 'KING LONG', 'LOVOL', 'MERCEDES', 'MERCEDES TRUCK', 'MITSUBISHI', 'PEUGEOT', 'RENAULT', 'RENAULT TRUCK', 'SINOTRUK', 'SUZUKI', 'TOYOTA', 'TOYOTA BT', 'TOYOTA FORKLIFT', 'VOLKSWAGEN', 'YAMAHA BATEAU', 'YAMAHA MOTO'];

/** false = pas de mail à l'import (RH transmet les identifiants). Remettre true après. */
const IMPORT_SEND_REGISTRATION_MAIL = false;

function importSendRegistrationMail(string $email, string $subject, string $message): bool
{
    if (!IMPORT_SEND_REGISTRATION_MAIL || trim($email) === '') {
        return false;
    }
    return sendMailRegisterUser($email, $subject, $message);
}

function importeUserBuildRecapRow(
    int $line,
    string $action,
    string $firstName,
    string $lastName,
    string $username,
    string $visiblePassword,
    string $usernameManager,
    $managerDoc,
    string $level,
    string $profile
): array {
    $managerOk = is_object($managerDoc) || (is_array($managerDoc) && !empty($managerDoc));
    return [
        'line' => $line,
        'action' => $action,
        'name' => trim($firstName . ' ' . $lastName),
        'username' => $username,
        'password' => $visiblePassword,
        'manager' => trim($usernameManager) !== '' ? trim($usernameManager) : '—',
        'manager_ok' => $managerOk,
        'level' => $level,
        'profile' => $profile,
    ];
}

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";
    include_once "../sendMail.php";
    $importRecap = [];

    if (isset($_POST["submit"])) {
        // Create connection
        $conn = new MongoDB\Client(medacapMongoUri());

        // Connecting in database
        $academy = $conn->academy;

        // Connecting in collections
        $users = $academy->users;
        $vehicles = $academy->vehicles;
        $tests = $academy->tests;
        $allocations = $academy->allocations;

        $filePath = $_FILES["excel"]["tmp_name"];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $data = $spreadsheet->getActiveSheet()->toArray();

        // remove header
        unset($data[0]);

        //retirer les espaces inutiles
        function filterBrandValues($string, array $correctBrands) {
            // Nettoyage + découpage
            $values = array_map(function ($value) {
                return strtoupper(trim($value));
            }, explode(",", $string ?: ''));

            // Filtrer les valeurs vides après nettoyage
            $values = array_filter($values, function ($v) {
                return $v !== '';
            });

            // Vérification des valeurs invalides
            $invalidValues = array_diff($values, $correctBrands);

            // Si toutes les valeurs sont valides
            if (empty($invalidValues)) {
                return $values;
            }

            // Sinon on retourne false
            return false;
        }

        function filterSpecialityValues($string) {
            //Nettoyage + découpage
            $values = array_map(function ($value) {
                return trim($value);
            }, explode(",", $string ?: ''));

            // Filtrer les valeurs vides après nettoyage
            $values = array_filter($values, function ($v) {
                return $v !== '';
            });

            // Sinon on retourne false
            return $values;
        }


        function filterFieldValue($db, $value, $collectionName, $field = 'name', $validValues = null) {
            // Si on ne fournit pas de valeurs valides, les récupérer depuis MongoDB
            if (is_null($validValues)) {
                $collection = $db->$collectionName;
                $distinctValues = $collection->distinct($field);

                // Nettoyage des valeurs nulles/vides
                $validValues = array_filter($distinctValues, function($val) {
                    return !is_null($val) && $val !== '';
                });
            }

            // Si $value est un tableau, on vérifie que tous les éléments sont valides
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (!in_array($item, $validValues)) {
                        return false; // Dès qu’un élément est invalide, on retourne false
                    }
                }
                return true; // Toutes les valeurs sont valides
            }

            // Si $value est une valeur unique
            return in_array($value, $validValues);
        }

        function formatErrorMessages(array $file_error_msg): string {
            $messages = [];
            foreach ($file_error_msg as $row => $cols) {
                if ($cols){
                    // Trier et formater les colonnes en string séparées par des virgules
                    sort($cols);
                    $colsList = implode(', ', $cols);
                    $messages[] = "Erreur à la ligne $row sur la ou les colonnes : $colsList.";
                }
            }
            return implode("\n", $messages);
        }

        $file_error_msg = null;
        $importRecap = [];

        foreach ($data as $index => $row) {
            // Supprime les cellules vides ou contenant seulement des espaces
            $filtered = array_filter($row, function($cell) {
                return trim($cell) !== '';
            });

            if (empty($filtered)) {
                // ligne vide, on passe à la suivante
                continue;
            }

            $file_error_msg[$index] = [];
            $brandJunior = [];
            $brandSenior = [];
            $brandExpert = [];
            
            $specialitySenior = [];
            $specialityExpert = [];

            $username = $row["0"];
            $matricule = $row["1"];
            $firstName = $row["2"];
            $lastName = $row["3"];
            $email = $row["4"];
            $phone = $row["5"];
            $gender = $row["6"];
            $birthdate = date($row["7"]);

            $level = in_array(trim($row["8"]), ['', 'Expert', 'Junior', 'Senior' ])
                ? trim($row["8"])
                : $file_error_msg[$index] []= 8;

            $country = $row["9"];

            $profileValue = trim($row["10"]);
            if (filterFieldValue($academy, $profileValue, 'profiles')) {
                $profile = $profileValue;
            } else {
                $file_error_msg[$index][] = 10;
                $profile = null;
            }

            $certificate = $row["11"];
            $subsidiary = $row["12"];
            $agency = $row["13"];

            $departmentValue = trim($row["14"]);
            if (in_array($departmentValue, ['', 'Equipment', 'Equipment & Motors', 'Motors'])) {
                $department = $departmentValue;
            } else {
                $file_error_msg[$index][] = 14;
                $department = null;
            }


            $role = $row["15"];
            $recrutmentDate = date($row["16"]);
            $usernameManager = $row["17"];
            $password = sha1($row["18"]);

            $brandJunior = filterBrandValues($row["19"] ?? '', $correctBrands);
            if ($brandJunior === false) {
                $file_error_msg[$index][] = 19;
                $brandJunior = [];
            }

            $brandSenior = filterBrandValues($row["20"] ?? '', $correctBrands);
            if ($brandSenior === false) {
                $file_error_msg[$index][] = 20;
                $brandSenior = [];
            }

            $brandExpert = filterBrandValues($row["21"] ?? '', $correctBrands);
            if ($brandExpert === false) {
                $file_error_msg[$index][] = 21;
                $brandExpert = [];
            }

            $specialitySenior = filterSpecialityValues($row["23"] ?? '');
            $specialityExpert = filterSpecialityValues($row["24"] ?? '');

            if (!empty($file_error_msg[$index])) {
                $error_msg = ($error_msg ?? '') . "\n" . formatErrorMessages($file_error_msg);
                unset($file_error_msg[$index]);
                continue;
            }

            $member = $users->findOne([
                '$and' => [["username" => $username], ["active" => true]],
            ]);
            if (isset($usernameManager)) {
                $manager = $users->findOne([
                    '$and' => [
                        [
                            "username" => $usernameManager,
                            "active" => true,
                        ],
                    ],
                ]);
            }
            if ($member) {
                $person = [
                    "matricule" => $matricule,
                    "firstName" => ucfirst($firstName),
                    "lastName" => strtoupper($lastName),
                    "email" => $email,
                    "phone" => $phone,
                    "gender" => $gender,
                    "level" => $level,
                    "country" => $country,
                    "profile" => $profile,
                    "birthdate" => $birthdate,
                    "recrutmentDate" => $recrutmentDate,
                    "certificate" => ucfirst($certificate),
                    "subsidiary" => strtoupper($subsidiary),
                    "agency" => ucfirst($agency),
                    "department" => ucfirst($department),
                    "role" => ucfirst($role),
                    "password" => $password,
                    "visiblePassword" => $row["18"],
                    "updated" => date("d-m-Y H:i:s"),
                ];
                $users->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($member->_id)],
                    [
                        '$set' => $person
                    ]
                );
                if ($profile == "Technicien") {
                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Mis à jour', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '', $manager ?? null, $level, $profile
                    );
                    $success_msg = $success_tech;
                }
                if ($profile == "Manager") {
                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Mis à jour', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '', $manager ?? null, $level, $profile
                    );
                    $success_msg = $success_manager;
                }
                if ($profile == "Admin") {
                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Mis à jour', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '', $manager ?? null, $level, $profile
                    );
                    $success_msg = $success_admin;
                }
            }
            elseif ($profile == "Technicien") {
                if (!empty($usernameManager)) {
                    
                    $userManager = $users->findone([
                        '$and' => [
                            [
                                "username" => $usernameManager,
                                "active" => true,
                            ],
                        ],
                    ]);

                    if (!$userManager) {
                        $file_error_msg[$index][] = 'manager:' . $usernameManager;
                        $error_msg = ($error_msg ?? '') . "\nLigne " . ($index + 1) . " : manager introuvable « " . $usernameManager . " ».";
                        continue;
                    }
                    $manager = $userManager;

                    $person = [
                        "users" => [],
                        "username" => $username,
                        "matricule" => $matricule,
                        "firstName" => ucfirst($firstName),
                        "lastName" => strtoupper($lastName),
                        "email" => $email,
                        "phone" => $phone,
                        "gender" => $gender,
                        "level" => $level,
                        "country" => $country,
                        "profile" => $profile,
                        "birthdate" => $birthdate,
                        "recrutmentDate" => $recrutmentDate,
                        "certificate" => ucfirst($certificate),
                        "subsidiary" => ucfirst($subsidiary),
                        "agency" => ucfirst($agency),
                        "department" => ucfirst($department),
                        "brandJunior" => $brandJunior ?? [],
                        "brandSenior" => $brandSenior ?? [],
                        "brandExpert" => $brandExpert ?? [],
                        "specialitySenior" => $specialitySenior ?? [],
                        "specialityExpert" => $specialityExpert ?? [],
                        "role" => ucfirst($role),
                        "password" => $password,
                        "visiblePassword" => $row["18"],
                        "manager" => new MongoDB\BSON\ObjectId($manager->_id),
                        "test" => true,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $user = $users->insertOne($person);

                    $users->updateOne(
                        ["_id" => new MongoDB\BSON\ObjectId($manager->_id)],
                        [
                            '$push' => [
                                "users" => new MongoDB\BSON\ObjectId(
                                    $user->getInsertedId()
                                ),
                            ],
                        ]
                    );

                    $technician = $users->findOne([
                        '$and' => [
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            ["active" => true],
                        ],
                    ]);

                
                    // Assurez-vous que les variables sont définies et valides
                    if (isset($userManager['lastName'], $technician['firstName'], $technician['lastName'])) {
                        // Échapper les données pour éviter les problèmes de sécurité
                        $managerLastName = htmlspecialchars($userManager['lastName']);
                        $technicianFirstName = htmlspecialchars($technician['firstName']);
                        $technicianLastName = htmlspecialchars($technician['lastName']);
                        
                        // Vérifier le pays pour déterminer la langue
                        if (isset($country) && $country === "Rwanda") {
                            // Message en anglais pour le Rwanda
                            $message = '<p>Hello Mr. ' . $managerLastName . ',</p>
                                        <p>Your employee <strong>' . $technicianFirstName . ' ' . $technicianLastName . '</strong>
                                        has been successfully registered on the "<strong>Technician Skills Assessment</strong>" platform. <br>
                                        You can now view the recorded information of your employees (menu: <strong>User List</strong>).</p>
                                        <p>Please validate the mastery of each professional task of your employee in the application space (menu: <strong>List of Employees to Evaluate</strong>).</p>
                                        <p style="font-size: 20px; font-weight: 100px">Best Regards | Cordialement | よろしくお願いします。</p>';
                            
                            // Sujet de l'e-mail en anglais
                            $subject = 'Registration confirmation of ' . $technicianFirstName . ' ' . $technicianLastName . ' on the "Technician Skills Assessment" platform - Technician Space';
                            
                        } else {
                            // Message en français (par défaut)
                            $message = '<p>Bonjour M.' . $managerLastName . ',</p>
                                        <p>Votre collaborateur <strong>' . $technicianFirstName . ' ' . $technicianLastName . '</strong>
                                        a été inscrit avec succès dans la plateforme de « <strong>Mesure des Compétences des Techniciens </strong> ». <br>
                                        Il vous est désormais possible de visualiser les informations enregistrées de vos collaborateurs (menu : <strong>Liste des Utilisateurs</strong>).</p>
                                        <p>Merci de valider la maîtrise de chacune des tâches professionnelles de votre collaborateur dans l\'espace de l\'application (menu : <strong>Liste des Collaborateurs à Evaluer</strong>).</p>
                                        <p style="font-size: 20px; font-weight: 100px">Cordialement | Best Regards | よろしくお願いします。</p>';
                            
                            // Sujet de l'e-mail en français
                            $subject = 'Confirmation d\'inscription de ' . $technicianFirstName . ' ' . $technicianLastName . ' sur la plateforme « Mesure des Compétences des Techniciens » Espace Technicien';
                        }
                        
                        // Envoyer l'e-mail
                        $sendMail = importSendRegistrationMail($userManager['email'], $subject, $message);
                    }

                } else {
                    $person = [
                        "users" => [],
                        "username" => $username,
                        "matricule" => $matricule,
                        "firstName" => ucfirst($firstName),
                        "lastName" => strtoupper($lastName),
                        "email" => $email,
                        "phone" => $phone,
                        "gender" => $gender,
                        "level" => $level,
                        "country" => $country,
                        "profile" => $profile,
                        "birthdate" => $birthdate,
                        "recrutmentDate" => $recrutmentDate,
                        "certificate" => ucfirst($certificate),
                        "subsidiary" => ucfirst($subsidiary),
                        "agency" => ucfirst($agency),
                        "department" => ucfirst($department),
                        "brandJunior" => $brandJunior ?? [],
                        "brandSenior" => $brandSenior ?? [],
                        "brandExpert" => $brandExpert ?? [],
                        "specialitySenior" => $specialitySenior ?? [],
                        "specialityExpert" => $specialityExpert ?? [],
                        "role" => ucfirst($role),
                        "password" => $password,
                        "visiblePassword" => $_row["18"],
                        "manager" => "",
                        "test" => true,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $user = $users->insertOne($person);
                }
                if (isset($brandExpert)) {
                    for ($i = 0; $i < count($brandExpert); $i++) {
                        $users->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            [
                                '$addToSet' => [
                                    "brandSenior" => $brandExpert[$i]
                                ],
                            ]
                        );
                        $users->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            [
                                '$addToSet' => [
                                    "brandJunior" => $brandExpert[$i]
                                ],
                            ]
                        );
                    }
                }
                if (isset($brandSenior)) {
                    for ($i = 0; $i < count($brandSenior); $i++) {
                        $users->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            [
                                '$addToSet' => [
                                    "brandJunior" => $brandSenior[$i]
                                ],
                            ]
                        );
                    }
                }
                if ($level == "Junior") {
                    $person = $users->findOne(
                        ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                    );
                    $testFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandJunior'],
                        "type" => "Factuel",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertFac = $tests->insertOne($testFac);

                    $testDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandJunior'],
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertDecla = $tests->insertOne($testDecla);

                    for ($n = 0; $n < count($person['brandJunior']); ++$n) {
                        $vehicleFacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);

                        if ($vehicleDeclacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    $saveTestFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                    ]);
                    $saveTestFac["total"] = count($saveTestFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestFac]
                    );

                    $allocateFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Junior",
                        "activeTest" => true,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateFac);

                    $saveTestDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestDecla["total"] = count($saveTestDecla["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestDecla]
                    );

                    $allocateDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "activeTest" => true,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateDecla);

                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Créé', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '',
                        isset($manager) ? $manager : (isset($userManager) ? $userManager : null),
                        $level, $profile
                    );
                    $success_msg = $success_tech;
                } elseif ($level == "Senior") {
                    $person = $users->findOne(
                        ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                    );
                    $testJuFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertFac = $tests->insertOne($testJuFac);

                    $testJuDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertDecla = $tests->insertOne($testJuDecla);

                    $testSeFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeFac = $tests->insertOne($testSeFac);

                    $testSeDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeDecla = $tests->insertOne($testSeDecla);

                    for ($n = 0; $n < count($person['brandJunior']); ++$n) {
                        $vehicleFacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['specialitySenior']); ++$n) {
                        $specialityFacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Factuelle"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($specialityFacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $specialityDeclacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Declarative"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);

                        if ($specialityDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['brandSenior']); ++$n) {
                        $vehicleFacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }

                    $saveTestFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                    ]);
                    $saveTestFac["total"] = count($saveTestFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestFac]
                    );

                    $allocateFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Junior",
                        "activeTest" => true,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateFac);

                    $saveTestDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestDecla["total"] = count($saveTestDecla["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestDecla]
                    );

                    $allocateDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "activeTest" => true,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateDecla);

                    $saveTestSeFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                    ]);
                    $saveTestSeFac["total"] = count($saveTestSeFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeFac]
                    );

                    $allocateSeFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Senior",
                        "activeTest" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeFac);

                    $saveTestSeDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestSeDecla["total"] = count(
                        $saveTestSeDecla["quizzes"]
                    );
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeDecla]
                    );

                    $allocateSeDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "activeTest" => false,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeDecla);

                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Créé', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '',
                        isset($manager) ? $manager : (isset($userManager) ? $userManager : null),
                        $level, $profile
                    );
                    $success_msg = $success_tech;
                } elseif ($level == "Expert") {
                    $person = $users->findOne(
                        ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                    );
                    $testJuFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertFac = $tests->insertOne($testJuFac);

                    $testJuDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertDecla = $tests->insertOne($testJuDecla);

                    $testSeFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeFac = $tests->insertOne($testSeFac);

                    $testSeDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeDecla = $tests->insertOne($testSeDecla);

                    $testExFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $brandExpert,
                        "type" => "Factuel",
                        "level" => "Expert",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertExFac = $tests->insertOne($testExFac);

                    $testExDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $brandExpert,
                        "type" => "Declaratif",
                        "level" => "Expert",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertExDecla = $tests->insertOne($testExDecla);

                    for ($n = 0; $n < count($person['brandJunior']); ++$n) {
                        $vehicleFacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    for ($n = 0; $n < count($person['brandSenior']); ++$n) {
                        $vehicleFacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['specialitySenior']); ++$n) {
                        $specialityFacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Factuelle"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($specialityFacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $specialityDeclacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Declarative"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);

                        if ($specialityDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['specialityExpert']); ++$n) {
                        $specialityFacEx = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialityExpert'][$n]],
                                ["type" => "Factuelle"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);
                        if ($specialityFacEx) {
                            for (
                                $a = 0;
                                $a < count($specialityFacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityFacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $specialityDeclacEx = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialityExpert'][$n]],
                                ["type" => "Declarative"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);

                        if ($specialityDeclacEx) {
                            for (
                                $a = 0;
                                $a < count($specialityDeclacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityDeclacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    for ($n = 0; $n < count($brandExpert); ++$n) {
                        $vehicleFacEx = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $brandExpert[$n]],
                                ["type" => "Factuel"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacEx) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacEx = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $brandExpert[$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacEx) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }

                    $saveTestFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                    ]);
                    $saveTestFac["total"] = count($saveTestFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestFac]
                    );

                    $allocateFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Junior",
                        "activeTest" => true,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateFac);

                    $saveTestDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestDecla["total"] = count($saveTestDecla["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestDecla]
                    );

                    $allocateDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "activeTest" => true,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateDecla);

                    $saveTestSeFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                    ]);
                    $saveTestSeFac["total"] = count($saveTestSeFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeFac]
                    );

                    $allocateSeFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Senior",
                        "activeTest" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeFac);

                    $saveTestSeDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestSeDecla["total"] = count(
                        $saveTestSeDecla["quizzes"]
                    );
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeDecla]
                    );

                    $allocateSeDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "activeTest" => false,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeDecla);

                    $saveTestExFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertExFac->getInsertedId()
                        ),
                    ]);
                    $saveTestExFac["total"] = count($saveTestExFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertExFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestExFac]
                    );

                    $allocateExFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertExFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Expert",
                        "activeTest" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateExFac);

                    $saveTestExDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertExDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestExDecla["total"] = count(
                        $saveTestExDecla["quizzes"]
                    );
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertExDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestExDecla]
                    );

                    $allocateExDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertExDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Expert",
                        "activeTest" => false,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateExDecla);

                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Créé', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '',
                        isset($manager) ? $manager : (isset($userManager) ? $userManager : null),
                        $level, $profile
                    );
                    $success_msg = $success_tech;
                }
            }
            elseif ($profile == "Manager - Technicien") {
                if (!empty($usernameManager)) {
                    
                    $userManager = $users->findone([
                        '$and' => [
                            [
                                "username" => $usernameManager,
                                "active" => true,
                            ],
                        ],
                    ]);
                    $personM = [
                        "users" => [],
                        "username" => $username,
                        "matricule" => $matricule,
                        "firstName" => ucfirst($firstName),
                        "lastName" => strtoupper($lastName),
                        "email" => $email,
                        "phone" => $phone,
                        "gender" => $gender,
                        "level" => $level,
                        "country" => $country,
                        "profile" => "Manager",
                        "birthdate" => $birthdate,
                        "recrutmentDate" => $recrutmentDate,
                        "certificate" => ucfirst($certificate),
                        "subsidiary" => ucfirst($subsidiary),
                        "agency" => ucfirst($agency),
                        "department" => ucfirst($department),
                        "brandJunior" => $brandJunior ?? [],
                        "brandSenior" => $brandSenior ?? [],
                        "brandExpert" => $brandExpert ?? [],
                        "specialitySenior" => $specialitySenior ?? [],
                        "specialityExpert" => $specialityExpert ?? [],
                        "role" => ucfirst($role),
                        "password" => $password,
                        "visiblePassword" => $row["18"],
                        "manager" => new MongoDB\BSON\ObjectId($manager->_id),
                        "test" => true,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $user = $users->insertOne($personM);
                    $users->updateOne(
                        ["_id" => new MongoDB\BSON\ObjectId($manager->_id)],
                        [
                            '$push' => [
                                "users" => new MongoDB\BSON\ObjectId(
                                    $user->getInsertedId()
                                ),
                            ],
                        ]
                    );

                    $technician = $users->findOne([
                        '$and' => [
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            ["active" => true],
                        ],
                    ]);

                
                                    // Assurez-vous que les variables sont définies et valides
                    if (isset($userManager['lastName'], $technician['firstName'], $technician['lastName'])) {
                        // Échapper les données pour éviter les problèmes de sécurité
                        $managerLastName = htmlspecialchars($userManager['lastName']);
                        $technicianFirstName = htmlspecialchars($technician['firstName']);
                        $technicianLastName = htmlspecialchars($technician['lastName']);
                        
                        // Vérifier le pays pour déterminer la langue
                        if (isset($country) && $country === "Rwanda") {
                            // Message en anglais pour le Rwanda
                            $message = '<p>Hello Mr. ' . $managerLastName . ',</p>
                                        <p>Your employee <strong>' . $technicianFirstName . ' ' . $technicianLastName . '</strong>
                                        has been successfully registered on the <strong>CFAO Mobility Academy Skills Assessment</strong> platform,
                                        you can now view if the recorded information is correct in the <strong>User List</strong> section,
                                        as well as complete their <strong>Professional Tasks Manager MCQ</strong>.</p>
                                        <p style="font-size: 20px; font-weight: 100px">Best Regards | Cordialement | よろしくお願いします。</p>';
                            
                            // Sujet de l'e-mail en anglais
                            $subject = 'Registration confirmation of ' . $technicianFirstName . ' ' . $technicianLastName . ' on the "Technician Skills Assessment" platform - Technician Space';
                            
                        } else {
                            // Message en français (par défaut)
                            $message = '<p>Bonjour M.' . $managerLastName . ',</p>
                                        <p>Votre collaborateur <strong>' . $technicianFirstName . ' ' . $technicianLastName . '</strong>
                                        a été inscrit avec succès dans la plateforme de <strong>Mesure des Compétences de CFAO Mobility Academy</strong>,
                                        il est désormais possible pour vous de visualiser si les informations enregistrées sont correctes dans l\'espace <strong>Liste des Utilisateurs</strong>,
                                        ainsi que de compléter ses <strong>QCM Tâches Professionnelles Manager</strong>.</p>
                                        <p style="font-size: 20px; font-weight: 100px">Cordialement | Best Regards | よろしくお願いします。</p>';
                            
                            // Sujet de l'e-mail en français
                            $subject = 'Confirmation d\'inscription de ' . $technicianFirstName . ' ' . $technicianLastName . ' sur la plateforme « Mesure des Compétences des Techniciens » Espace Technicien';
                        }
                        
                        // Envoyer l'e-mail
                        $sendMail = importSendRegistrationMail($userManager['email'], $subject, $message);
                    }
                } else {
                    $personM = [
                        "users" => [],
                        "username" => $username,
                        "matricule" => $matricule,
                        "firstName" => ucfirst($firstName),
                        "lastName" => strtoupper($lastName),
                        "email" => $email,
                        "phone" => $phone,
                        "gender" => $gender,
                        "level" => $level,
                        "country" => $country,
                        "profile" => "Manager",
                        "birthdate" => $birthdate,
                        "recrutmentDate" => $recrutmentDate,
                        "certificate" => ucfirst($certificate),
                        "subsidiary" => ucfirst($subsidiary),
                        "agency" => ucfirst($agency),
                        "department" => ucfirst($department),
                        "brandJunior" => $brandJunior ?? [],
                        "brandSenior" => $brandSenior ?? [],
                        "brandExpert" => $brandExpert ?? [],
                        "specialitySenior" => $specialitySenior ?? [],
                        "specialityExpert" => $specialityExpert ?? [],
                        "role" => ucfirst($role),
                        "password" => $password,
                        "visiblePassword" => $row["18"],
                        "manager" => "",
                        "test" => true,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $user = $users->insertOne($personM);
                }
                if (isset($brandExpert)) {
                    for ($i = 0; $i < count($brandExpert); $i++) {
                        $users->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            [
                                '$addToSet' => [
                                    "brandSenior" => $brandExpert[$i]
                                ],
                            ]
                        );
                        $users->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            [
                                '$addToSet' => [
                                    "brandJunior" => $brandExpert[$i]
                                ],
                            ]
                        );
                    }
                }
                if (isset($brandSenior)) {
                    for ($i = 0; $i < count($brandSenior); $i++) {
                        $users->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                            [
                                '$addToSet' => [
                                    "brandJunior" => $brandSenior[$i]
                                ],
                            ]
                        );
                    }
                }
                if ($level == "Junior") {
                    $person = $users->findOne(
                        ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                    );
                    $testFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandJunior'],
                        "type" => "Factuel",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertFac = $tests->insertOne($testFac);

                    $testDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandJunior'],
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertDecla = $tests->insertOne($testDecla);

                    for ($n = 0; $n < count($person['brandJunior']); ++$n) {
                        $vehicleFacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);

                        if ($vehicleDeclacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    $saveTestFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                    ]);
                    $saveTestFac["total"] = count($saveTestFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestFac]
                    );

                    $allocateFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Junior",
                        "activeTest" => true,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateFac);

                    $saveTestDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestDecla["total"] = count($saveTestDecla["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestDecla]
                    );

                    $allocateDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "activeTest" => true,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateDecla);

                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Créé', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '',
                        isset($manager) ? $manager : (isset($userManager) ? $userManager : null),
                        $level, $profile
                    );
                    $success_msg = $success_tech;
                } elseif ($level == "Senior") {
                    $person = $users->findOne(
                        ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                    );
                    $testJuFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertFac = $tests->insertOne($testJuFac);

                    $testJuDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertDecla = $tests->insertOne($testJuDecla);

                    $testSeFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeFac = $tests->insertOne($testSeFac);

                    $testSeDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeDecla = $tests->insertOne($testSeDecla);

                    for ($n = 0; $n < count($person['brandJunior']); ++$n) {
                        $vehicleFacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['specialitySenior']); ++$n) {
                        $specialityFacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Factuelle"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($specialityFacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $specialityDeclacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Declarative"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);

                        if ($specialityDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['brandSenior']); ++$n) {
                        $vehicleFacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }

                    $saveTestFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                    ]);
                    $saveTestFac["total"] = count($saveTestFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestFac]
                    );

                    $allocateFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Junior",
                        "activeTest" => true,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateFac);

                    $saveTestDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestDecla["total"] = count($saveTestDecla["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestDecla]
                    );

                    $allocateDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "activeTest" => true,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateDecla);

                    $saveTestSeFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                    ]);
                    $saveTestSeFac["total"] = count($saveTestSeFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeFac]
                    );

                    $allocateSeFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Senior",
                        "activeTest" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeFac);

                    $saveTestSeDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestSeDecla["total"] = count(
                        $saveTestSeDecla["quizzes"]
                    );
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeDecla]
                    );

                    $allocateSeDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "activeTest" => false,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeDecla);

                    $importRecap[] = importeUserBuildRecapRow(
                        $index + 1, 'Créé', $firstName, $lastName, $username,
                        $row['18'] ?? '', $usernameManager ?? '',
                        isset($manager) ? $manager : (isset($userManager) ? $userManager : null),
                        $level, $profile
                    );
                    $success_msg = $success_tech;
                } elseif ($level == "Expert") {
                    $person = $users->findOne(
                        ["_id" => new MongoDB\BSON\ObjectId($user->getInsertedId())],
                    );
                    $testJuFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertFac = $tests->insertOne($testJuFac);

                    $testJuDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertDecla = $tests->insertOne($testJuDecla);

                    $testSeFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Factuel",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeFac = $tests->insertOne($testSeFac);

                    $testSeDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $person['brandSenior'],
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertSeDecla = $tests->insertOne($testSeDecla);

                    $testExFac = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $brandExpert,
                        "type" => "Factuel",
                        "level" => "Expert",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertExFac = $tests->insertOne($testExFac);

                    $testExDecla = [
                        "quizzes" => [],
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "brand" => $brandExpert,
                        "type" => "Declaratif",
                        "level" => "Expert",
                        "total" => 0,
                        "active" => true,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $insertExDecla = $tests->insertOne($testExDecla);

                    for ($n = 0; $n < count($person['brandJunior']); ++$n) {
                        $vehicleFacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacJu = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandJunior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Junior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacJu) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacJu->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacJu->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    for ($n = 0; $n < count($person['brandSenior']); ++$n) {
                        $vehicleFacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Factuel"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacSe = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $person['brandSenior'][$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['specialitySenior']); ++$n) {
                        $specialityFacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Factuelle"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);
                        if ($specialityFacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityFacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityFacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $specialityDeclacSe = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialitySenior'][$n]],
                                ["type" => "Declarative"],
                                ["level" => "Senior"],
                                ["active" => true],
                            ],
                        ]);

                        if ($specialityDeclacSe) {
                            for (
                                $a = 0;
                                $a < count($specialityDeclacSe->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertSeDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityDeclacSe->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    
                    for ($n = 0; $n < count($person['specialityExpert']); ++$n) {
                        $specialityFacEx = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialityExpert'][$n]],
                                ["type" => "Factuelle"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);
                        if ($specialityFacEx) {
                            for (
                                $a = 0;
                                $a < count($specialityFacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityFacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $specialityDeclacEx = $academy->specialities->findOne([
                            '$and' => [
                                ["label" => $person['specialityExpert'][$n]],
                                ["type" => "Declarative"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);

                        if ($specialityDeclacEx) {
                            for (
                                $a = 0;
                                $a < count($specialityDeclacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $specialityDeclacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }
                    for ($n = 0; $n < count($brandExpert); ++$n) {
                        $vehicleFacEx = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $brandExpert[$n]],
                                ["type" => "Factuel"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleFacEx) {
                            for (
                                $a = 0;
                                $a < count($vehicleFacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExFac->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleFacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }

                        $vehicleDeclacEx = $vehicles->findOne([
                            '$and' => [
                                ["brand" => $brandExpert[$n]],
                                ["type" => "Declaratif"],
                                ["level" => "Expert"],
                                ["active" => true],
                            ],
                        ]);
                        if ($vehicleDeclacEx) {
                            for (
                                $a = 0;
                                $a < count($vehicleDeclacEx->quizzes);
                                ++$a
                            ) {
                                $tests->updateOne(
                                    [
                                        "_id" => new MongoDB\BSON\ObjectId(
                                            $insertExDecla->getInsertedId()
                                        ),
                                    ],
                                    [
                                        '$addToSet' => [
                                            "quizzes" =>
                                                $vehicleDeclacEx->quizzes[$a],
                                        ],
                                    ]
                                );
                            }
                        }
                    }

                    $saveTestFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                    ]);
                    $saveTestFac["total"] = count($saveTestFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestFac]
                    );

                    $allocateFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Junior",
                        "activeTest" => true,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateFac);

                    $saveTestDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestDecla["total"] = count($saveTestDecla["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestDecla]
                    );

                    $allocateDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Junior",
                        "activeTest" => true,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateDecla);

                    $saveTestSeFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                    ]);
                    $saveTestSeFac["total"] = count($saveTestSeFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeFac]
                    );

                    $allocateSeFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Senior",
                        "activeTest" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeFac);

                    $saveTestSeDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestSeDecla["total"] = count(
                        $saveTestSeDecla["quizzes"]
                    );
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertSeDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestSeDecla]
                    );

                    $allocateSeDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertSeDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Senior",
                        "activeTest" => false,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateSeDecla);

                    $saveTestExFac = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertExFac->getInsertedId()
                        ),
                    ]);
                    $saveTestExFac["total"] = count($saveTestExFac["quizzes"]);
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertExFac->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestExFac]
                    );

                    $allocateExFac = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertExFac->getInsertedId()
                        ),
                        "type" => "Factuel",
                        "level" => "Expert",
                        "activeTest" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateExFac);

                    $saveTestExDecla = $tests->findOne([
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insertExDecla->getInsertedId()
                        ),
                    ]);
                    $saveTestExDecla["total"] = count(
                        $saveTestExDecla["quizzes"]
                    );
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $insertExDecla->getInsertedId()
                            ),
                        ],
                        ['$set' => $saveTestExDecla]
                    );

                    $allocateExDecla = [
                        "user" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                        "test" => new MongoDB\BSON\ObjectId(
                            $insertExDecla->getInsertedId()
                        ),
                        "type" => "Declaratif",
                        "level" => "Expert",
                        "activeTest" => false,
                        "activeManager" => false,
                        "active" => false,
                        "created" => date("d-m-Y H:i:s"),
                    ];
                    $allocations->insertOne($allocateExDecla);

                    $success_msg = $success_manager;
                }
            }
            elseif ($profile == "Manager") {
                $personM = [
                    "users" => [],
                    "username" => $username,
                    "matricule" => $matricule,
                    "firstName" => ucfirst($firstName),
                    "lastName" => strtoupper($lastName),
                    "email" => $email,
                    "phone" => $phone,
                    "gender" => $gender,
                    "level" => "",
                    "country" => $country,
                    "profile" => "Manager",
                    "birthdate" => $birthdate,
                    "recrutmentDate" => $recrutmentDate,
                    "certificate" => ucfirst($certificate),
                    "subsidiary" => ucfirst($subsidiary),
                    "agency" => ucfirst($agency),
                    "department" => ucfirst($department),
                    "role" => ucfirst($role),
                    "password" => $password,
                    "visiblePassword" => $row["18"],
                    "test" => false,
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $user = $users->insertOne($personM);

                $link = medacapAppBaseUrl($country); 
            
                // Assurez-vous que les variables sont définies et valides
                if (isset($email, $lastName)) {
                    // Échapper les données pour éviter les problèmes de sécurité
                    $managerLastName = htmlspecialchars($lastName);
                    
                    // Vérifier le pays pour déterminer la langue
                    if (isset($country) && $country === "Rwanda") {
                        // Message en anglais pour le Rwanda
                        $message = '<p>Hello Mr. ' . $managerLastName . ',</p>
                                    <p>You have been successfully registered on the <strong>CFAO Mobility Academy Skills Assessment</strong> platform,
                                    you can now view if the recorded information of your employees is correct in the <strong>User List</strong> section,
                                    as well as complete their <strong>Professional Tasks Manager MCQ</strong>.</p>
                                    <p>Your access credentials: <br>
                                        <strong>Username: </strong>' . $username . '<br>
                                        <strong>Password: </strong>' . $row["18"] . '<br>
                                        <strong>Platform Link: </strong>' . $link . '<br>
                                    </p>
                                    <p style="font-size: 20px; font-weight: 300px">Best Regards | Cordialement | よろしくお願いします。</p>';
                        
                        // Sujet de l'e-mail en anglais
                        $subject = 'Registration confirmation of ' . $firstName . ' ' . $lastName;
                        
                    } else {
                        // Message en français (par défaut)
                        $message = '<p>Bonjour M.' . $managerLastName . ',</p>
                                    <p>Vous avez été inscrit avec succès dans la plateforme de <strong>Mesure des Compétences de CFAO Mobility Academy</strong>,
                                    il est désormais possible pour vous de visualiser si les informations enregistrées de vos collaborateurs sont correctes dans l\'espace <strong>Liste des Utilisateurs</strong>,
                                    ainsi que de compléter leurs <strong>QCM Tâches Professionnelles Manager</strong>.</p>
                                    <p>Vos accès : <br>
                                        <strong>Identifiant : </strong>' . $username . '<br>
                                        <strong>Mot de passe : </strong>' . $row["18"] . '<br>
                                        <strong>Lien de plateforme : </strong>' . $link . '<br>
                                    </p>
                                    <p style="font-size: 20px; font-weight: 300px">Cordialement | Best Regards | よろしくお願いします。</p>';
                        
                        // Sujet de l'e-mail en français
                        $subject = 'Confirmation d\'inscription de ' . $firstName . ' ' . $lastName;
                    }
                    
                    // Envoyer l'e-mail
                    $sendMail = importSendRegistrationMail($email, $subject, $message);
                }
                $success_msg = $success_manager;
            }
            elseif ($profile == "Ressource Humaine") {
                $personRh = [
                    "users" => [],
                    "username" => $userName,
                    "matricule" => $matriculation,
                    "firstName" => ucfirst($firstName),
                    "lastName" => ucfirst($lastName),
                    "email" => $email,
                    "phone" => $phone,
                    "gender" => $sex,
                    "level" => "",
                    "country" => $country,
                    "profile" => $profile,
                    "birthdate" => $birthDate,
                    "recrutmentDate" => $recrutementDate,
                    "certificate" => ucfirst($certificate), 
                    "subsidiary" => ucfirst($subsidiary),
                    "agency" => ucfirst($agency),
                    "department" => ucfirst($departement),
                    "role" => ucfirst($fonction),
                    "password" => $password,
                    "visiblePassword" => $row["18"],
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $users->insertOne($personRh);
                
                $link = medacapAppBaseUrl($country); 
            
                // Assurez-vous que les variables sont définies et valides
                // Assurez-vous que les variables sont définies et valides
                if (isset($email, $lastName)) {
                    // Échapper les données pour éviter les problèmes de sécurité
                    $managerLastName = htmlspecialchars($lastName);
                    
                    // Vérifier le pays pour déterminer la langue
                    if (isset($country) && $country === "Rwanda") {
                        // Message en anglais pour le Rwanda
                        $message = '<p>Hello Mr. ' . $managerLastName . ',</p>
                                    <p>You have been successfully registered on the <strong>CFAO Mobility Academy Skills Assessment</strong> platform,
                                    you can now view the recorded information of technicians in the <strong>User List</strong> section,
                                    as well as their results in the <strong>Technician Results by Level</strong> section while monitoring the progress of tests and MCQs.</p>
                                    <p>Your access credentials: <br>
                                        <strong>Username: </strong>' . $username . '<br>
                                        <strong>Password: </strong>' . $row["18"] . '<br>
                                        <strong>Platform Link: </strong>' . $link . '<br>
                                    </p>
                                    <p style="font-size: 20px; font-weight: 100px">Best Regards | Cordialement | よろしくお願いします。</p>';
                        
                        // Sujet de l'e-mail en anglais
                        $subject = 'Registration confirmation of ' . $firstName . ' ' . $lastName;
                        
                    } else {
                        // Message en français (par défaut)
                        $message = '<p>Bonjour M.' . $managerLastName . ',</p>
                                    <p>Vous avez été inscrit avec succès dans la plateforme de <strong>Mesure des Compétences de CFAO Mobility Academy</strong>,
                                    il est désormais possible pour vous de visualiser si les informations enregistrées des techniciens dans l\'espace <strong>Liste des Utilisateurs</strong>,
                                    ainsi que leurs résultats dans l\'espace <strong>Résultats des Techniciens par niveau</strong> tout en faisant le suivi de l\'évolution des tests et QCM.</p>
                                    <p>Vos accès : <br>
                                        <strong>Identifiant : </strong>' . $username . '<br>
                                        <strong>Mot de passe : </strong>' . $row["18"] . '<br>
                                        <strong>Lien de plateforme : </strong>' . $link . '<br>
                                    </p>
                                    <p style="font-size: 20px; font-weight: 100px">Cordialement | Best Regards | よろしくお願いします。</p>';
                        
                        // Sujet de l'e-mail en français
                        $subject = 'Confirmation d\'inscription de ' . $firstName . ' ' . $lastName;
                    }
                    
                    // Envoyer l'e-mail
                    $sendMail = importSendRegistrationMail($email, $subject, $message);
                }
                $success_msg = $success_rh;
            }
            elseif ($profile == "Admin") {
                $personA = [
                    "users" => [],
                    "username" => $username,
                    "matricule" => $matricule,
                    "firstName" => ucfirst($firstName),
                    "lastName" => strtoupper($lastName),
                    "email" => $email,
                    "phone" => $phone,
                    "gender" => $gender,
                    "level" => $level,
                    "country" => $country,
                    "profile" => $profile,
                    "birthdate" => $birthdate,
                    "recrutmentDate" => $recrutmentDate,
                    "certificate" => ucfirst($certificate),
                    "subsidiary" => ucfirst($subsidiary),
                    "agency" => ucfirst($agency),
                    "department" => ucfirst($department),
                    "role" => ucfirst($role),
                    "password" => $password,
                    "visiblePassword" => $row["18"],
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $users->insertOne($personA);
                
                $link = medacapAppBaseUrl($country); 
            
                // Assurez-vous que les variables sont définies et valides
                if (isset($email, $lastName)) {
                    // Échapper les données pour éviter les problèmes de sécurité
                    $managerLastName = htmlspecialchars($lastName);
                    
                    // Vérifier le pays pour déterminer la langue
                    if (isset($country) && $country === "Rwanda") {
                        // Message en anglais pour le Rwanda
                        $message = '<p>Hello Mr. ' . $managerLastName . ',</p>
                                    <p>You have been successfully registered on the <strong>CFAO Mobility Academy Skills Assessment</strong> platform,
                                    you can now view the recorded information of technicians in the <strong>User List</strong> section,
                                    as well as their results in the <strong>Technician Results by Level</strong> section while monitoring the progress of tests and MCQs.</p>
                                    <p>Your access credentials: <br>
                                        <strong>Username: </strong>' . $username . '<br>
                                        <strong>Password: </strong>' . $row["18"] . '<br>
                                        <strong>Platform Link: </strong>' . $link . '<br>
                                    </p>'
                                    ;
                        
                        // Sujet de l'e-mail en anglais
                        $subject = 'Registration confirmation of ' . $firstName . ' ' . $lastName;
                        
                    } else {
                        // Message en français (par défaut)
                        $message = '<p>Bonjour M.' . $managerLastName . ',</p>
                                    <p>Vous avez été inscrit avec succès dans la plateforme de <strong>Mesure des Compétences de CFAO Mobility Academy</strong>,
                                    il est désormais possible pour vous de visualiser si les informations enregistrées des techniciens dans l\'espace <strong>Liste des Utilisateurs</strong>,
                                    ainsi que leurs résultats dans l\'espace <strong>Résultats des Techniciens par niveau</strong> tout en faisant le suivi de l\'évolution des tests et QCM.</p>
                                    <p>Vos accès : <br>
                                        <strong>Identifiant : </strong>' . $username . '<br>
                                        <strong>Mot de passe : </strong>' . $row["18"] . '<br>
                                        <strong>Lien de plateforme : </strong>' . $link . '<br>
                                    </p>
                                    ';
                        
                        // Sujet de l'e-mail en français
                        $subject = 'Confirmation d\'inscription de ' . $firstName . ' ' . $lastName;
                    }
                    
                    // Envoyer l'e-mail
                    $sendMail = importSendRegistrationMail($email, $subject, $message);
                }
                $success_msg = $success_admin;
            }
            elseif ($profile == "Directeur Pièce et Service" || $profile == "Directeur des Opérations" || $profile == "Directeur Général" || $profile == "Directeur Groupe") {
                $personD = [
                    "users" => [],
                    "username" => $username,
                    "matricule" => $matricule,
                    "firstName" => ucfirst($firstName),
                    "lastName" => strtoupper($lastName),
                    "email" => $email,
                    "phone" => $phone,
                    "gender" => $gender,
                    "level" => $level,
                    "country" => $country,
                    "profile" => $profile,
                    "birthdate" => $birthdate,
                    "recrutmentDate" => $recrutmentDate,
                    "certificate" => ucfirst($certificate),
                    "subsidiary" => ucfirst($subsidiary),
                    "agency" => ucfirst($agency),
                    "department" => ucfirst($department),
                    "role" => ucfirst($role),
                    "password" => $password,
                    "visiblePassword" => $row["18"],
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $users->insertOne($personD);
                
                $link = medacapAppBaseUrl($country); 
            
               // Assurez-vous que les variables sont définies et valides
                if (isset($email, $lastName)) {
                    // Échapper les données pour éviter les problèmes de sécurité
                    $managerLastName = htmlspecialchars($lastName);
                    
                    // Vérifier le pays pour déterminer la langue
                    if (isset($country) && $country === "Rwanda") {
                        // Message en anglais pour le Rwanda
                        $message = '<p>Hello Mr. ' . $managerLastName . ',</p>
                                    <p>You have been successfully registered on the <strong>CFAO Mobility Academy Skills Assessment</strong> platform,
                                    you can now view the recorded information of technicians in the <strong>User List</strong> section,
                                    as well as their results in the <strong>Technician Results by Level</strong> section while monitoring the progress of tests and MCQs.</p>
                                    <p>Your access credentials: <br>
                                        <strong>Username: </strong>' . $username . '<br>
                                        <strong>Password: </strong>' . $row["18"] . '<br>
                                        <strong>Platform Link: </strong>' . $link . '<br>
                                    </p>
                                    <p style="font-size: 20px; font-weight: 100px">Best Regards | Cordialement | よろしくお願いします。</p>';
                        
                        // Sujet de l'e-mail en anglais
                        $subject = 'Registration confirmation of ' . $firstName . ' ' . $lastName;
                        
                    } else {
                        // Message en français (par défaut)
                        $message = '<p>Bonjour M.' . $managerLastName . ',</p>
                                    <p>Vous avez été inscrit avec succès dans la plateforme de <strong>Mesure des Compétences de CFAO Mobility Academy</strong>,
                                    il est désormais possible pour vous de visualiser si les informations enregistrées des techniciens dans l\'espace <strong>Liste des Utilisateurs</strong>,
                                    ainsi que leurs résultats dans l\'espace <strong>Résultats des Techniciens par niveau</strong> tout en faisant le suivi de l\'évolution des tests et QCM.</p>
                                    <p>Vos accès : <br>
                                        <strong>Identifiant : </strong>' . $username . '<br>
                                        <strong>Mot de passe : </strong>' . $row["18"] . '<br>
                                        <strong>Lien de plateforme : </strong>' . $link . '<br>
                                    </p>
                                    <p style="font-size: 20px; font-weight: 100px">Cordialement | Best Regards | よろしくお願いします。</p>';
                        
                        // Sujet de l'e-mail en français
                        $subject = 'Confirmation d\'inscription de ' . $firstName . ' ' . $lastName;
                    }
                    
                    // Envoyer l'e-mail
                    $sendMail = importSendRegistrationMail($email, $subject, $message);
                }
                $success_msg = $success_directeur;
            }
        }
    }
    ?>
<?php include_once "partials/header.php"; ?>
<style>
input {
    background-color: #fff ! important;
    border-style: solid;
}
</style>
<!--begin::Title-->
<title><?php echo $import_user ?> | CFAO Mobility Academy</title>
<!--end::Title-->


<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class="container-xxl">
            <!--begin::Modal body-->
            <div class="container mt-5 w-100 text-center">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class="my-3 text-center"><?php echo $import_user ?></h1>

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

                <?php if (!empty($importRecap)) {
                    $recapCreated = count(array_filter($importRecap, function ($r) { return $r['action'] === 'Créé'; }));
                    $recapUpdated = count(array_filter($importRecap, function ($r) { return $r['action'] === 'Mis à jour'; }));
                    $recapTech = count(array_filter($importRecap, function ($r) { return $r['profile'] === 'Technicien'; }));
                ?>
                <div class="card shadow-sm mb-4 text-start">
                    <div class="card-header bg-light">
                        <strong>Récapitulatif import</strong>
                        <span class="text-muted ms-2">(mails désactivés — transmission RH manuelle)</span>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            Liste source CFAO : <strong>37</strong> techniciens —
                            fichier généré : <strong>33</strong> à importer
                            (4 exclus : 3 managers N+2 + 1 chef d'équipe).
                            Traité cette session : <strong><?php echo count($importRecap); ?></strong>
                            (<?php echo $recapCreated; ?> créé(s), <?php echo $recapUpdated; ?> mis à jour, dont <?php echo $recapTech; ?> technicien(s)).
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ligne</th>
                                        <th>Action</th>
                                        <th>Nom</th>
                                        <th>Identifiant</th>
                                        <th>Mot de passe</th>
                                        <th>Manager N+2</th>
                                        <th>Niveau</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($importRecap as $rec) { ?>
                                    <tr>
                                        <td><?php echo (int)$rec['line']; ?></td>
                                        <td><?php echo htmlspecialchars($rec['action']); ?></td>
                                        <td><?php echo htmlspecialchars($rec['name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($rec['username']); ?></code></td>
                                        <td><code><?php echo htmlspecialchars($rec['password']); ?></code></td>
                                        <td>
                                            <?php echo htmlspecialchars($rec['manager']); ?>
                                            <?php if ($rec['manager'] !== '—' && empty($rec['manager_ok'])) { ?>
                                                <span class="badge bg-danger ms-1" title="Manager introuvable en base">!</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($rec['level']); ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php } ?>

                <form enctype='multipart/form-data' method='POST'><br>
                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <!--begin::Label-->
                        <label class="required form-label fw-bolder text-dark fs-6">Importer des utilisateurs via <?php echo $excel ?></label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <div class="input-group">
                            <input type="file" class="form-control form-control-solid" placeholder="" name="excel" style="text-align: center;" />
                            <div class="input-group-append">
                                <span class="input-group-text" style = "height: 10px !important; padding: 15px;">.xlsx</span>
                            </div>
                        </div>
                        <!--end::Input-->
                    </div>
                    <!--end::Input group-->
                    <div class="modal-footer flex-center">
                        <!--begin::Button-->
                        <button type="submit" name="submit" class="btn btn-primary">
                            <span class="indicator-label">
                                <?php echo $valider ?>
                            </span>
                            <span class="indicator-progress">
                                Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                        <!--end::Button-->
                    </div>
                    <!--end::Modal footer-->
                </form>
            </div>
            <!--end::Modal body-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->

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
</script>
<?php include_once "partials/footer.php"; ?>

<?php
} ?>
