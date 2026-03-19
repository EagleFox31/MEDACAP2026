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
    $vehicles = $academy->vehicles;
    $tests = $academy->tests;
    $quizzes = $academy->quizzes;
    $allocations = $academy->allocations;

    if (isset($_POST["submit"])) {
        $firstName = $_POST["firstName"];
        $lastName = $_POST["lastName"];
        $emailAddress = $_POST["email"];
        $phone = $_POST["phone"];
        $matriculation = $_POST["matricule"] ?? 99999999;
        $userName = $_POST["username"];
        $departement = $_POST["department"];
        $fonction = $_POST["role"];
        $sex = $_POST["gender"];
        $agency = $_POST["agency"];
        $certificate = $_POST["certificate"];
        $birthDate = date("d-m-Y", strtotime($_POST["birthdate"]));
        $recrutementDate = date("d-m-Y", strtotime($_POST["recrutmentDate"]));
        if (isset($_POST["level"])) {
          $level = $_POST["level"];
        }
        if (isset($_POST["specialitySenior"])) {
          $specialitySenior = $_POST["specialitySenior"];
        }
        if (isset($_POST["specialityExpert"])) {
          $specialityExpert = $_POST["specialityExpert"];
        }
        if (isset($_POST["brandJu"])) {
          $brandJunior = $_POST["brandJu"];
        }
        if (isset($_POST["brandSe"])) {
          $brandSenior = $_POST["brandSe"];
        }
        if (isset($_POST["brandEx"])) {
          $brandExpert = $_POST["brandEx"];
        }

        $fn = substr($firstName, -2);
        $ln = substr($lastName,0, 2);
        $ma = substr($matriculation, -3);

        $passWord = $ln.$ma.$fn;

        $techs = [];

        $password_hash = sha1($passWord);
        $member = $users->findOne([
            '$and' => [["username" => $userName], ["active" => true]],
        ]);
        $manager = $users->findOne([
            '$and' => [["_id" => new MongoDB\BSON\ObjectId($_SESSION["id"])], ["active" => true]],
        ]);
        if (
            empty($firstName) ||
            empty($lastName) ||
            empty($userName) ||
            empty($agency)
            // !filter_var($emailAddress, FILTER_VALIDATE_EMAIL) ||
            // preg_match('/^[\D]{15}$/', $phone) 
            // preg_match(
            //     '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])[A-Za-z\d\W_]{6,}$/',
            //     $passWord
            // )
        ) {
            $error = $champ_obligatoire;
            $email_error = $email_invalid;
            $phone_error = $phone_invalid;
            $password_error = $password_invalid;
        } elseif ($member) {
            $error_msg = $exist_user;
        } else {
            $person = [
                "users" => [],
                "username" => $userName,
                "matricule" => $matriculation,
                "firstName" => ucfirst($firstName),
                "lastName" => ucfirst($lastName),
                "email" => $emailAddress,
                "phone" => +$phone,
                "gender" => $sex,
                "level" => $level,
                "country" => $manager["country"],
                "profile" => "Technicien",
                "birthdate" => $birthDate,
                "recrutmentDate" => $recrutementDate,
                "certificate" => ucfirst($certificate), 
                "subsidiary" => ucfirst($manager["subsidiary"]),
                "agency" => ucfirst($agency),
                "department" => ucfirst($departement),
                "brandJunior" => $brandJunior ?? [],
                "brandSenior" => $brandSenior ?? [],
                "brandExpert" => $brandExpert ?? [],
                "specialitySenior" => $specialitySenior,
                "specialityExpert" => $specialityExpert,
                "role" => ucfirst($fonction),
                "password" => $password_hash,
                "visiblePassword" => $passWord,
                "manager" => new MongoDB\BSON\ObjectId($_SESSION["id"]),
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $user = $users->insertOne($person);

            $users->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($_SESSION["id"])],
                [
                    '$push' => [
                        "users" => new MongoDB\BSON\ObjectId(
                            $user->getInsertedId()
                        ),
                    ],
                ]
            );
            if (isset($_POST["brandEx"])) {
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
            if (isset($_POST["brandSe"])) {
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
                    "activeTest" => false,
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
                    "activeTest" => false,
                    "activeManager" => false,
                    "active" => false,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $allocations->insertOne($allocateDecla);
  
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
                    "activeTest" => false,
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
                    "activeTest" => false,
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
                    "activeTest" => false,
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
                    "activeTest" => false,
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
                    "created" => date("d-m-Y H:i:s")
                ];
                $allocations->insertOne($allocateExDecla);
  
                $success_msg = $success_tech;
            }
          }
    }
    ?>

