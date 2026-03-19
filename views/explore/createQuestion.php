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
    $quizzes = $academy->quizzes;
    $vehicles = $academy->vehicles;
    $allocations = $academy->allocations;

    if (isset($_POST["submit"])) {
        $label = $_POST["label"];
        $proposal1 = $_POST["proposal1"];
        $proposal2 = $_POST["proposal2"];
        $proposal3 = $_POST["proposal3"];
        $proposal4 = $_POST["proposal4"];
        $reference = $_POST["ref"];
        $specialite = $_POST["speciality"];
        $types = $_POST["type"];
        $levels = $_POST["level"];
        if (isset($_POST["title"])) {
            $title = $_POST["title"];
        }
        if ($_POST["answer"] == "proposal1") {
            $reponse = $proposal1;
        }
        if ($_POST["answer"] == "proposal2") {
            $reponse = $proposal2;
        }
        if ($_POST["answer"] == "proposal3") {
            $reponse = $proposal3;
        }
        if ($_POST["answer"] == "proposal4") {
            $reponse = $proposal4;
        }

        $pic = $_FILES["image"]["name"];
        $tmp_name = $_FILES["image"]["tmp_name"];
        $folder = "../../public/files/" . $image;
        move_uploaded_file($tmp_name, $folder);

        $array = [];

        $exist = $questions->findOne([
            '$and' => [
                ["ref" => $reference],
                ["label" => $label],
                ["speciality" => $specialite],
                ["level" => $levels],
                ["type" => $types],
            ],
        ]);

        if (
            empty($label) ||
            empty($reference) ||
            empty($types) ||
            empty($levels) ||
            empty($specialite)
        ) {
            $error = $champ_obligatoire;
        } elseif ($exist) {
            $error_msg = $error_question;
        } elseif ($types == "Factuelle") {
            $question = [
                "image" => $pic,
                "ref" => $reference,
                "label" => ucfirst($label),
                "proposal1" => ucfirst($proposal1),
                "proposal2" => ucfirst($proposal2),
                "proposal3" => ucfirst($proposal3),
                "proposal4" => ucfirst($proposal4),
                "answer" => ucfirst($reponse),
                "speciality" => ucfirst($specialite),
                "type" => $types,
                "level" => $levels,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $result = $questions->insertOne($question);

            $quizz = $quizzes->findOne([
                '$and' => [
                    ["speciality" => $specialite],
                    ["level" => $levels],
                    ["type" => "Factuel"],
                    ["active" => true],
                ],
            ]);

            $bus = $vehicles->findOne([
                '$and' => [
                    ["label" => "Bus"],
                    ["level" => $levels],
                    ["type" => "Factuel"],
                    ["active" => true],
                ],
            ]);
            $camions = $vehicles->findOne([
                '$and' => [
                    ["label" => "Camions"],
                    ["level" => $levels],
                    ["type" => "Factuel"],
                    ["active" => true],
                ],
            ]);
            $chariots = $vehicles->findOne([
                '$and' => [
                    ["label" => "Chariots"],
                    ["level" => $levels],
                    ["type" => "Factuel"],
                    ["active" => true],
                ],
            ]);
            $engins = $vehicles->findOne([
                '$and' => [
                    ["label" => "Engins"],
                    ["level" => $levels],
                    ["type" => "Factuel"],
                    ["active" => true],
                ],
            ]);
            $voitures = $vehicles->findOne([
                '$and' => [
                    ["label" => "Voitures"],
                    ["level" => $levels],
                    ["type" => "Factuel"],
                    ["active" => true],
                ],
            ]);
            $specialityElecFac = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Electricité et Electronique"],
                    ["level" => $levels],
                    ["type" => "Factuelle"],
                    ["active" => true],
                ],
            ]);
            $specialityHydroFac = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Hydraulique"],
                    ["level" => $levels],
                    ["type" => "Factuelle"],
                    ["active" => true],
                ],
            ]);
            $specialityMoteurFac = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Moteur"],
                    ["level" => $levels],
                    ["type" => "Factuelle"],
                    ["active" => true],
                ],
            ]);
            $specialityTransFac = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Transmission"],
                    ["level" => $levels],
                    ["type" => "Factuelle"],
                    ["active" => true],
                ],
            ]);

            if ($quizz) {
                ++$quizz->total;
                $quizzes->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($quizz->_id)],
                    ['$set' => $quizz]
                );
                $quizzes->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($quizz->_id)],
                    [
                        '$push' => [
                            "questions" => new MongoDB\BSON\ObjectId(
                                $result->getInsertedId()
                            ),
                        ],
                    ]
                );
            } else {
                array_push($array, $result->getInsertedId());
                $quiz = [
                    "questions" => [],
                    "label" => "QCM " . $specialite . "",
                    "type" => "Factuel",
                    "speciality" => ucfirst($specialite),
                    "level" => ucfirst($levels),
                    "total" => 0,
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $insert = $quizzes->insertOne($quiz);
                $quizzes->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insert->getInsertedId()
                        ),
                    ],
                    ['$set' => ["total" => +1]]
                );
                $quizzes->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insert->getInsertedId()
                        ),
                    ],
                    [
                        '$push' => [
                            "questions" => new MongoDB\BSON\ObjectId(
                                $result->getInsertedId()
                            ),
                        ],
                    ]
                );

                if ($specialite == "Arbre de Transmission") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Assistance à la Conduite") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecFac) {
                        $specialityElecFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            ['$set' => $specialityElecFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Transfert") {
                    if ($camions) {
                        if (
                            $camions->brand == "RENAULT TRUCK" ||
                            $camions->brand == "MERCEDES TRUCK" ||
                            $camions->brand == "SINOTRUK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Vitesse") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Vitesse Automatique") {
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Vitesse Mécanique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA FORKLIFT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif (
                    $specialite == "Boite de Vitesse à Variation Continue"
                ) {
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Climatisation") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        if (
                            $camions->brand == "RENAULT TRUCK" ||
                            $camions->brand == "MERCEDES TRUCK" ||
                            $camions->brand == "SINOTRUK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecFac) {
                        $specialityElecFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            ['$set' => $specialityElecFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Demi Arbre de Roue") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA FORKLIFT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Direction") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Electricité et Electronique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecFac) {
                        $specialityElecFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            ['$set' => $specialityElecFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        ++$camions["total"];
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage Electromagnétique") {
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA BT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage Hydraulique") {
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage Pneumatique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Hydraulique") {
                    if ($camions) {
                        if (
                            $camions->brand == "RENAULT TRUCK" ||
                            $camions->brand == "MERCEDES TRUCK" ||
                            $camions->brand == "SINOTRUK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Diesel") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurFac) {
                        $specialityMoteurFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Electrique") {
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurFac) {
                        $specialityMoteurFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Essence") {
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurFac) {
                        $specialityMoteurFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Thermique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        ++$camions["total"];
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurFac) {
                        $specialityMoteurFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Réseaux de Communication") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecFac) {
                        $specialityElecFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            ['$set' => $specialityElecFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Pont") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA FORFLIT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Reducteur") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Pneumatique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        ++$camions["total"];
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension à Lame") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension Ressort") {
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension Pneumatique") {
                    if ($camions) {
                        if (
                            ($camions && $camions->brand == "RENAULT TRUCK") ||
                            $camions->brand == "MERCEDES TRUCK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($voitures) {
                        if ($voitures->brand != "SUZUKI") {
                            $voitures["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $voitures->_id
                                    ),
                                ],
                                ['$set' => $voitures]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $voitures->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Transversale") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransFac) {
                        $specialityTransFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            ['$set' => $specialityTransFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecFac) {
                        $specialityElecFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            ['$set' => $specialityElecFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroFac) {
                        $specialityHydroFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            ['$set' => $specialityHydroFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurFac) {
                        $specialityMoteurFac["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurFac]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurFac->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                }
            }
            $success_msg = $success_question;
        } elseif ($types == "Declarative")  {
            if ($level == "Expert") {
                $question = [
                    "image" => $image,
                    "ref" => $reference,
                    "title" => ucfirst($title),
                    "label" => ucfirst($label),
                    "proposal1" =>
                        "1-" . $speciality . "-" . $level . "-" . $label . "-1",
                    "proposal2" =>
                        "2-" . $speciality . "-" . $level . "-" . $label . "-2",
                    "proposal3" =>
                        "3-" . $speciality . "-" . $level . "-" . $label . "-3",
                    "speciality" => ucfirst($speciality),
                    "type" => $type,
                    "level" => $level,
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $result = $questions->insertOne($question);
            } else {
                $question = [
                    "image" => $image,
                    "ref" => $reference,
                    "label" => ucfirst($label),
                    "proposal1" =>
                        "1-" . $speciality . "-" . $level . "-" . $label . "-1",
                    "proposal2" =>
                        "2-" . $speciality . "-" . $level . "-" . $label . "-2",
                    "proposal3" =>
                        "3-" . $speciality . "-" . $level . "-" . $label . "-3",
                    "speciality" => ucfirst($speciality),
                    "type" => $type,
                    "level" => $level,
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $result = $questions->insertOne($question);
            }

            $quizz = $quizzes->findOne([
                '$and' => [
                    ["speciality" => $specialite],
                    ["level" => $levels],
                    ["type" => "Declaratif"],
                    ["active" => true],
                ],
            ]);

            $bus = $vehicles->findOne([
                '$and' => [
                    ["label" => "Bus"],
                    ["level" => $levels],
                    ["type" => "Declaratif"],
                    ["active" => true],
                ],
            ]);
            $camions = $vehicles->findOne([
                '$and' => [
                    ["label" => "Camions"],
                    ["level" => $levels],
                    ["type" => "Declaratif"],
                    ["active" => true],
                ],
            ]);
            $chariots = $vehicles->findOne([
                '$and' => [
                    ["label" => "Chariots"],
                    ["level" => $levels],
                    ["type" => "Declaratif"],
                    ["active" => true],
                ],
            ]);
            $engins = $vehicles->findOne([
                '$and' => [
                    ["label" => "Engins"],
                    ["level" => $levels],
                    ["type" => "Declaratif"],
                    ["active" => true],
                ],
            ]);
            $voitures = $vehicles->findOne([
                '$and' => [
                    ["label" => "Voitures"],
                    ["level" => $levels],
                    ["type" => "Declaratif"],
                    ["active" => true],
                ],
            ]);
            $specialityElecDecla = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Electricité et Electronique"],
                    ["level" => $levels],
                    ["type" => "Declarative"],
                    ["active" => true],
                ],
            ]);
            $specialityHydroDecla = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Hydraulique"],
                    ["level" => $levels],
                    ["type" => "Declarative"],
                    ["active" => true],
                ],
            ]);
            $specialityMoteurDecla = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Moteur"],
                    ["level" => $levels],
                    ["type" => "Declarative"],
                    ["active" => true],
                ],
            ]);
            $specialityTransDecla = $academy->specialities->findOne([
                '$and' => [
                    ["label" => "Transmission"],
                    ["level" => $levels],
                    ["type" => "Declarative"],
                    ["active" => true],
                ],
            ]);

            if ($quizz) {
                ++$quizz->total;
                $quizzes->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($quizz->_id)],
                    ['$set' => $quizz]
                );
                $quizzes->updateOne(
                    ["_id" => new MongoDB\BSON\ObjectId($quizz->_id)],
                    [
                        '$push' => [
                            "questions" => new MongoDB\BSON\ObjectId(
                                $result->getInsertedId()
                            ),
                        ],
                    ]
                );
            } else {
                array_push($array, $result->getInsertedId());
                $quiz = [
                    "questions" => [],
                    "label" => "QCM " . $specialite . "",
                    "type" => "Declatuel",
                    "speciality" => ucfirst($specialite),
                    "level" => ucfirst($levels),
                    "total" => 0,
                    "active" => true,
                    "created" => date("d-m-Y H:i:s"),
                ];
                $insert = $quizzes->insertOne($quiz);
                $quizzes->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insert->getInsertedId()
                        ),
                    ],
                    ['$set' => ["total" => +1]]
                );
                $quizzes->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $insert->getInsertedId()
                        ),
                    ],
                    [
                        '$push' => [
                            "questions" => new MongoDB\BSON\ObjectId(
                                $result->getInsertedId()
                            ),
                        ],
                    ]
                );

                if ($specialite == "Arbre de Transmission") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Assistance à la Conduite") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecDecla) {
                        $specialityElecDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            ['$set' => $specialityElecDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Transfert") {
                    if ($camions) {
                        if (
                            $camions->brand == "RENAULT TRUCK" ||
                            $camions->brand == "MERCEDES TRUCK" ||
                            $camions->brand == "SINOTRUK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Vitesse") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Vitesse Automatique") {
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Boite de Vitesse Mécanique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA FORKLIFT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif (
                    $specialite == "Boite de Vitesse à Variation Continue"
                ) {
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Climatisation") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        if (
                            $camions->brand == "RENAULT TRUCK" ||
                            $camions->brand == "MERCEDES TRUCK" ||
                            $camions->brand == "SINOTRUK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecDecla) {
                        $specialityElecDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            ['$set' => $specialityElecDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Demi Arbre de Roue") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA FORKLIFT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Direction") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Electricité et Electronique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecDecla) {
                        $specialityElecDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            ['$set' => $specialityElecDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        ++$camions["total"];
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage Electromagnétique") {
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA BT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage Hydraulique") {
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Freinage Pneumatique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Hydraulique") {
                    if ($camions) {
                        if (
                            $camions->brand == "RENAULT TRUCK" ||
                            $camions->brand == "MERCEDES TRUCK" ||
                            $camions->brand == "SINOTRUK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Diesel") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurDecla) {
                        $specialityMoteurDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Electrique") {
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurDecla) {
                        $specialityMoteurDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Essence") {
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurDecla) {
                        $specialityMoteurDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Moteur Thermique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        ++$camions["total"];
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurDecla) {
                        $specialityMoteurDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Réseaux de Communication") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecDecla) {
                        $specialityElecDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            ['$set' => $specialityElecDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Pont") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        if ($chariots->brand == "TOYOTA FORFLIT") {
                            $chariots["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                ['$set' => $chariots]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $chariots->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Reducteur") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Pneumatique") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        ++$camions["total"];
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension à Lame") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension Ressort") {
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Suspension Pneumatique") {
                    if ($camions) {
                        if (
                            ($camions && $camions->brand == "RENAULT TRUCK") ||
                            $camions->brand == "MERCEDES TRUCK"
                        ) {
                            $camions["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                ['$set' => $camions]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $camions->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($voitures) {
                        if ($voitures->brand != "SUZUKI") {
                            $voitures["total"]++;
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $voitures->_id
                                    ),
                                ],
                                ['$set' => $voitures]
                            );
                            $vehicles->updateOne(
                                [
                                    "_id" => new MongoDB\BSON\ObjectId(
                                        $voitures->_id
                                    ),
                                ],
                                [
                                    '$push' => [
                                        "quizzes" => new MongoDB\BSON\ObjectId(
                                            $insert->getInsertedId()
                                        ),
                                    ],
                                ]
                            );
                        }
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                } elseif ($specialite == "Transversale") {
                    if ($bus) {
                        $bus["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            ['$set' => $bus]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($bus->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($camions) {
                        $camions["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            ['$set' => $camions]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($camions->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($chariots) {
                        $chariots["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            ['$set' => $chariots]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $chariots->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($engins) {
                        $engins["total"]++;
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            ['$set' => $engins]
                        );
                        $vehicles->updateOne(
                            ["_id" => new MongoDB\BSON\ObjectId($engins->_id)],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($voitures) {
                        $voitures["total"]++;
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            ['$set' => $voitures]
                        );
                        $vehicles->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $voitures->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityTransDecla) {
                        $specialityTransDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            ['$set' => $specialityTransDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityTransDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityElecDecla) {
                        $specialityElecDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            ['$set' => $specialityElecDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityElecDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityHydroDecla) {
                        $specialityHydroDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            ['$set' => $specialityHydroDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityHydroDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                    if ($specialityMoteurDecla) {
                        $specialityMoteurDecla["total"]++;
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            ['$set' => $specialityMoteurDecla]
                        );
                        $academu->specialities->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $specialityMoteurDecla->_id
                                ),
                            ],
                            [
                                '$push' => [
                                    "quizzes" => new MongoDB\BSON\ObjectId(
                                        $insert->getInsertedId()
                                    ),
                                ],
                            ]
                        );
                    }
                }
            }
            $success_msg = $success_question;
        }
    }
    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $title_question; ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Modal body-->
            <div class="container mt-5 w-50">
                <img src="../../public/images/logo.png" alt="10" height="170" style="display: block; max-width: 75%; height: auto; margin-left: 25px;">
                <h1 class='my-3 text-center'><?php echo $title_question; ?></h1>

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

                <form enctype='multipart/form-data' method='POST'>
                    <br>
                    <!--begin::Input group-->
                    <div class='fv-row mb-7'>
                        <!--begin::Label-->
                        <label class='required form-label fw-bolder text-dark fs-6'><?php echo $Ref; ?></label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <input type='text' class='form-control form-control-solid' placeholder='' name='ref' />
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
                        <label class='required form-label fw-bolder text-dark fs-6'><?php echo $label_question; ?></label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <input type='text' class='form-control form-control-solid' placeholder='' name='label' />
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
                        <label class='form-label fw-bolder text-dark fs-6'><?php echo $titre_question; ?></label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <input type='text' class='form-control form-control-solid' placeholder='' name='title' />
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
                        <label class='form-label fw-bolder text-dark fs-6'><?php echo $image; ?></label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <input type='file' class='form-control form-control-solid' placeholder='' name='image' />
                        <!--end::Input-->
                    </div>
                    <!--end::Input group-->
                    <!--begin::Input group-->
                    <div class='d-flex flex-column mb-7 fv-row'>
                        <!--begin::Label-->
                        <label class='form-label fw-bolder text-dark fs-6'>
                            <span class='required'><?php echo $type; ?></span>
                            </span>
                        </label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <select name='type' onchange='selectif()' aria-label='Select a Country' data-control='select2'
                            data-placeholder='<?php echo $select_type; ?>' class='form-select form-select-solid fw-bold'>
                            <option>-- <?php echo $select_type; ?> --</option>
                            <option value='Declarative'>
                                <?php echo $tache_pro; ?>
                            </option>
                            <option value='Factuelle'>
                                <?php echo $connaissances; ?>
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
                    <div class='row g-9 mb-7' id='prop'>
                        <!--begin::Col-->
                        <div class='col-md-6 fv-row'>
                            <!--begin::Label-->
                            <label class='required form-label fw-bolder text-dark fs-6'><?php echo $proposal; ?>
                                1</label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input class='form-control form-control-solid' placeholder='' name='proposal1' />
                            <!--end::Input-->
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
                            <label class='required form-label fw-bolder text-dark fs-6'><?php echo $proposal; ?>
                                2</label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input class='form-control form-control-solid' placeholder='' name='proposal2' />
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
                    <div class='row g-9 mb-7' id='prop1'>
                        <!--begin::Col-->
                        <div class='col-md-6 fv-row'>
                            <!--begin::Label-->
                            <label class='form-label fw-bolder required text-dark fs-6'><?php echo $proposal; ?>
                                3</label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input class='form-control form-control-solid' placeholder='' name='proposal3' />
                            <!--end::Input-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class='col-md-6 fv-row'>
                            <!--begin::Label-->
                            <label class='form-label fw-bolder required text-dark fs-6'><?php echo $proposal; ?>
                                4</label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input class='form-control form-control-solid' placeholder='' name='proposal4' />
                            <!--end::Input-->
                        </div>
                        <!--end::Col-->
                    </div>
                    <!--end::Input group-->
                    <!--begin::Input group-->
                    <div class='fv-row mb-7' id='answer'>
                        <!--begin::Label-->
                        <label class='form-label fw-bolder required text-dark fs-6'><?php echo $answer; ?></label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <div>
                          <input type="radio" id="contactChoice1" name="answer" value="proposal1" />
                          <label class="fw-bold"><?php echo $proposal; ?> 1</label>
                    
                          <input type="radio" id="contactChoice2" name="answer" value="proposal2" />
                          <label class="fw-bold"><?php echo $proposal; ?> 2</label>
                    
                          <input type="radio" id="contactChoice3" name="answer" value="proposal3" />
                          <label class="fw-bold"><?php echo $proposal; ?> 3</label>
                    
                          <input type="radio" id="contactChoice3" name="answer" value="proposal4" />
                          <label class="fw-bold"><?php echo $proposal; ?> 4</label>
                        </div>
                        <!--end::Input-->
                    </div>
                    <!--end::Input group-->
                    <!--begin::Input group-->
                    <div class="d-flex flex-column mb-7 fv-row">
                        <!--begin::Label-->
                        <label class="form-label fw-bolder text-dark fs-6">
                            <span class="required"><?php echo $speciality; ?></span>
                            </span>
                        </label>
                        <!--end::Label-->
                        <!--begin::Input-->
                            <select name="speciality" aria-label="Select a Country" data-control="select2"
                                data-placeholder="<?php echo $select_speciality; ?>"
                                class="form-select form-select-solid fw-bold">
                                <option>-- <?php echo $select_speciality; ?> --</option>
                                <option value="Arbre de Transmission">
                                    <?php echo $arbre; ?>
                                </option>
                                <option value="Assistance à la Conduite">
                                    <?php echo $assistanceConduite; ?>
                                </option>
                                <option value="Boite de Transfert">
                                    <?php echo $transfert; ?>
                                </option>
                                <option value="Boite de Vitesse">
                                    <?php echo $boite_vitesse; ?>
                                </option>
                                <option value="Boite de Vitesse Automatique">
                                    <?php echo $boite_vitesse_auto; ?>
                                </option>
                                <option value="Boite de Vitesse Mécanique">
                                    <?php echo $boite_vitesse_meca; ?>
                                </option>
                                <option value="Boite de Vitesse à Variation Continue">
                                    <?php echo $boite_vitesse_VC; ?>
                                </option>
                                <option value="Climatisation">
                                    <?php echo $clim; ?>
                                </option>
                                <option value="Demi Arbre de Roue">
                                    <?php echo $demi; ?>
                                </option>
                                <option value="Direction">
                                    <?php echo $direction; ?>
                                </option>
                                <option value="Electricité et Electronique">
                                    <?php echo $elec; ?>
                                </option>
                                <option value="Freinage">
                                    <?php echo $freinage; ?>
                                </option>
                                <option value="Freinage Electromagnétique">
                                    <?php echo $freinageElec; ?>
                                </option>
                                <option value="Freinage Hydraulique">
                                    <?php echo $freinageHydro; ?>
                                </option>
                                <option value="Freinage Pneumatique">
                                    <?php echo $freinagePneu; ?>
                                </option>
                                <option value="Hydraulique">
                                    <?php echo $hydraulique; ?>
                                </option>
                                <option value="Moteur Diesel">
                                    <?php echo $moteurDiesel; ?>
                                </option>
                                <option value="Moteur Electrique">
                                    <?php echo $moteurElectrique; ?>
                                </option>
                                <option value="Moteur Essence">
                                    <?php echo $moteurEssence; ?>
                                </option>
                                <option value="Moteur Thermique">
                                    <?php echo $moteurThermique; ?>
                                </option>
                                <option value="Réseaux de Communication">
                                    <?php echo $multiplexage; ?>
                                </option>
                                <option value="Pneumatique">
                                   <?php echo $pneu; ?>
                                </option>
                                <option value="Pont">
                                    <?php echo $pont; ?>
                                </option>
                                <option value="Reducteur">
                                    <?php echo $reducteur; ?>
                                </option>
                                <option value="Suspension">
                                    <?php echo $suspension; ?>
                                </option>
                                <option value="Suspension à Lame">
                                    <?php echo $suspensionLame; ?>
                                </option>
                                <option value="Suspension Ressort">
                                    <?php echo $suspensionRessort; ?>
                                </option>
                                <option value="Suspension Pneumatique">
                                    <?php echo $suspensionPneu; ?>
                                </option>
                                <option value="Transversale">
                                    <?php echo $transversale; ?>
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
                    <div class='d-flex flex-column mb-7 fv-row'>
                        <!--begin::Label-->
                        <label class='form-label fw-bolder text-dark fs-6'>
                            <span class='required'><?php echo $level; ?></span>
                            </span>
                        </label>
                        <!--end::Label-->
                        <!--begin::Input-->
                        <select name='level' aria-label='Select a Country'
                            data-placeholder='<?php echo $select_level; ?>'
                            data-dropdown-parent='#kt_modal_add_customer' class='form-select form-select-solid fw-bold'>
                            <option value=''>-- <?php echo $select_level; ?> --</option>
                            <option value='Junior'>
                                <?php echo $junior; ?>
                            </option>
                            <option value='Senior'>
                                <?php echo $senior; ?>
                            </option>
                            <option value='Expert'>
                                <?php echo $expert; ?>
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
                    <div class='modal-footer flex-center'>
                        <!--begin::Button-->
                        <button type='submit' name='submit' class='btn btn-primary'>
                            <span class='indicator-label'>
                                <?php echo $valider; ?>
                            </span>
                            <span class='indicator-progress'>
                                Patientez... <span class='spinner-border spinner-border-sm align-middle ms-2'></span>
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
const prop = document.querySelector('#prop')
const prop1 = document.querySelector('#prop1')
const answer = document.querySelector('#answer')

function selectif() {
    const type = document.querySelector("select[name='type']").value
    if (type == 'Declarative') {
        prop.classList.add('hidden')
        prop1.classList.add('hidden')
        answer.classList.add('hidden')
    } else {
        prop.classList.remove('hidden')
        prop1.classList.remove('hidden')
        answer.classList.remove('hidden')
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

<?php include_once "partials/footer.php"; ?>
<?php
} ?>