<?php include_once "partials/header.php"; ?>

<!--begin::Title-->
<title><?php echo $title_addCollab ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<?php include_once "../partials/background-manager.php"; ?>
<?php setPageBackground("bg-default"); ?>

<!--begin::Body-->
<?php openBackgroundContainer(); ?>
  <!-- Main Title Card -->
  <div class="container-xxl">
    <div class="card shadow-sm mb-5 w-75 mx-auto">
      <div class="card-body p-4">
        <h1 class="text-dark fw-bold text-center fs-1">
          <?php echo $title_addCollab ?>
        </h1>
      </div>
    </div>
  </div>

  <!--begin::Post-->
  <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
    <!--begin::Container-->
    <div class="container-xxl" data-select2-id="select2-data-194-27hh">
      <!--begin::Modal body-->
      <div class="container mt-5 w-50 card bg-opacity-50 bg-white border-0" style="backdrop-filter: blur(10px);">
        <div class="card-body p-4">
          <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">

        <?php if (isset($success_msg)) { ?>
        <div class='alert alert-success alert-dismissible fade show' role='alert'>
          <center><strong><?php echo $success_msg; ?></strong></center>
          <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
            <span aria-hidden='true'>&times;
            </span>
          </button>
        </div>
        <?php } ?>
        <?php if (isset($error_msg)) { ?>
        <div class='alert alert-danger alert-dismissible fade show' role='alert'>
          <center><strong><?php echo $error_msg; ?></strong></center>
          <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
            <span aria-hidden='true'>&times;
            </span>
          </button>
        </div>
        <?php } ?>

        <form method='POST'><br>
          <!--begin::Input group-->
          <div class='row fv-row mb-7'>
            <!--begin::Input group-->
            <div class='row g-9 mb-7'>
              <!--begin::Col-->
              <div class='col-md-6 fv-row'>
                <!--begin::Label-->
                <label class='required form-label fw-bolder text-dark fs-6'><?php echo $prenoms ?></label>
                <!--end::Label-->
                <!--begin::Input-->
                <input class='form-control form-control-solid' placeholder='' name='firstName' />
                <?php if (isset($error)) { ?>
                <span class='text-danger'>
                  <?php echo $error; ?>
                </span>
                <?php } ?>
              </div>
              <!--end::Col-->
              <!--begin::Col-->
              <div class='col-md-6 fv-row'>
                <!--begin::Label-->
                <label class='required form-label fw-bolder text-dark fs-6'><?php echo $noms ?></label>
                <!--end::Label-->
                <!--begin::Input-->
                <input class='form-control form-control-solid' placeholder='' name='lastName' />
                <!--end::Input-->
                <?php if (isset($error)) { ?>
                <span class='text-danger'>
                  <?php echo $error; ?>
                </span>
                <?php } ?>
              </div>
              <!--end::Col-->
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class='fv-row mb-7'>
              <!--begin::Label-->
              <label class='required form-label fw-bolder text-dark fs-6'><?php echo $username ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type='text' class='form-control form-control-solid' placeholder='' name='username' />
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class='fv-row mb-7'>
              <!--begin::Label-->
              <label class='required form-label fw-bolder text-dark fs-6'><?php echo $matricule ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type='text' class='form-control form-control-solid' placeholder='' name='matricule' />
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class='d-flex flex-column mb-7 fv-row'>
              <!--begin::Label-->
              <label class='form-label fw-bolder text-dark fs-6'>
                <span class='required'><?php echo $gender ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name='gender' aria-label='Select a Country' data-control='select2' data-placeholder='<?php echo $select_gender ?>' class='form-select form-select-solid fw-bold'>
                <option>-- <?php echo $select_gender ?> --</option>
                <option value='Feminin'>
                  <?php echo $female ?>
                </option>
                <option value='Masculin'>
                  <?php echo $male ?>
                </option>
              </select>
              <!--end::Input-->
            </div>
            <!--end::Input group-->
            <?php if (isset($error)) { ?>
            <span class='text-danger'>
              <?php echo $error; ?>
            </span>
            <?php } ?>
            <!--begin::Input group-->
            <div class='fv-row mb-7'>
              <!--begin::Label-->
              <label class='form-label fw-bolder text-dark fs-6'>
                <span><?php echo $email ?></span>
                <span class='ms-1' data-bs-toggle='tooltip' title='Votre adresse email doit être active'>
                  <i class='ki-duotone ki-information fs-7'><span class='path1'></span><span class='path2'></span><span class='path3'></span></i>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type='email' class='form-control form-control-solid' placeholder='' name='email'/>
              <!--end::Input-->
              <?php if (isset($email_error)) { ?>
              <span class='text-danger'>
                <?php echo $email_error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class='fv-row mb-7'>
              <!--begin::Label-->
              <label class='required form-label fw-bolder text-dark fs-6'><?php echo $phoneNumber ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type='text' class='form-control form-control-solid' placeholder='' name='phone' />
              <!--end::Input-->
              <?php if (isset($phone_error)) { ?>
              <span class='text-danger'>
                <?php echo $phone_error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class='fv-row mb-15'>
              <!--begin::Label-->
              <label class='required form-label fw-bolder text-dark fs-6'><?php echo $birthdate ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type='date' class='form-control form-control-solid' placeholder='' name='birthdate' />
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <!-- <div class="d-flex flex-column mb-7 fv-row"> -->
              <!--begin::Label-->
              <!-- <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $level ?></span> <span class="ms-1" data-bs-toggle="tooltip" title="Choississez le niveau du technicien ou du manager">
                  <i class="ki-duotone ki-information fs-7"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                </span>
              </label> -->
              <!--end::Label-->
              <!--begin::Input-->
              <!-- <select name="level" onchange="enableSpeciality(this)" aria-label="<?php echo $select_level ?>" class="form-select form-select-solid fw-bold">
                <option><?php echo $select_level ?></option>
                <option value="Junior">
                  <?php echo $junior ?>
                </option>
                <option value="Senior">
                  <?php echo $senior ?>
                </option>
                <option value="Expert">
                  <?php echo $expert ?>
                </option>
              </select> -->
              <!--end::Input-->
              <!-- <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div> -->
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $level ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <div class="form-check" style="margin-top: 10px">
                <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Junior" id="junior">
                <label class="form-check-label text-black">
                  <?php echo $junior ?> (<?php echo $maintenance ?>)
                </label>
              </div>
            <!--begin::Input group-->
              <div class="form-check" style="margin-top: 10px">
                <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Senior" id="senior">
                <label class="form-check-label text-black">
                  <?php echo $senior ?> (<?php echo $reparation ?>)
                </label>
              </div>
            <!--begin::Input group-->
              <div class="form-check" style="margin-top: 10px">
                <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Expert" id="expert">
                <label class="form-check-label text-black">
                  <?php echo $expert ?> (<?php echo $diagnostic ?>)
                </label>
              </div>
            </div> 
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="fv-row mb-7">
              <!--begin::Label-->
              <label class="required form-label fw-bolder text-dark fs-6"><?php echo $agence ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type="text" class="form-control form-control-solid" name="agency" />
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $department ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <!-- <div class="form-check" style="margin-top: 10px">
                <input class="form-check-input" type="radio" value="Equipment" id="equip">
                <label class="form-check-label text-black">
                  Equipment
                </label>
              </div> <br>
              <div class="form-check">
                <input class="form-check-input" type="radio" value="Motors" id="motors">
                <label class="form-check-label text-black">
                  Motors
                </label>
              </div> -->
              <select onchange="enableBrand(this)" name="department" aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_department ?>" class="form-select form-select-solid fw-bold">
                <option>-- <?php echo $select_department ?> --</option>
                <option value="Equipment">
                  Equipment
                </option>
                <option value="Motors">
                  Motors
                </option>
                <option value="Equipment & Motors">
                  Equipment & Motors
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandEquipmentJu">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $junior ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandJu[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="FUSO">
                  <?php echo $fuso ?>
                </option>
                <option value="HINO">
                  <?php echo $hino ?>
                </option>
                <option value="JCB">
                  <?php echo $jcb ?>
                </option>
                <option value="KING LONG">
                  <?php echo $kingLong ?>
                </option>
                <option value="LOVOL">
                  <?php echo $lovol ?>
                </option>
                <option value="MERCEDES TRUCK">
                  <?php echo $mercedesTruck ?>
                </option>
                <option value="RENAULT TRUCK">
                  <?php echo $renaultTruck ?>
                </option>
                <option value="SINOTRUCK">
                  <?php echo $sinotruk ?>
                </option>
                <option value="TOYOTA BT">
                  <?php echo $toyotaBt ?>
                </option>
                <option value="TOYOTA FORKLIFT">
                  <?php echo $toyotaForklift ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandMotorsJu">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $junior ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandJu[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="BYD">
                  <?php echo $byd ?>
                </option>
                <option value="CITROEN">
                  <?php echo $citroen ?>
                </option>
                <option value="MERCEDES">
                  <?php echo $mercedes ?>
                </option>
                <option value="MUTSUBISHI">
                  <?php echo $mutsubishi ?>
                </option>
                <option value="PEUGEOT">
                  <?php echo $peugeot ?>
                </option>
                <option value="SUZUKI">
                  <?php echo $suzuki ?>
                </option>
                <option value="TOYOTA">
                  <?php echo $toyota ?>
                </option>
                <option value="YAMAHA BATEAU">
                  <?php echo $yamahaBateau ?>
                </option>
                <option value="YAMAHA MOTO">
                  <?php echo $yamahaMoto ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandEqMoJu">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $junior ?></span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandJu[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="FUSO">
                  <?php echo $fuso ?>
                </option>
                <option value="HINO">
                  <?php echo $hino ?>
                </option>
                <option value="JCB">
                  <?php echo $jcb ?>
                </option>
                <option value="KING LONG">
                  <?php echo $kingLong ?>
                </option>
                <option value="LOVOL">
                  <?php echo $lovol ?>
                </option>
                <option value="MERCEDES TRUCK">
                  <?php echo $mercedesTruck ?>
                </option>
                <option value="RENAULT TRUCK">
                  <?php echo $renaultTruck ?>
                </option>
                <option value="SINOTRUCK">
                  <?php echo $sinotruk ?>
                </option>
                <option value="TOYOTA BT">
                  <?php echo $toyotaBt ?>
                </option>
                <option value="TOYOTA FORKLIFT">
                  <?php echo $toyotaForklift ?>
                </option>
                <option value="BYD">
                  <?php echo $byd ?>
                </option>
                <option value="CITROEN">
                  <?php echo $citroen ?>
                </option>
                <option value="MERCEDES">
                  <?php echo $mercedes ?>
                </option>
                <option value="MUTSUBISHI">
                  <?php echo $mutsubishi ?>
                </option>
                <option value="PEUGEOT">
                  <?php echo $peugeot ?>
                </option>
                <option value="SUZUKI">
                  <?php echo $suzuki ?>
                </option>
                <option value="TOYOTA">
                  <?php echo $toyota ?>
                </option>
                <option value="YAMAHA BATEAU">
                  <?php echo $yamahaBateau ?>
                </option>
                <option value="YAMAHA MOTO">
                  <?php echo $yamahaMoto ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandEquipmentSe">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $senior ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandSe[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="FUSO">
                  <?php echo $fuso ?>
                </option>
                <option value="HINO">
                  <?php echo $hino ?>
                </option>
                <option value="JCB">
                  <?php echo $jcb ?>
                </option>
                <option value="KING LONG">
                  <?php echo $kingLong ?>
                </option>
                <option value="LOVOL">
                  <?php echo $lovol ?>
                </option>
                <option value="MERCEDES TRUCK">
                  <?php echo $mercedesTruck ?>
                </option>
                <option value="RENAULT TRUCK">
                  <?php echo $renaultTruck ?>
                </option>
                <option value="SINOTRUCK">
                  <?php echo $sinotruk ?>
                </option>
                <option value="TOYOTA BT">
                  <?php echo $toyotaBt ?>
                </option>
                <option value="TOYOTA FORKLIFT">
                  <?php echo $toyotaForklift ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandMotorsSe">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $senior ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandSe[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="BYD">
                  <?php echo $byd ?>
                </option>
                <option value="CITROEN">
                  <?php echo $citroen ?>
                </option>
                <option value="MERCEDES">
                  <?php echo $mercedes ?>
                </option>
                <option value="MUTSUBISHI">
                  <?php echo $mutsubishi ?>
                </option>
                <option value="PEUGEOT">
                  <?php echo $peugeot ?>
                </option>
                <option value="SUZUKI">
                  <?php echo $suzuki ?>
                </option>
                <option value="TOYOTA">
                  <?php echo $toyota ?>
                </option>
                <option value="YAMAHA BATEAU">
                  <?php echo $yamahaBateau ?>
                </option>
                <option value="YAMAHA MOTO">
                  <?php echo $yamahaMoto ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandEqMoSe">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $senior ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandSe[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="FUSO">
                  <?php echo $fuso ?>
                </option>
                <option value="HINO">
                  <?php echo $hino ?>
                </option>
                <option value="JCB">
                  <?php echo $jcb ?>
                </option>
                <option value="KING LONG">
                  <?php echo $kingLong ?>
                </option>
                <option value="LOVOL">
                  <?php echo $lovol ?>
                </option>
                <option value="MERCEDES TRUCK">
                  <?php echo $mercedesTruck ?>
                </option>
                <option value="RENAULT TRUCK">
                  <?php echo $renaultTruck ?>
                </option>
                <option value="SINOTRUCK">
                  <?php echo $sinotruk ?>
                </option>
                <option value="TOYOTA BT">
                  <?php echo $toyotaBt ?>
                </option>
                <option value="TOYOTA FORKLIFT">
                  <?php echo $toyotaForklift ?>
                </option>
                <option value="BYD">
                  <?php echo $byd ?>
                </option>
                <option value="CITROEN">
                  <?php echo $citroen ?>
                </option>
                <option value="MERCEDES">
                  <?php echo $mercedes ?>
                </option>
                <option value="MUTSUBISHI">
                  <?php echo $mutsubishi ?>
                </option>
                <option value="PEUGEOT">
                  <?php echo $peugeot ?>
                </option>
                <option value="SUZUKI">
                  <?php echo $suzuki ?>
                </option>
                <option value="TOYOTA">
                  <?php echo $toyota ?>
                </option>
                <option value="YAMAHA BATEAU">
                  <?php echo $yamahaBateau ?>
                </option>
                <option value="YAMAHA MOTO">
                  <?php echo $yamahaMoto ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" id="metierSe">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $speciality ?> <?php echo $senior ?></span> <span class="ms-1" data-bs-toggle="tooltip" title="Choississez la spécialité du collaborateur">
                  <i class="ki-duotone ki-information fs-7"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="specialitySenior[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_speciality ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_speciality ?> --</option>
                <option value="Boite de Vitesse">
                  <?php echo $boite_vitesse ?>
                </option>
                <option value="Electricté et Electronique">
                  <?php echo $elec ?>
                </option>
                <option value="Hydraulique">
                  <?php echo $hydraulique ?>
                </option>
                <option value="Moteur">
                  <?php echo $moteur ?>
                </option>
                <option value="Transmission">
                  <?php echo $transmission ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandEquipmentEx">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $expert ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandEx[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="FUSO">
                  <?php echo $fuso ?>
                </option>
                <option value="HINO">
                  <?php echo $hino ?>
                </option>
                <option value="JCB">
                  <?php echo $jcb ?>
                </option>
                <option value="KING LONG">
                  <?php echo $kingLong ?>
                </option>
                <option value="LOVOL">
                  <?php echo $lovol ?>
                </option>
                <option value="MERCEDES TRUCK">
                  <?php echo $mercedesTruck ?>
                </option>
                <option value="RENAULT TRUCK">
                  <?php echo $renaultTruck ?>
                </option>
                <option value="SINOTRUCK">
                  <?php echo $sinotruk ?>
                </option>
                <option value="TOYOTA BT">
                  <?php echo $toyotaBt ?>
                </option>
                <option value="TOYOTA FORKLIFT">
                  <?php echo $toyotaForklift ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandMotorsEx">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $expert ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandEx[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="BYD">
                  <?php echo $byd ?>
                </option>
                <option value="CITROEN">
                  <?php echo $citroen ?>
                </option>
                <option value="MERCEDES">
                  <?php echo $mercedes ?>
                </option>
                <option value="MUTSUBISHI">
                  <?php echo $mutsubishi ?>
                </option>
                <option value="PEUGEOT">
                  <?php echo $peugeot ?>
                </option>
                <option value="SUZUKI">
                  <?php echo $suzuki ?>
                </option>
                <option value="TOYOTA">
                  <?php echo $toyota ?>
                </option>
                <option value="YAMAHA BATEAU">
                  <?php echo $yamahaBateau ?>
                </option>
                <option value="YAMAHA MOTO">
                  <?php echo $yamahaMoto ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top: 10px" id="brandEqMoEx">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $brand ?> <?php echo $expert ?></span>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="brandEx[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_brand ?> --</option>
                <option value="FUSO">
                  <?php echo $fuso ?>
                </option>
                <option value="HINO">
                  <?php echo $hino ?>
                </option>
                <option value="JCB">
                  <?php echo $jcb ?>
                </option>
                <option value="KING LONG">
                  <?php echo $kingLong ?>
                </option>
                <option value="LOVOL">
                  <?php echo $lovol ?>
                </option>
                <option value="MERCEDES TRUCK">
                  <?php echo $mercedesTruck ?>
                </option>
                <option value="RENAULT TRUCK">
                  <?php echo $renaultTruck ?>
                </option>
                <option value="SINOTRUCK">
                  <?php echo $sinotruk ?>
                </option>
                <option value="TOYOTA BT">
                  <?php echo $toyotaBt ?>
                </option>
                <option value="TOYOTA FORKLIFT">
                  <?php echo $toyotaForklift ?>
                </option>
                <option value="BYD">
                  <?php echo $byd ?>
                </option>
                <option value="CITROEN">
                  <?php echo $citroen ?>
                </option>
                <option value="MERCEDES">
                  <?php echo $mercedes ?>
                </option>
                <option value="MUTSUBISHI">
                  <?php echo $mutsubishi ?>
                </option>
                <option value="PEUGEOT">
                  <?php echo $peugeot ?>
                </option>
                <option value="SUZUKI">
                  <?php echo $suzuki ?>
                </option>
                <option value="TOYOTA">
                  <?php echo $toyota ?>
                </option>
                <option value="YAMAHA BATEAU">
                  <?php echo $yamahaBateau ?>
                </option>
                <option value="YAMAHA MOTO">
                  <?php echo $yamahaMoto ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="d-flex flex-column mb-7 fv-row d-none" id="metierEx">
              <!--begin::Label-->
              <label class="form-label fw-bolder text-dark fs-6">
                <span class="required"><?php echo $speciality ?> <?php echo $expert ?></span> <span class="ms-1" data-bs-toggle="tooltip" title="Choississez la spécialité du collaborateur">
                  <i class="ki-duotone ki-information fs-7"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                </span>
              </label>
              <!--end::Label-->
              <!--begin::Input-->
              <select name="specialityExpert[]" multiple aria-label="Select a Country" data-control="select2" data-placeholder="<?php echo $select_speciality ?>" class="form-select form-select-solid fw-bold">
                <option value="">-- <?php echo $select_speciality ?> --</option>
                <option value="Boite de Vitesse">
                  <?php echo $boite_vitesse ?>
                </option>
                <option value="Electricté et Electronique">
                  <?php echo $elec ?>
                </option>
                <option value="Hydraulique">
                  <?php echo $hydraulique ?>
                </option>
                <option value="Moteur">
                  <?php echo $moteur ?>
                </option>
                <option value="Transmission">
                  <?php echo $transmission ?>
                </option>
              </select>
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="fv-row mb-7">
              <!--begin::Label-->
              <label class="required form-label fw-bolder text-dark fs-6"><?php echo $certificat ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type="text" class="form-control form-control-solid" placeholder="" name="certificate" />
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="fv-row mb-7">
              <!--begin::Label-->
              <label class="required form-label fw-bolder text-dark fs-6"><?php echo $role ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type="text" class="form-control form-control-solid fw-bold" placeholder="" name="role" />
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <div class="fv-row mb-7">
              <!--begin::Label-->
              <label class="required form-label fw-bolder text-dark fs-6"><?php echo $recrutmentDate ?></label>
              <!--end::Label-->
              <!--begin::Input-->
              <input type="date" class="form-control form-control-solid" placeholder="" name="recrutmentDate" />
              <!--end::Input-->
              <?php if (isset($error)) { ?>
              <span class='text-danger'>
                <?php echo $error; ?>
              </span>
              <?php } ?>
            </div>
            <!--end::Input group-->
            <!--begin::Input group-->
            <!-- <div class="mb-10 fv-row" data-kt-password-meter="true"> -->
              <!--begin::Wrapper-->
              <!-- <div class="mb-1"> -->
                <!--begin::Label-->
                <!-- <label class="required form-label fw-bolder text-dark fs-6"><?php echo $password ?></label> -->
                <!--end::Label-->
                <!--begin::Input wrapper-->
                <!-- <div class="position-relative mb-3">
                  <input class="form-control form-control-solid" type="password" placeholder="" name="password" autocomplete="off" />
                </div> -->
                <!--end::Input wrapper-->
                <!-- <?php if (isset($password_error)) { ?>
                <span class="text-danger">
                  <?php echo $password_error; ?>
                </span>
                <?php } ?>
                <?php if (isset($error)) { ?>
                <span class='text-danger'>
                  <?php echo $error; ?>
                </span>
                <?php } ?> -->
                <!--begin::Meter-->
                <!-- <div class="d-flex align-items-center mb-3" data-kt-password-meter-control="highlight">
                  <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2">
                  </div>
                  <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2">
                  </div>
                  <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px me-2">
                  </div>
                  <div class="flex-grow-1 bg-secondary bg-active-success rounded h-5px">
                  </div>
                </div> -->
                <!--end::Meter-->
              <!-- </div> -->
              <!--end::Wrapper-->
              <!--begin::Hint-->
              <!-- <div class="text-muted"><?php echo $password_text ?>.</div> -->
              <!--end::Input group-->
            <!-- </div> -->
            <!--end::Input group-->
            <!--end::Scroll-->
            <!--end::Modal body-->
            <!--begin::Modal footer-->
            <div class=" modal-footer flex-center">
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
          </div>
        </form>
        </div>
      </div>
      <!--end::Modal body-->
    </div>
    <!--end::Container-->
  </div>
  <!--end::Post-->
<?php closeBackgroundContainer(); ?>
<!--end::Body-->
<?php include "./partials/footer.php"; ?>
<?php
}
?>

<script>
  function enableBrand(answer) {
    checkedRa(answer.value);
  }

  function checkedRa(departValue) {
    var metierSe = document.querySelector('#metierSe');
    var metierEx = document.querySelector('#metierEx');
    var brandMotorsJu = document.querySelector('#brandMotorsJu');
    var brandEquipmentJu = document.querySelector('#brandEquipmentJu');
    var brandEqMoJu = document.querySelector('#brandEqMoJu');
    var brandMotorsSe = document.querySelector('#brandMotorsSe');
    var brandEquipmentSe = document.querySelector('#brandEquipmentSe');
    var brandEqMoSe = document.querySelector('#brandEqMoSe');
    var brandMotorsEx = document.querySelector('#brandMotorsEx');
    var brandEquipmentEx = document.querySelector('#brandEquipmentEx');
    var brandEqMoEx = document.querySelector('#brandEqMoEx');

    var junior = document.querySelector('#junior');
    var senior = document.querySelector('#senior');
    var expert = document.querySelector('#expert');
    if(junior.checked) {
      if(departValue == 'Motors') {
        brandMotorsJu.classList.remove('d-none');
        brandEquipmentJu.classList.add('d-none');
        brandEqMoJu.classList.add('d-none');
        brandMotorsSe.classList.add('d-none');
        brandEquipmentSe.classList.add('d-none');
        brandEqMoSe.classList.add('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.add('d-none');
      } else if(departValue == 'Equipment') {
        brandMotorsJu.classList.add('d-none');
        brandEquipmentJu.classList.remove('d-none');
        brandEqMoJu.classList.add('d-none');
        brandMotorsSe.classList.add('d-none');
        brandEquipmentSe.classList.add('d-none');
        brandEqMoSe.classList.add('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.add('d-none');
      } else if(departValue == 'Equipment & Motors') {
        brandMotorsJu.classList.add('d-none');
        brandEquipmentJu.classList.add('d-none');
        brandEqMoJu.classList.remove('d-none');
        brandMotorsSe.classList.add('d-none');
        brandEquipmentSe.classList.add('d-none');
        brandEqMoSe.classList.add('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.add('d-none');
      }
    } else if(senior.checked) {
      if(departValue == 'Motors') {
        brandMotorsJu.classList.remove('d-none');
        brandEquipmentJu.classList.add('d-none');
        brandEqMoJu.classList.add('d-none');
        brandMotorsSe.classList.remove('d-none');
        brandEquipmentSe.classList.add('d-none');
        brandEqMoSe.classList.add('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.add('d-none');
      } else if(departValue == 'Equipment') {
        brandMotorsJu.classList.add('d-none');
        brandEquipmentJu.classList.remove('d-none');
        brandEqMoJu.classList.add('d-none');
        brandMotorsSe.classList.add('d-none');
        brandEquipmentSe.classList.remove('d-none');
        brandEqMoSe.classList.add('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.add('d-none');
      } else if(departValue == 'Equipment & Motors') {
        brandMotorsJu.classList.add('d-none');
        brandEquipmentJu.classList.add('d-none');
        brandEqMoJu.classList.remove('d-none');
        brandMotorsSe.classList.add('d-none');
        brandEquipmentSe.classList.add('d-none');
        brandEqMoSe.classList.remove('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.add('d-none');
      }
    } else if(expert.checked) {
      if(departValue == 'Motors') {
        brandMotorsJu.classList.remove('d-none');
        brandEquipmentJu.classList.add('d-none');
        brandEqMoJu.classList.add('d-none');
        brandMotorsSe.classList.remove('d-none');
        brandEquipmentSe.classList.add('d-none');
        brandEqMoSe.classList.add('d-none');
        brandMotorsEx.classList.remove('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.add('d-none');
      } else if(departValue == 'Equipment') {
        brandMotorsJu.classList.add('d-none');
        brandEquipmentJu.classList.remove('d-none');
        brandEqMoJu.classList.add('d-none');
        brandMotorsSe.classList.add('d-none');
        brandEquipmentSe.classList.remove('d-none');
        brandEqMoSe.classList.add('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.remove('d-none');
        brandEqMoEx.classList.add('d-none');
      } else if(departValue == 'Equipment & Motors') {
        brandMotorsJu.classList.add('d-none');
        brandEquipmentJu.classList.add('d-none');
        brandEqMoJu.classList.remove('d-none');
        brandMotorsSe.classList.add('d-none');
        brandEquipmentSe.classList.add('d-none');
        brandEqMoSe.classList.remove('d-none');
        brandMotorsEx.classList.add('d-none');
        brandEquipmentEx.classList.add('d-none');
        brandEqMoEx.classList.remove('d-none');
      }
    }
    if(senior.checked) {
      metierSe.classList.remove('d-none');
      metierEx.classList.add('d-none');
    } else if(expert.checked){
      metierSe.classList.remove('d-none');
      metierEx.classList.remove('d-none');
    } else {
      metierSe.classList.add('d-none');
      metierEx.classList.add('d-none');
    }
  }
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