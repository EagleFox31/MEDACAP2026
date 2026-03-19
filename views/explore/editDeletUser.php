<?php
session_start();
include_once "../language.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {
    // Liste des spécialités
    $specialities = [
        'Boite de Vitesse' => $boite_vitesse,
        'Electricté et Electronique' => $elec,
        'Hydraulique' => $hydraulique,
        'Moteur' => $moteur,
        'Transmission' => $transmission
    ];
    
    // Liste des pays
    $countries = [
        'Afghanistan', 'Albania', 'Algeria', 'American Samoa', 'Andorra', 'Angola', 
        'Anguilla', 'Antarctica', 'Antigua and Barbuda', 'Argentina', 'Armenia', 
        'Aruba', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 
        'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bermuda', 
        'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Bouvet Island', 
        'Brazil', 'British Indian Ocean Territory', 'Brunei Darussalam', 'Bulgaria', 
        'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroun', 'Canada', 'Cape Verde', 
        'Cayman Islands', 'RCA', 'Chad', 'Chile', 'China', 
        'Christmas Island', 'Cocos (Keeling) Islands', 'Colombia', 'Comoros', 
        'Congo', 'RDC', 'Cook Islands', 'Costa Rica', 
        "Cote d'Ivoire", 'Croatia (Hrvatska)', 'Cuba', 'Cyprus', 'Czech Republic', 
        'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'East Timor', 
        'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 
        'Ethiopia', 'Falkland Islands (Malvinas)', 'Faroe Islands', 'Fiji', 'Finland', 
        'France', 'France, Metropolitan', 'French Guiana', 'French Polynesia', 
        'French Southern Territories', 'Gabon', 'Gambia', 'Georgia', 'Germany', 
        'Ghana', 'Gibraltar', 'Greece', 'Greenland', 'Grenada', 'Guadeloupe', 
        'Guam', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 
        'Heard and Mc Donald Islands', 'Holy See (Vatican City State)', 'Honduras', 
        'Hong Kong', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran (Islamic Republic of)', 
        'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 
        'Kenya', 'Kiribati', 'Korea, Democratic People\'s Republic of', 'Korea, Republic of', 
        'Kuwait', 'Kyrgyzstan', 'Lao People\'s Democratic Republic', 'Latvia', 'Lebanon', 
        'Lesotho', 'Liberia', 'Libyan Arab Jamahiriya', 'Liechtenstein', 'Lithuania', 
        'Luxembourg', 'Macau', 'Macedonia, The Former Yugoslav Republic of', 'Madagascar', 
        'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Martinique', 
        'Mauritania', 'Mauritius', 'Mayotte', 'Mexico', 'Micronesia, Federated States of', 
        'Moldova, Republic of', 'Monaco', 'Mongolia', 'Montserrat', 'Morocco', 
        'Mozambique', 'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Netherlands', 
        'Netherlands Antilles', 'New Caledonia', 'New Zealand', 'Nicaragua', 'Niger', 
        'Nigeria', 'Niue', 'Norfolk Island', 'Northern Mariana Islands', 'Norway', 'Oman', 
        'Pakistan', 'Palau', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 
        'Philippines', 'Pitcairn', 'Poland', 'Portugal', 'Puerto Rico', 'Qatar', 
        'Reunion', 'Romania', 'Russian Federation', 'Rwanda', 'Saint Kitts and Nevis', 
        'Saint LUCIA', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 
        'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Seychelles', 'Sierra Leone', 
        'Singapore', 'Slovakia (Slovak Republic)', 'Slovenia', 'Solomon Islands', 'Somalia', 
        'South Africa', 'South Georgia and the South Sandwich Islands', 'Spain', 
        'Sri Lanka', 'St. Helena', 'St. Pierre and Miquelon', 'Sudan', 'Suriname', 
        'Svalbard and Jan Mayen Islands', 'Swaziland', 'Sweden', 'Switzerland', 
        'Syrian Arab Republic', 'Taiwan, Province of China', 'Tajikistan', 
        'Tanzania, United Republic of', 'Thailand', 'Togo', 'Tokelau', 'Tonga', 
        'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Turks and Caicos Islands', 
        'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 
        'United States', 'United States Minor Outlying Islands', 'Uruguay', 'Uzbekistan', 
        'Vanuatu', 'Venezuela', 'Viet Nam', 'Virgin Islands (British)', 'Virgin Islands (U.S.)', 
        'Wallis and Futuna Islands', 'Western Sahara', 'Yemen', 'Serbia', 'Zambia', 'Zimbabwe'
    ];

     ?>
<?php
require_once "../../vendor/autoload.php"; // Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");
// Connexion à la base de donnée
$academy = $conn->academy; 
// Connexion aux collections
$users = $academy->users;
$tests = $academy->tests;
$vehicles = $academy->vehicles;
$allocations = $academy->allocations;
$results = $academy->results;

// Modification d'un ou plusieurs attributs d'un utilisateur 
if (isset($_POST["update"])) {
    $id = $_POST["userID"];
    $firstName = $_POST["firstName"];
    $lastName = $_POST["lastName"];
    $mail = $_POST["email"];
    $phone = $_POST["phone"];
    $matriculation = $_POST["matricule"];
    $userName = $_POST["username"];
    $userAgency = $_POST["agency"];
    $fonction = $_POST["role"];
    $sex = $_POST["gender"];
    $userPays = $_POST["country"];
    $niveau = $_POST["level"];
    $certificate = $_POST["certificate"];
    $birthDate = date("d-m-Y", strtotime($_POST["birthdate"]));
    $recrutementDate = date("d-m-Y", strtotime($_POST["recrutmentDate"]));
    $person = [
        "username" => $userName,
        "matricule" => $matriculation,
        "firstName" => ucfirst($firstName),
        "lastName" => strtoupper($lastName),
        "email" => $mail,
        "phone" => $phone,
        "gender" => $sex,
        "level" => $niveau,
        "country" => $userPays,
        "birthdate" => $birthDate,
        "recrutmentDate" => $recrutementDate,
        "certificate" => ucfirst($certificate),
        "agency" => ucfirst($userAgency),
        "role" => ucfirst($fonction),
        "updated" => date("d-m-Y H:i:s"),
    ];
    // Requête de modification
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => $person]
    );
    $success_msg = $success_user_edit;
}


// Modification du profile d'un utilisateur 
if (isset($_POST["profile"])) {
$id = $_POST["userID"];
$userProfil = $_POST["profile"];

$user = $users->findOne([
      '$and' => [
          ["users" => new MongoDB\BSON\ObjectId($id)],
          ["active" => true],
      ],
  ]);

if ($userProfil == 'Manager & Technicien') {
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['profile' => 'Manager', 'test' => true] ]
    );  
} elseif ($userProfil == 'Manager') {
    // Supprimer l'attribut manager
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$unset' => ['manager' => ''] ]
    );
    // Supprimer l'ID du technicien dans l'ancien manager
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($user['_id'])],
        ['$pull' => ['users' => new MongoDB\BSON\ObjectId($id)] ]
    );
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['profile' => 'Manager', 'test' => true] ]
    );  
} {
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['profile' => $userProfil, 'test' => false]]
    );  
}

$success_msg = $success_user_edit;
}


// Modification de la spécialité Senior d'un utilisateur 
if (isset($_POST["specialitySenior"])) {
    $id = $_POST["userID"];
    $specialitySenior = $_POST["specialitySenior"];

    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['specialitySenior' => $specialitySenior] ]
    );
    $success_msg = $success_user_edit;
}


// Modification de la spécialité Expert d'un utilisateur 
if (isset($_POST["specialityExpert"])) {
$id = $_POST["userID"];
$specialityExpert = $_POST["specialityExpert"];

$users->updateOne(
    ["_id" => new MongoDB\BSON\ObjectId($id)],
    ['$set' => ['specialityExpert' => $specialityExpert] ]
);
$success_msg = $success_user_edit;
}

if (isset($_POST["department"])) {
    $id = $_POST["userID"];
    $department = $_POST["department"];

    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['department' => $department] ]
    );
    $success_msg = $success_user_edit;
}

if (isset($_POST["subsidiary"])) {
    $id = $_POST["userID"];
    $userSubsidiary = $_POST["subsidiary"];

    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ["subsidiary" => ucfirst($userSubsidiary)] ]
    );
    $success_msg = $success_user_edit;
}


// Modification du manager d'un utilisateur 
if (isset($_POST["manager"])) {
  $id = $_POST["userID"];
  $manager = $_POST["manager"];
  
  $user = $users->findOne([
      '$and' => [
          ["users" => new MongoDB\BSON\ObjectId($id)],
          ["active" => true],
      ],
  ]);
  $managerUser = $users->findOne([
      '$and' => [
          ["_id" => new MongoDB\BSON\ObjectId($id)],
          ["active" => true],
      ],
  ]);
  $result = $results->find([
      '$and' => [
          ["user" => new MongoDB\BSON\ObjectId($id)],
          ["manager" => new MongoDB\BSON\ObjectId($managerUser['manager'])],
          ["active" => true],
      ],
  ])->toArray();
  // Remplacer par l'ID du nouveau manager
  $users->updateOne(
      ["_id" => new MongoDB\BSON\ObjectId($id)],
      ['$set' => ['manager' => new MongoDB\BSON\ObjectId($manager)] ]
  );
  // Supprimer l'ID du technicien dans l'ancien manager
  $users->updateOne(
      ["_id" => new MongoDB\BSON\ObjectId($user['_id'])],
      ['$pull' => ['users' => new MongoDB\BSON\ObjectId($id)] ]
  );
  // Inserer le nouveau manager dans le nouveau manager
  $users->updateOne(
      ["_id" => new MongoDB\BSON\ObjectId($manager)],
      ['$push' => ['users' => new MongoDB\BSON\ObjectId($id)] ]
  );
  foreach ($result as $res) {
      $results->updateOne(
          ["_id" => new MongoDB\BSON\ObjectId($res['_id'])],
          ['$set' => ['manager' => new MongoDB\BSON\ObjectId($manager)] ]
      );
  }
  $success_msg = $success_user_edit;
}


// Modification des marques junior d'un utilisateur 
if (isset($_POST["brandJu"])) {
    $id = $_POST["userID"];
    
    $brandJu = $_POST["brandJu"];
    $resultJu = $results->find([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["level" => "Junior"],
            ["active" => true],
        ],
    ]);
    $allocateFacJu = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Factuel"],
            ["level" => "Junior"],
        ],
    ]);
    
    $allocateDeclaJu = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Declaratif"],
            ["level" => "Junior"]
        ],
    ]);
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        [
            '$set' => [
                "brandJunior" => $brandJu,
            ],
        ]
    );
    $testFac = $tests->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($id)],
            ["type" => "Factuel"],
            ["level" => "Junior"],
            ["active" => true],
        ],
    ]);
    $testDecla = $tests->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($id)],
            ["type" => "Declaratif"],
            ["level" => "Junior"],
            ["active" => true],
        ],
    ]);
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($testFac["_id"])],
        [
            '$set' => [
                "quizzes" => [],
                "brand" => $brandJu,
            ],
        ]
    );
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($testDecla["_id"])],
        [
            '$set' => [
                "quizzes" => [],
                "brand" => $brandJu,
            ],
        ]
    );
    for ($n = 0; $n < count($brandJu); ++$n) {
        $vehicleFac = $vehicles->findOne([
            '$and' => [
                ["brand" => $brandJu[$n]],
                ["type" => "Factuel"],
                ["level" => "Junior"],
                ["active" => true],
            ],
        ]);
        $vehicleDecla = $vehicles->findOne([
            '$and' => [
                ["brand" => $brandJu[$n]],
                ["type" => "Declaratif"],
                ["level" => "Junior"],
                ["active" => true],
            ],
        ]);
        if ($vehicleFac) {
            for (
                $a = 0;
                $a < count($vehicleFac->quizzes);
                ++$a
            ) {
                $tests->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $testFac["_id"]
                        ),
                    ],
                    [
                        '$addToSet' => [
                            "quizzes" =>
                                $vehicleFac->quizzes[$a],
                        ],
                    ]
                );
            }
        }
        if ($vehicleDecla) {
            for (
                $a = 0;
                $a < count($vehicleDecla->quizzes);
                ++$a
            ) {
                $tests->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $testDecla["_id"]
                        ),
                    ],
                    [
                        '$addToSet' => [
                            "quizzes" =>
                                $vehicleDecla->quizzes[$a],
                        ],
                    ]
                );
            }
        }
    }
    $saveTestFac = $tests->findOne([
        "_id" => new MongoDB\BSON\ObjectId(
            $testFac["_id"]
        ),
    ]);
    $saveTestDecla = $tests->findOne([
        "_id" => new MongoDB\BSON\ObjectId(
            $testDecla["_id"]
        ),
    ]);
    
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($saveTestFac["_id"])],
        [
            '$set' => [
                "total" => count($saveTestFac["quizzes"])
            ],
        ]
    );
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($saveTestDecla["_id"])],
        [
            '$set' => [
                "total" => count($saveTestDecla["quizzes"])
            ],
        ]
    );
    $success_msg = $success_user_edit;
}


// Modification des marques senior d'un utilisateur 
if (isset($_POST["brandSe"])) {
    $id = $_POST["userID"];
    
    $brandSe = $_POST["brandSe"];
    $resultJu = $results->find([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["level" => "Junior"],
            ["active" => true],
        ],
    ]);
    $resultSe = $results->find([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["level" => "Senior"],
            ["active" => true],
        ],
    ]);
    $allocateFacJu = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Factuel"],
            ["level" => "Junior"],
        ],
    ]);
    $allocateFacSe = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Factuel"],
            ["level" => "Senior"],
        ],
    ]);
    $allocateDeclaJu = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Declaratif"],
            ["level" => "Junior"]
        ],
    ]);
    $allocateDeclaSe = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Declaratif"],
            ["level" => "Senior"],
        ],
    ]);
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        [
            '$set' => [
                "brandSenior" => $brandSe,
            ],
        ]
    );
    foreach ($brandSe as $bran) {
        $users->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            [
                '$addToSet' => [
                    "brandJunior" => $bran,
                ],
            ]
        );
    }
    $user = $users->findOne([
        '$and' => [
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            ["active" => true],
        ],
    ]);
    $testFac = $tests->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($id)],
            ["type" => "Factuel"],
            ["level" => "Junior"],
            ["active" => true],
        ],
    ]);
    $testDecla = $tests->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($id)],
            ["type" => "Declaratif"],
            ["level" => "Junior"],
            ["active" => true],
        ],
    ]);
    $testFacSe = $tests->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($id)],
            ["type" => "Factuel"],
            ["level" => "Senior"],
            ["active" => true],
        ],
    ]);
    $testDeclaSe = $tests->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($id)],
            ["type" => "Declaratif"],
            ["level" => "Senior"],
            ["active" => true],
        ],
    ]);
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($testFac["_id"])],
        [
            '$set' => [
                "quizzes" => [],
                "brand" => $user['brandJunior'],
            ],
        ]
    );
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($testDecla["_id"])],
        [
            '$set' => [
                "quizzes" => [],
                "brand" => $user['brandJunior'],
            ],
        ]
    );
    
    for ($n = 0; $n < count($user['brandJunior']); ++$n) {
        $vehicleFac = $vehicles->findOne([
            '$and' => [
                ["brand" => $user['brandJunior'][$n]],
                ["type" => "Factuel"],
                ["level" => "Junior"],
                ["active" => true],
            ],
        ]);
        $vehicleDecla = $vehicles->findOne([
            '$and' => [
                ["brand" => $user['brandJunior'][$n]],
                ["type" => "Declaratif"],
                ["level" => "Junior"],
                ["active" => true],
            ],
        ]);
        if ($vehicleFac) {
            for (
                $a = 0;
                $a < count($vehicleFac->quizzes);
                ++$a
            ) {
                $tests->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $testFac["_id"]
                        ),
                    ],
                    [
                        '$addToSet' => [
                            "quizzes" =>
                                $vehicleFac->quizzes[$a],
                        ],
                    ]
                );
            }
        }
        if ($vehicleDecla) {
            for (
                $a = 0;
                $a < count($vehicleDecla->quizzes);
                ++$a
            ) {
                $tests->updateOne(
                    [
                        "_id" => new MongoDB\BSON\ObjectId(
                            $testDecla["_id"]
                        ),
                    ],
                    [
                        '$addToSet' => [
                            "quizzes" =>
                                $vehicleDecla->quizzes[$a],
                        ],
                    ]
                );
            }
        }
    }
    $saveTestFacJu = $tests->findOne([
        "_id" => new MongoDB\BSON\ObjectId(
            $testFac["_id"]
        ),
    ]);
    $saveTestDeclaJu = $tests->findOne([
        "_id" => new MongoDB\BSON\ObjectId(
            $testDecla["_id"]
        ),
    ]);
    
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($saveTestFacJu["_id"])],
        [
            '$set' => [
                "total" => count($saveTestFacJu["quizzes"])
            ],
        ]
    );
    $tests->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($saveTestDeclaJu["_id"])],
        [
            '$set' => [
                "total" => count($saveTestDeclaJu["quizzes"])
            ],
        ]
    );
    
    if ($testFacSe) {
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($testFacSe["_id"])],
            [
                '$set' => [
                    "quizzes" => [],
                    "brand" => $brandSe,
                ],
            ]
        );
        for ($n = 0; $n < count($brandSe); ++$n) {
            $vehicleFacSe = $vehicles->findOne([
                '$and' => [
                    ["brand" => $brandSe[$n]],
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
                                $testFacSe["_id"]
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
        }
        $saveTestFacSe = $tests->findOne([
            "_id" => new MongoDB\BSON\ObjectId(
                $testFacSe["_id"]
            ),
        ]);
        
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($saveTestFacSe["_id"])],
            [
                '$set' => [
                    "total" => count($saveTestFacSe["quizzes"])
                ],
            ]
        );
    } else {
        $newTest = [
            "quizzes" => [],
            "user" => new MongoDB\BSON\ObjectId($id),
            "brand" => $brandSe,
            "type" => "Factuel",
            "level" =>"Senior",
            "total" => 0,
            "active" => true,
            "created" => date("d-m-Y H:i:s"),
        ];
        $insert = $tests->insertOne($newTest);
    
        for ($n = 0; $n < count($brandSe); ++$n) {
            $vehicleFacSe = $vehicles->findOne([
                '$and' => [
                    ["brand" => $brandSe[$n]],
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
                                $insert->getInsertedId()
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
        }
        $test = $tests->findOne([
            '$and' => [
                ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                ["active" => true],
            ],
        ]);
        $test['total'] = count($test['quizzes']);
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
            [
                '$set' => $test
            ]
        );
        $allocate = [
            'user' => new MongoDB\BSON\ObjectId($user['_id']),
            'test' => new MongoDB\BSON\ObjectId($insert->getInsertedId()),
            'type' => 'Factuel',
            'level' => "Senior",
            'activeTest' => false,
            'active' => false,
            'created' => date("d-m-Y H:i:s"),
        ];
        $allocations->insertOne($allocate);
    }
    
    if ($testDeclaSe) {
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($testDeclaSe["_id"])],
            [
                '$set' => [
                    "quizzes" => [],
                    "brand" => $brandSe,
                ],
            ]
        );
        
        for ($n = 0; $n < count($brandSe); ++$n) {
            $vehicleDeclaSe = $vehicles->findOne([
                '$and' => [
                    ["brand" => $brandSe[$n]],
                    ["type" => "Declaratif"],
                    ["level" => "Senior"],
                    ["active" => true],
                ],
            ]);
        
            if ($vehicleDeclaSe) {
                for (
                    $a = 0;
                    $a < count($vehicleDeclaSe->quizzes);
                    ++$a
                ) {
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $testDeclaSe["_id"]
                            ),
                        ],
                        [
                            '$addToSet' => [
                                "quizzes" =>
                                    $vehicleDeclaSe->quizzes[$a],
                            ],
                        ]
                    );
                }
            }
        }
        $saveTestDeclaSe = $tests->findOne([
            "_id" => new MongoDB\BSON\ObjectId(
                $testDeclaSe["_id"]
            ),
        ]);
        
        
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($saveTestDeclaSe["_id"])],
            [
                '$set' => [
                    "total" => count($saveTestDeclaSe["quizzes"])
                ],
                ]
            );
    } else {
        $newTest = [
            "quizzes" => [],
            "user" => new MongoDB\BSON\ObjectId($id),
            "brand" => $brandSe,
            "type" => "Declaratif",
            "level" =>"Senior",
            "total" => 0,
            "active" => true,
            "created" => date("d-m-Y H:i:s"),
        ];
        $insertDecla = $tests->insertOne($newTest);
    
        for ($n = 0; $n < count($brandSe); ++$n) {
            $vehicleDeclaSe = $vehicles->findOne([
                '$and' => [
                    ["brand" => $brandSe[$n]],
                    ["type" => "Declaratif"],
                    ["level" => "Senior"],
                    ["active" => true],
                ],
            ]);
            if ($vehicleDeclaSe) {
                for (
                    $a = 0;
                    $a < count($vehicleDeclaSe->quizzes);
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
                                    $vehicleDeclaSe->quizzes[$a],
                            ],
                        ]
                    );
                }
            }
        }
        $test = $tests->findOne([
            '$and' => [
                ["_id" => new MongoDB\BSON\ObjectId($insertDecla->getInsertedId())],
                ["active" => true],
            ],
        ]);
        $test['total'] = count($test['quizzes']);
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($insertDecla->getInsertedId())],
            [
                '$set' => $test
            ]
        );
        $allocate = [
            'user' => new MongoDB\BSON\ObjectId($user['_id']),
            'test' => new MongoDB\BSON\ObjectId($insertDecla->getInsertedId()),
            'type' => 'Declaratif',
            'level' => "Senior",
            'activeTest' => false,
            'activeManager' => false,
            'active' => false,
            'created' => date("d-m-Y H:i:s"),
        ];
        $allocations->insertOne($allocate);
    }
        $success_msg = $success_user_edit;
}


// Modification des marques expert d'un utilisateur 
if (isset($_POST["brandEx"])) {
    $id = $_POST["userID"];
    $brandEx = $_POST["brandEx"];

    $resultJu = $results->find([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["level" => "Junior"],
            ["active" => true],
        ],
    ]);
    $resultSe = $results->find([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["level" => "Senior"],
            ["active" => true],
        ],
    ]);
    $resultEx = $results->find([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["level" => "Expert"],
            ["active" => true],
        ],
    ]);
    $allocateFacJu = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Factuel"],
            ["level" => "Junior"],
        ],
    ]);
    $allocateFacSe = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Factuel"],
            ["level" => "Senior"],
        ],
    ]);
    $allocateFacEx = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Factuel"],
            ["level" => "Expert"],
        ],
    ]);
    $allocateDeclaJu = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Declaratif"],
            ["level" => "Junior"]
        ],
    ]);
    $allocateDeclaSe = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Declaratif"],
            ["level" => "Senior"],
        ],
    ]);
    $allocateDeclaEx = $allocations->findOne([
        '$and' => [
            ["user" => new MongoDB\BSON\ObjectId($user['_id'])],
            ["type" => "Declaratif"],
            ["level" => "Expert"],
        ],
        ]);
        $users->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            [
                '$set' => [
                    "brandExpert" => $brandEx,
                ],
            ]
        );
        foreach ($brandEx as $bran) {
            $users->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($id)],
                [
                    '$addToSet' => [
                        "brandJunior" => $bran,
                    ],
                ]
            );
            $users->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($id)],
                [
                    '$addToSet' => [
                        "brandSenior" => $bran,
                    ],
                ]
            );
        }
        $user = $users->findOne([
            '$and' => [
                ["_id" => new MongoDB\BSON\ObjectId($id)],
                ["active" => true],
            ],
        ]);
        $testFac = $tests->findOne([
            '$and' => [
                ["user" => new MongoDB\BSON\ObjectId($id)],
                ["type" => "Factuel"],
                ["level" => "Junior"],
                ["active" => true],
            ],
        ]);
        $testDecla = $tests->findOne([
            '$and' => [
                ["user" => new MongoDB\BSON\ObjectId($id)],
                ["type" => "Declaratif"],
                ["level" => "Junior"],
                ["active" => true],
            ],
        ]);
        $testFacSe = $tests->findOne([
            '$and' => [
                ["user" => new MongoDB\BSON\ObjectId($id)],
                ["type" => "Factuel"],
                ["level" => "Senior"],
                ["active" => true],
            ],
        ]);
        $testDeclaSe = $tests->findOne([
            '$and' => [
                ["user" => new MongoDB\BSON\ObjectId($id)],
                ["type" => "Declaratif"],
                ["level" => "Senior"],
                ["active" => true],
            ],
        ]);
        $testFacEx = $tests->findOne([
            '$and' => [
                ["user" => new MongoDB\BSON\ObjectId($id)],
                ["type" => "Factuel"],
                ["level" => "Expert"],
                ["active" => true],
            ],
        ]);
        $testDeclaEx = $tests->findOne([
            '$and' => [
                ["user" => new MongoDB\BSON\ObjectId($id)],
                ["type" => "Declaratif"],
                ["level" => "Expert"],
                ["active" => true],
            ],
        ]);
        for ($n = 0; $n < count($user['brandJunior']); ++$n) {
            $vehicleFac = $vehicles->findOne([
                '$and' => [
                    ["brand" => $user['brandJunior'][$n]],
                    ["type" => "Factuel"],
                    ["level" => "Junior"],
                    ["active" => true],
                ],
            ]);
            $vehicleDecla = $vehicles->findOne([
                '$and' => [
                    ["brand" => $user['brandJunior'][$n]],
                    ["type" => "Declaratif"],
                    ["level" => "Junior"],
                    ["active" => true],
                ],
            ]);
            if ($vehicleFac) {
                for (
                    $a = 0;
                    $a < count($vehicleFac->quizzes);
                    ++$a
                ) {
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $testFac["_id"]
                            ),
                        ],
                        [
                            '$addToSet' => [
                                "quizzes" =>
                                    $vehicleFac->quizzes[$a],
                            ],
                        ]
                    );
                }
            }
            if ($vehicleDecla) {
                for (
                    $a = 0;
                    $a < count($vehicleDecla->quizzes);
                    ++$a
                ) {
                    $tests->updateOne(
                        [
                            "_id" => new MongoDB\BSON\ObjectId(
                                $testDecla["_id"]
                            ),
                        ],
                        [
                            '$addToSet' => [
                                "quizzes" =>
                                    $vehicleDecla->quizzes[$a],
                            ],
                        ]
                    );
                }
            }
        }
        $saveTestFacJu = $tests->findOne([
            "_id" => new MongoDB\BSON\ObjectId(
                $testFac["_id"]
            ),
        ]);
        $saveTestDeclaJu = $tests->findOne([
            "_id" => new MongoDB\BSON\ObjectId(
                $testDecla["_id"]
            ),
        ]);
        
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($saveTestFacJu["_id"])],
            [
                '$set' => [
                    "total" => count($saveTestFacJu["quizzes"])
                ],
            ]
        );
        $tests->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($saveTestDeclaJu["_id"])],
            [
                '$set' => [
                    "total" => count($saveTestDeclaJu["quizzes"])
                ],
            ]
        );
        
        if ($testFacSe) {
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($testFacSe["_id"])],
                [
                    '$set' => [
                        "quizzes" => [],
                        "brand" => $user['brandSenior'],
                    ],
                ]
            );
            for ($n = 0; $n < count($user['brandSenior']); ++$n) {
                $vehicleFacSe = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $user['brandSenior'][$n]],
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
                                    $testFacSe["_id"]
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
            }
            $saveTestFacSe = $tests->findOne([
                "_id" => new MongoDB\BSON\ObjectId(
                    $testFacSe["_id"]
                ),
            ]);
            
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($saveTestFacSe["_id"])],
                [
                    '$set' => [
                        "total" => count($saveTestFacSe["quizzes"])
                    ],
                ]
            );
        } else {
            $newTest = [
                "quizzes" => [],
                "user" => new MongoDB\BSON\ObjectId($id),
                "brand" => $user['brandSenior'],
                "type" => "Factuel",
                "level" =>"Senior",
                "total" => 0,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $insert = $tests->insertOne($newTest);
        
            for ($n = 0; $n < count($user['brandSenior']); ++$n) {
                $vehicleFacSe = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $user['brandSenior'][$n]],
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
                                    $insert->getInsertedId()
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
            }
            $test = $tests->findOne([
                '$and' => [
                    ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                    ["active" => true],
                ],
            ]);
            $test['total'] = count($test['quizzes']);
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                [
                    '$set' => $test
                ]
            );
            $allocate = [
                'user' => new MongoDB\BSON\ObjectId($user['_id']),
                'test' => new MongoDB\BSON\ObjectId($insert->getInsertedId()),
                'type' => 'Factuel',
                'level' => "Senior",
                'activeTest' => false,
                'active' => false,
                'created' => date("d-m-Y H:i:s"),
            ];
            $allocations->insertOne($allocate);
        }
        
        if ($testDeclaSe) {
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($testDeclaSe["_id"])],
                [
                    '$set' => [
                        "quizzes" => [],
                        "brand" => $user['brandSenior'],
                    ],
                ]
            );
            
            for ($n = 0; $n < count($user['brandSenior']); ++$n) {
                $vehicleDeclaSe = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $user['brandSenior'][$n]],
                        ["type" => "Declaratif"],
                        ["level" => "Senior"],
                        ["active" => true],
                    ],
                ]);
            
                if ($vehicleDeclaSe) {
                    for (
                        $a = 0;
                        $a < count($vehicleDeclaSe->quizzes);
                        ++$a
                    ) {
                        $tests->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $testDeclaSe["_id"]
                                ),
                            ],
                            [
                                '$addToSet' => [
                                    "quizzes" =>
                                        $vehicleDeclaSe->quizzes[$a],
                                ],
                            ]
                        );
                    }
                }
            }
            $saveTestDeclaSe = $tests->findOne([
                "_id" => new MongoDB\BSON\ObjectId(
                    $testDeclaSe["_id"]
                ),
            ]);
            
            
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($saveTestDeclaSe["_id"])],
                [
                    '$set' => [
                        "total" => count($saveTestDeclaSe["quizzes"])
                    ],
                    ]
                );
        } else {
            $newTest = [
                "quizzes" => [],
                "user" => new MongoDB\BSON\ObjectId($id),
                "brand" => $user['brandSenior'],
                "type" => "Declaratif",
                "level" =>"Senior",
                "total" => 0,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $insert = $tests->insertOne($newTest);
        
            for ($n = 0; $n < count($user['brandSenior']); ++$n) {
                $vehicleDeclaSe = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $user['brandSenior'][$n]],
                        ["type" => "Declaratif"],
                        ["level" => "Senior"],
                        ["active" => true],
                    ],
                ]);
                if ($vehicleDeclaSe) {
                    for (
                        $a = 0;
                        $a < count($vehicleDeclaSe->quizzes);
                        ++$a
                    ) {
                        $tests->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $insert->getInsertedId()
                                ),
                            ],
                            [
                                '$addToSet' => [
                                    "quizzes" =>
                                        $vehicleDeclaSe->quizzes[$a],
                                ],
                            ]
                        );
                    }
                }
            }
            $test = $tests->findOne([
                '$and' => [
                    ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                    ["active" => true],
                ],
            ]);
            $test['total'] = count($test['quizzes']);
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                [
                    '$set' => $test
                ]
            );
            $allocate = [
                'user' => new MongoDB\BSON\ObjectId($user['_id']),
                'test' => new MongoDB\BSON\ObjectId($insert->getInsertedId()),
                'type' => 'Declaratif',
                'level' => "Senior",
                'activeTest' => false,
                'activeManager' => false,
                'active' => false,
                'created' => date("d-m-Y H:i:s"),
            ];
            $allocations->insertOne($allocate);
        }
        
        if ($testFacEx) {
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($testFacEx["_id"])],
                [
                    '$set' => [
                        "quizzes" => [],
                        "brand" => $brandEx,
                    ],
                ]
            );
            for ($n = 0; $n < count($brandEx); ++$n) {
                $vehicleFacEx = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $brandEx[$n]],
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
                                    $testFacEx["_id"]
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
            }
            $saveTestFacEx = $tests->findOne([
                "_id" => new MongoDB\BSON\ObjectId(
                    $testFacEx["_id"]
                ),
            ]);
            
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($saveTestFacEx["_id"])],
                [
                    '$set' => [
                        "total" => count($saveTestFacEx["quizzes"])
                    ],
                ]
            );
        } else {
            $newTest = [
                "quizzes" => [],
                "user" => new MongoDB\BSON\ObjectId($id),
                "brand" => $brandEx,
                "type" => "Factuel",
                "level" =>"Expert",
                "total" => 0,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $insert = $tests->insertOne($newTest);
        
            for ($n = 0; $n < count($brandEx); ++$n) {
                $vehicleFacEx = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $brandEx[$n]],
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
                                    $insert->getInsertedId()
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
            }
            $test = $tests->findOne([
                '$and' => [
                    ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                    ["active" => true],
                ],
            ]);
            $test['total'] = count($test['quizzes']);
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                [
                    '$set' => $test
                ]
            );
            $allocate = [
                'user' => new MongoDB\BSON\ObjectId($user['_id']),
                'test' => new MongoDB\BSON\ObjectId($insert->getInsertedId()),
                'type' => 'Factuel',
                'level' => "Expert",
                'activeTest' => false,
                'active' => false,
                'created' => date("d-m-Y H:i:s"),
            ];
            $allocations->insertOne($allocate);
        }
        
        if ($testDeclaEx) {
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($testDeclaEx["_id"])],
                [
                    '$set' => [
                        "quizzes" => [],
                        "brand" => $brandEx,
                    ],
                ]
            );
            
            for ($n = 0; $n < count($brandEx); ++$n) {
                $vehicleDeclaEx = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $brandEx[$n]],
                        ["type" => "Declaratif"],
                        ["level" => "Expert"],
                        ["active" => true],
                    ],
                ]);
            
                if ($vehicleDeclaEx) {
                    for (
                        $a = 0;
                        $a < count($vehicleDeclaEx->quizzes);
                        ++$a
                    ) {
                        $tests->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $testDeclaEx["_id"]
                                ),
                            ],
                            [
                                '$addToSet' => [
                                    "quizzes" =>
                                        $vehicleDeclaEx->quizzes[$a],
                                ],
                            ]
                        );
                    }
                }
            }
            $saveTestDeclaEx = $tests->findOne([
                "_id" => new MongoDB\BSON\ObjectId(
                    $testDeclaEx["_id"]
                ),
            ]);
            
            
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($saveTestDeclaEx["_id"])],
                [
                    '$set' => [
                        "total" => count($saveTestDeclaEx["quizzes"])
                    ],
                    ]
                );
        } else {
            $newTest = [
                "quizzes" => [],
                "user" => new MongoDB\BSON\ObjectId($id),
                "brand" => $brandEx,
                "type" => "Declaratif",
                "level" =>"Expert",
                "total" => 0,
                "active" => true,
                "created" => date("d-m-Y H:i:s"),
            ];
            $insert = $tests->insertOne($newTest);
        
            for ($n = 0; $n < count($brandEx); ++$n) {
                $vehicleDeclaEx = $vehicles->findOne([
                    '$and' => [
                        ["brand" => $brandEx[$n]],
                        ["type" => "Declaratif"],
                        ["level" => "Expert"],
                        ["active" => true],
                    ],
                ]);
                if ($vehicleDeclaEx) {
                    for (
                        $a = 0;
                        $a < count($vehicleDeclaEx->quizzes);
                        ++$a
                    ) {
                        $tests->updateOne(
                            [
                                "_id" => new MongoDB\BSON\ObjectId(
                                    $insert->getInsertedId()
                                ),
                            ],
                            [
                                '$addToSet' => [
                                    "quizzes" =>
                                        $vehicleDeclaEx->quizzes[$a],
                                ],
                            ]
                        );
                    }
                }
            }
            $test = $tests->findOne([
                '$and' => [
                    ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                    ["active" => true],
                ],
            ]);
            $test['total'] = count($test['quizzes']);
            $tests->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($insert->getInsertedId())],
                [
                    '$set' => $test
                ]
            );
            $allocate = [
                'user' => new MongoDB\BSON\ObjectId($user['_id']),
                'test' => new MongoDB\BSON\ObjectId($insert->getInsertedId()),
                'type' => 'Declaratif',
                'level' => "Expert",
                'activeTest' => false,
                'activeManager' => false,
                'active' => false,
                'created' => date("d-m-Y H:i:s"),
            ];
            $allocations->insertOne($allocate);
        }
    $success_msg = $success_user_edit;
}

// Expert de la table des utilisateurs
if (isset($_POST["excel"])) {
    $spreadsheet = new Spreadsheet();
    $excel_writer = new Xlsx($spreadsheet);
    $spreadsheet->setActiveSheetIndex(0);
    $activeSheet = $spreadsheet->getActiveSheet();
    $activeSheet->setCellValue("A1", "Prénoms");
    $activeSheet->setCellValue("B1", "Noms");
    $myObj = $users->find();
    $i = 2;
    foreach ($myObj as $row) {
        $activeSheet->setCellValue("A" . $i, $row->lastName);
        $activeSheet->setCellValue("A" . $i, $row->firstName);
        $i++;
    }
    $filename = "collaborateurs.xlsx";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment;filename=" . $filename);
    header("cache-Control: max-age=0");
    $excel_writer->save("php://output");
}
if (isset($_POST["password"])) {
    // Password modification
    $id = $_POST["userID"];
    $passWord = $_POST["password"]; // Check if the password contains at least 8 characters, including at least one uppercase letter, one lowercase letter, and one special character.
    if (
        preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])[A-Za-z\d\W_]{6,}$/',
            $passWord
        )
    ) {
        $error =
            "Le mot de passe doit être au moins de six caractères contenir au moins un chiffre, une lettre majiscule";
    } else {
        $password_hash = sha1($passWord);
        $users->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($id)],
            ['$set' => ["password" => $password_hash, "updated" => date("d-m-Y H:i:s"), "visiblePassword" => $passWord]]
        );
        $success_msg = $success_user_edit;
    }
}
if (isset($_POST["delete"])) {
    $id = $_POST["userID"];
    $member = $users->findOne([
        '$and' => [
            [
                "_id" => new MongoDB\BSON\ObjectId($id),
                "active" => true
            ]
        ],
    ]);
    $member["active"] = false;
    $member->deleted = date("d-m-Y H:i:s");
    $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => $member]
    );
    if ($member['profile'] == 'Technicien') {
        $users->updateOne(
            ["_id" => new MongoDB\BSON\ObjectId($member['manager'])],
            ['$pull' => [ 'users' => new MongoDB\BSON\ObjectId($id) ] ]
        );
    }
    $success_msg = $success_user_delet;
}
if (isset($_POST["retire-technician-manager"])) {
    $id = $_POST["userID"];
    $manager = $users->findOne([
        '$and' => [["_id" => new MongoDB\BSON\ObjectId($id), "active" => true]],
    ]);
    $membre = $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($manager->_id)],
        ['$pull' => ["users" => new MongoDB\BSON\ObjectId($id)]]
    );
    $user = $users->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$unset' => ["manager" => 1]]
    );
    $success_msg = "Membre retiré avec succes.";
}
?>

<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<?php if ($_SESSION["profile"] == "Admin") { ?>
<title><?php echo $title_edit_user?> | CFAO Mobility Academy</title>
<!--end::Title-->
<?php } ?>
<?php if ($_SESSION["profile"] == "Super Admin") { ?>
<title><?php echo $title_edit_sup_user?> | CFAO Mobility Academy</title>
<!--end::Title-->
<?php } ?>

<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <?php if ($_SESSION["profile"] == "Super Admin") { ?>
                <!--begin::Title-->
                <h1 class="text-dark fw-bolder my-1 fs-1">
                    <?php echo $title_edit_sup_user ?> </h1>
                <!--end::Title-->
                <?php } ?>
                <?php if ($_SESSION["profile"] == "Admin") { ?>
                <!--begin::Title-->
                <h1 class="text-dark fw-bolder my-1 fs-1">
                    <?php echo $title_edit_user ?> </h1>
                <!--end::Title-->
                <?php } ?>
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12"
                            placeholder="Recherche...">
                    </div>
                    <!--end::Search-->
                </div>
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
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="w-10px pe-2 sorting_disabled" rowspan="1" colspan="1" aria-label=""
                                            style="width: 29.8906px;">
                                            <div
                                                class="form-check form-check-sm form-check-custom form-check-solid me-3">
                                                <input class="form-check-input" type="checkbox" value="1">
                                            </div>
                                        </th>
                                        <th class="min-w-225px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $prenomsNoms ?>
                                        </th>
                                        <?php if ($_SESSION["profile"] == "Admin" ) { ?>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $email ?></th>
                                        <?php } ?>
                                        <?php if ($_SESSION["profile"] == "Super Admin" ) { ?>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Email: activate to sort column ascending"
                                            style="width: 155.266px;"><?php echo $pays ?></th>
                                        <?php } ?>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $profil ?>
                                        </th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $department ?></th>
                                        <th class="min-w-125px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;"><?php echo $levelTech ?>
                                        </th>
                                        <th class="min-w-50px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;">
                                            <?php echo $edit ?></th>
                                        <?php if ($_SESSION["profile"] == "Super Admin") { ?>
                                        <th class="min-w-50px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Created Date: activate to sort column ascending"
                                            style="width: 152.719px;">
                                            <?php echo $delete ?></th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                    $query = [
                                        'active' => true
                                    ];
                                    if ($_SESSION["profile"] !== "Super Admin" || $_SESSION["profile"] !== "Directeur Groupe") {
                                        if ($_SESSION["department"] !== 'Equipment & Motors') {
                                            $query['department'] = $_SESSION["department"];
                                        }
                                    }
                                    $persons = $users->find($query);
                                    foreach ($persons as $user) { ?>
                                    <?php if (
                                        $_SESSION["profile"] == "Admin"
                                    ) { ?>
                                    <?php if (
                                        $user["profile"] != "Admin" &&
                                        $user["profile"] != "Super Admin" && $_SESSION["subsidiary"] == $user["subsidiary"]
                                    ) { ?>
                                    <tr class="odd" etat="<?php echo $user->active; ?>">
                                        <!-- <td>
                                            <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                <input class="form-check-input" id="checkbox" type="checkbox"
                                                    onclick="enable()" value="<?php echo $user->_id; ?>">
                                            </div>
                                        </td> -->
                                        <td></td>
                                        <td data-filter="search">
                                            <?php echo $user->firstName; ?> <?php echo $user->lastName; ?>
                                        </td>
                                        <td data-filter="email">
                                            <?php echo $user->email; ?>
                                        </td>
                                        <?php if (
                                            $user["profile"] == "Manager" && $user["test"] == true
                                        ) { ?>
                                        <td data-order="subsidiary">
                                            Manager - Technicien
                                        </td>
                                        <?php } else { ?>
                                        <td data-order="subsidiary">
                                            <?php echo $user->profile; ?>
                                        </td>
                                        <?php } ?>
                                        <td data-order="subsidiary">
                                            <?php echo $user->department; ?>
                                        </td>
                                        <td data-order="subsidiary">
                                            <?php echo $user->level; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-success w-30px h-30px me-3"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_update_details<?php echo $user->_id; ?>">
                                                <i class="fas fa-edit fs-5"></i></button>
                                        </td>
                                        <?php if (
                                        $_SESSION["profile"] == "Super Admin"
                                    ) { ?>
                                        <td>
                                            <button class="btn btn-icon btn-light-danger w-30px h-30px"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_desactivate<?php echo $user->_id; ?>">
                                                <i class="fas fa-trash fs-5"></i></button>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php } ?>
                                    <?php } ?>
                                    <?php if (
                                        $_SESSION["profile"] == "Super Admin"
                                    ) { ?>
                                    <?php if (
                                        $user["profile"] != "Super Admin"
                                    ) { ?>
                                    <tr class="odd" etat="<?php echo $user->active; ?>">
                                        <!-- <td>
                                            <div class="form-check form-check-sm form-check-custom form-check-solid">
                                                <input class="form-check-input" id="checkbox" type="checkbox"
                                                    onclick="enable()" value="<?php echo $user->_id; ?>">
                                            </div>
                                        </td> -->
                                        <td></td>
                                        <td data-filter="search">
                                            <?php echo $user->firstName; ?> <?php echo $user->lastName; ?>
                                        </td>
                                        <td data-filter="email">
                                            <?php echo $user->country; ?>
                                        </td>
                                        <?php if (
                                            $user["profile"] == "Manager" && $user["test"] == true
                                        ) { ?>
                                        <td data-order="subsidiary">
                                            Manager - Technicien
                                        </td>
                                        <?php } else { ?>
                                        <td data-order="subsidiary">
                                            <?php echo $user->profile; ?>
                                        </td>
                                        <?php } ?>
                                        <td data-order="subsidiary">
                                            <?php echo $user->department; ?>
                                        </td>
                                        <td data-order="subsidiary">
                                            <?php echo $user->level; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-light-success w-30px h-30px me-3"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_update_details<?php echo $user->_id; ?>">
                                                <i class="fas fa-edit fs-5"></i></button>
                                        </td>
                                        <?php if (
                                        $_SESSION["profile"] == "Super Admin"
                                    ) { ?>
                                        <td>
                                            <button class="btn btn-icon btn-light-danger w-30px h-30px"
                                                data-bs-toggle="modal"
                                                data-bs-target="#kt_modal_desactivate<?php echo $user->_id; ?>">
                                                <i class="fas fa-trash fs-5"></i></button>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php } ?>
                                    <?php } ?>
                                    <!-- begin:: Modal - Confirm suspend -->
                                    <div class="modal" id="kt_modal_desactivate<?php echo $user->_id; ?>" tabindex="-1"
                                        aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-450px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="userID"
                                                        value="<?php echo $user->_id; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder">
                                                            <?php echo $delet ?>
                                                        </h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-kt-users-modal-action="close" data-bs-dismiss="modal">
                                                            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                                                            <span class="svg-icon svg-icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                    height="24" viewBox="0 0 24 24" fill="none">
                                                                    <rect opacity="0.5" x="6" y="17.3137" width="16"
                                                                        height="2" rx="1"
                                                                        transform="rotate(-45 6 17.3137)"
                                                                        fill="black" />
                                                                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                                                        transform="rotate(45 7.41422 6)" fill="black" />
                                                                </svg>
                                                            </span>
                                                            <!--end::Svg Icon-->
                                                        </div>
                                                        <!--end::Close-->
                                                    </div>
                                                    <!--end::Modal header-->
                                                    <!--begin::Modal body-->
                                                    <div class="modal-body py-10 px-lg-17">
                                                        <h4>
                                                            <?php echo $delet_text ?>
                                                        </h4>
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="reset" class="btn btn-light me-3"
                                                            id="closeDesactivate" data-bs-dismiss="modal"
                                                            data-kt-users-modal-action="cancel">
                                                            <?php echo $non ?>
                                                        </button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="delete" class="btn btn-danger">
                                                            <?php echo $oui ?>
                                                        </button>
                                                        <!--end::Button-->
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                        <!-- end Modal dialog -->
                                    </div>
                                    <!-- end:: Modal - Confirm suspend -->
                                    <!--begin::Modal - Update user details-->
                                    <div class="modal" id="kt_modal_update_details<?php echo $user->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog modal-dialog-centered mw-650px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Form-->
                                                <form class="form" method="POST" id="kt_modal_update_user_form">
                                                    <input type="hidden" name="userID"
                                                        value="<?php echo $user->_id; ?>">
                                                    <!--begin::Modal header-->
                                                    <div class="modal-header" id="kt_modal_update_user_header">
                                                        <!--begin::Modal title-->
                                                        <h2 class="fs-2 fw-bolder">
                                                            <?php echo $editer_data ?></h2>
                                                        <!--end::Modal title-->
                                                        <!--begin::Close-->
                                                        <div class="btn btn-icon btn-sm btn-active-icon-primary"
                                                            data-kt-users-modal-action="close" data-bs-dismiss="modal"
                                                            data-kt-menu-dismiss="true">
                                                            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                                                            <span class="svg-icon svg-icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                                    height="24" viewBox="0 0 24 24" fill="none">
                                                                    <rect opacity="0.5" x="6" y="17.3137" width="16"
                                                                        height="2" rx="1"
                                                                        transform="rotate(-45 6 17.3137)"
                                                                        fill="black" />
                                                                    <rect x="7.41422" y="6" width="16" height="2" rx="1"
                                                                        transform="rotate(45 7.41422 6)" fill="black" />
                                                                </svg>
                                                            </span>
                                                            <!--end::Svg Icon-->
                                                        </div>
                                                        <!--end::Close-->
                                                    </div>
                                                    <!--end::Modal header-->
                                                    <!--begin::Modal body-->
                                                    <div class="modal-body py-10 px-lg-17">
                                                        <!--begin::Scroll-->
                                                        <div class="d-flex flex-column scroll-y me-n7 pe-7"
                                                            id="kt_modal_update_user_scroll" data-kt-scroll="true"
                                                            data-kt-scroll-activate="{default: false, lg: true}"
                                                            data-kt-scroll-max-height="auto"
                                                            data-kt-scroll-dependencies="#kt_modal_update_user_header"
                                                            data-kt-scroll-wrappers="#kt_modal_update_user_scroll"
                                                            data-kt-scroll-offset="300px">
                                                            <!--begin::User form-->
                                                            <div id="kt_modal_update_user_user_info"
                                                                class="collapse show">
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $username ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="username"
                                                                        value="<?php echo $user->username; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $matricule ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="matricule"
                                                                        value="<?php echo $user->matricule; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="row g-9 mb-7">
                                                                    <!--begin::Col-->
                                                                    <div class="col-md-6 fv-row">
                                                                        <!--begin::Label-->
                                                                        <label
                                                                            class="form-label fw-bolder text-dark fs-6"><?php echo $prenoms ?></label>
                                                                        <!--end::Label-->
                                                                        <!--begin::Input-->
                                                                        <input class="form-control form-control-solid"
                                                                            placeholder="" name="firstName"
                                                                            value="<?php echo $user->firstName; ?>" />
                                                                        <!--end::Input-->
                                                                    </div>
                                                                    <!--end::Col-->
                                                                    <!--begin::Col-->
                                                                    <div class="col-md-6 fv-row">
                                                                        <!--begin::Label-->
                                                                        <label
                                                                            class="form-label fw-bolder text-dark fs-6"><?php echo $noms ?></label>
                                                                        <!--end::Label-->
                                                                        <!--begin::Input-->
                                                                        <input class="form-control form-control-solid"
                                                                            placeholder="" name="lastName"
                                                                            value="<?php echo $user->lastName; ?>" />
                                                                        <!--end::Input-->
                                                                    </div>
                                                                    <!--end::Col-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label class="fs-6 fw-bold mb-2">
                                                                        <span><?php echo $Email ?></span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="email"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="email"
                                                                        value="<?php echo $user->email; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span class=""><?php echo $gender ?></span>
                                                                        <span class='ms-1' data-bs-toggle='tooltip'
                                                                            title="Choississez le sexe de l' utilisateur">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="gender" aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $user->gender ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <option value="<?php echo $user->gender ?>">
                                                                            <?php echo $user->gender ?></option>
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
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $phoneNumber ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="phone"
                                                                        value="<?php echo $user->phone; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $birthdate ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="birthdate"
                                                                        value="<?php echo $user->birthdate; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class='d-flex flex-column mb-7 fv-row'>
                                                                    <!--begin::Label-->
                                                                    <label class='form-label fw-bolder text-dark fs-6'>
                                                                        <span><?php echo $levelTech ?></span> <span
                                                                            class='ms-1' data-bs-toggle='tooltip'
                                                                            title="Votre niveau technique">
                                                                            <i class='ki-duotone ki-information fs-7'><span
                                                                                    class='path1'></span><span
                                                                                    class='path2'></span><span
                                                                                    class='path3'></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name='level' aria-label='Select a Country'
                                                                        data-placeholder='Niveau de la formation'
                                                                        class='form-select form-select-solid fw-bold'>
                                                                        <?php 
                                                                          // On ne montre pas l'option correspondant à la valeur actuelle
                                                                          $levels = ['Junior' => $junior, 'Senior' => $senior, 'Expert' => $expert];
                                                                          
                                                                          foreach ($levels as $level => $label): 
                                                                          ?>
                                                                        <option
                                                                            value="<?php echo htmlspecialchars($level); ?>"
                                                                            <?php echo ($level == $user['level']) ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($label); ?>
                                                                        </option>
                                                                        <?php 
                                                                          endforeach; 
                                                                          ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class='d-flex flex-column mb-7 fv-row'>
                                                                    <!--begin::Label-->
                                                                    <label class='form-label fw-bolder text-dark fs-6'>
                                                                        <span><?php echo $pays ?></span> <span
                                                                            class='ms-1' data-bs-toggle='tooltip'
                                                                            title="Votre pays d'origine">
                                                                            <i class='ki-duotone ki-information fs-7'><span
                                                                                    class='path1'></span><span
                                                                                    class='path2'></span><span
                                                                                    class='path3'></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name='country' aria-label='Select a Country'
                                                                        data-control='select2'
                                                                        data-placeholder='<?php echo $user->country; ?>'
                                                                        class='form-select form-select-solid fw-bold'>
                                                                        <option value="<?php echo $user->country; ?>">
                                                                            <?php echo $user->country; ?></option>
                                                                        <?php
                                                                      foreach ($countries as $country) {
                                                                          echo "<option value='$country'>$country</option>";
                                                                      }
                                                                      ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $profil ?></span> <span
                                                                            class='ms-1' data-bs-toggle='tooltip'
                                                                            title="Choississez le profile de l' utilisateur">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="profile" aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $user->profile ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php if (
                                                                          $user["profile"] == "Manager" && $user["test"] == true
                                                                      ) { ?>
                                                                        <option value="<?php echo $user->profile; ?>">
                                                                            Manager - Technicien</option>
                                                                        <?php } else { ?>
                                                                        <option value="<?php echo $user->profile; ?>">
                                                                            <?php echo $user->profile; ?></option>
                                                                        <?php } ?>
                                                                        <?php if ($user["profile"] == "Technicien") { ?>
                                                                        <option value="Manager">
                                                                            Manager
                                                                        </option>
                                                                        <option value="Manager & Technicien">
                                                                            Manager - <?php echo $technicien ?>
                                                                        </option>
                                                                        <?php } elseif ($user["profile"] == "Manager" && $user["test"] == true) { ?>
                                                                        <option value="Manager">
                                                                            Manager
                                                                        </option>
                                                                        <option value="Technicien">
                                                                            <?php echo $technicien ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <?php if ($_SESSION["profile"] == "Super Admin") { ?>
                                                                        <option value="Admin">
                                                                            <?php echo $admin ?>
                                                                        </option>
                                                                        <option value="Directeur Filiale">
                                                                            <?php echo $directeur_filiale ?>
                                                                        </option>
                                                                        <option value="Directeur Groupe">
                                                                            <?php echo $directeur_groupe ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <option value="Manager">
                                                                            Manager
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $certificat ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="certificate"
                                                                        value="<?php echo $user->certificate; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span
                                                                            class="required"><?php echo $filiale ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="<?php echo $select_subsidiary ?>">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="subsidiary"
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $user->subsidiary; ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <option value="<?php echo $user->subsidiary ?>">
                                                                            <?php echo $user->subsidiary ?></option>
                                                                        <option value="CAMEROON MOTORS INDUSTRIES">
                                                                            <?php echo $cami ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS BENIN">
                                                                            <?php echo $cfao_benin ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS BURKINA">
                                                                            <?php echo $cfao_burkina ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS CENTRAFRIQUE">
                                                                            <?php echo $cfao_centrafrique ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS CONGO">
                                                                            <?php echo $cfao_congo ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS COTE D'IVOIRE">
                                                                            <?php echo $cfao_cote_divoire ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS GABON">
                                                                            <?php echo $cfao_gabon ?>
                                                                        </option>
                                                                        <option value="CFAO (GAMBIA) LIMITED">
                                                                            <?php echo $cfao_gambia ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS GHANA">
                                                                            <?php echo $cfao_ghana ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS GUINEE">
                                                                            <?php echo $cfao_guinee ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS GUINEE BISSAU">
                                                                            <?php echo $cfao_guinee_bissau ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS GUINEA EQUATORIAL">
                                                                            <?php echo $cfao_guinee_equatorial ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS MADAGASCAR">
                                                                            <?php echo $cfao_madagascar ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS MALI">
                                                                            <?php echo $cfao_mali ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS NIGER">
                                                                            <?php echo $cfao_niger ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS NIGERIA">
                                                                            <?php echo $cfao_nigeria ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS RDC">
                                                                            <?php echo $cfao_rdc ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS SENEGAL">
                                                                            <?php echo $cfao_senegal ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS TCHAD">
                                                                            <?php echo $cfao_tchad ?>
                                                                        </option>
                                                                        <option value="CFAO MOTORS TOGO">
                                                                            <?php echo $cfao_togo ?>
                                                                        </option>
                                                                        <option
                                                                            value="COMPAGNIE MAURITANIENNE DE DISTRIBUTION AUTOMOBILE">
                                                                            <?php echo $cfao_cmda ?>
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
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $agence ?>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="agency"
                                                                        value="<?php echo $user->agency ?? ""?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $Department ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez le departement">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="department"
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $user->department; ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <option
                                                                            value="<?php echo $user->department; ?>">
                                                                            <?php echo $user->department; ?></option>
                                                                        <?php if ($user->department == "Motors") { ?>
                                                                        <option value="Equipment">
                                                                            Equipment
                                                                        </option>
                                                                        <option value="Equipment & Motors">
                                                                            Equipment & Motors
                                                                        </option>
                                                                        <?php } elseif ($user->department == "Equipment") { ?>
                                                                        <option value="Motors">
                                                                            Motors
                                                                        </option>
                                                                        <option value="Equipment & Motors">
                                                                            Equipment & Motors
                                                                        </option>
                                                                        <?php } elseif ($user->department == "Equipment & Motors") { ?>
                                                                        <option value="Equipment">
                                                                            Equipment
                                                                        </option>
                                                                        <option value="Motors">
                                                                            Motors
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Equipment">
                                                                            Equipment
                                                                        </option>
                                                                        <option value="Motors">
                                                                            Motors
                                                                        </option>
                                                                        <option value="Equipment & Motors">
                                                                            Equipment & Motors
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label class="fs-6 fw-bold mb-2"><?php echo $role ?>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="role"
                                                                        value="<?php echo $user->role; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <?php if ($user["profile"] == "Technicien") { ?>
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $brand ?>
                                                                            <?php echo $junior ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="brandJu[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_brand ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $userBrands = []; 
                                                                    foreach ($user['brandJunior'] as $bra) {
                                                                        array_push($userBrands, $bra);
                                                                    }
                                                                    if (in_array("FUSO", $userBrands)) { ?>
                                                                        <option value="FUSO" selected>
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="FUSO">
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("HINO", $userBrands)) { ?>
                                                                        <option value="HINO" selected>
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="HINO">
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("JCB", $userBrands)) { ?>
                                                                        <option value="JCB" selected>
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="JCB">
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("KING LONG", $userBrands)) { ?>
                                                                        <option value="KING LONG" selected>
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="KING LONG">
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("LOVOL", $userBrands)) { ?>
                                                                        <option value="LOVOL" selected>
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="LOVOL">
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES TRUCK", $userBrands)) { ?>
                                                                        <option value="MERCEDES TRUCK" selected>
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES TRUCK">
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("RENAULT TRUCK", $userBrands)) { ?>
                                                                        <option value="RENAULT TRUCK" selected>
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="RENAULT TRUCK">
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SINOTRUK", $userBrands)) { ?>
                                                                        <option value="SINOTRUK" selected>
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SINOTRUK">
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA BT", $userBrands)) { ?>
                                                                        <option value="TOYOTA BT" selected>
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA BT">
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA FORKLIFT", $userBrands)) { ?>
                                                                        <option value="TOYOTA FORKLIFT" selected>
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA FORKLIFT">
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("BYD", $userBrands)) { ?>
                                                                        <option value="BYD" selected>
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="BYD">
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("CITROEN", $userBrands)) { ?>
                                                                        <option value="CITROEN" selected>
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="CITROEN">
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES", $userBrands)) { ?>
                                                                        <option value="MERCEDES" selected>
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES">
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MiTSUBISHI", $userBrands)) { ?>
                                                                        <option value="MiTSUBISHI" selected>
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MiTSUBISHI">
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("PEUGEOT", $userBrands)) { ?>
                                                                        <option value="PEUGEOT" selected>
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="PEUGEOT">
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SUZUKI", $userBrands)) { ?>
                                                                        <option value="SUZUKI" selected>
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SUZUKI">
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA", $userBrands)) { ?>
                                                                        <option value="TOYOTA" selected>
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA">
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA BATEAU", $userBrands)) { ?>
                                                                        <option value="YAMAHA BATEAU" selected>
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA BATEAU">
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA MOTO", $userBrands)) { ?>
                                                                        <option value="YAMAHA MOTO" selected>
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA MOTO">
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $brand ?>
                                                                            <?php echo $senior ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="brandSe[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_brand ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $userBrands = []; 
                                                                    foreach ($user['brandSenior'] as $bra) {
                                                                        array_push($userBrands, $bra);
                                                                    }if (in_array("FUSO", $userBrands)) { ?>
                                                                        <option value="FUSO" selected>
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="FUSO">
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("HINO", $userBrands)) { ?>
                                                                        <option value="HINO" selected>
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="HINO">
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("JCB", $userBrands)) { ?>
                                                                        <option value="JCB" selected>
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="JCB">
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("KING LONG", $userBrands)) { ?>
                                                                        <option value="KING LONG" selected>
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="KING LONG">
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("LOVOL", $userBrands)) { ?>
                                                                        <option value="LOVOL" selected>
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="LOVOL">
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES TRUCK", $userBrands)) { ?>
                                                                        <option value="MERCEDES TRUCK" selected>
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES TRUCK">
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("RENAULT TRUCK", $userBrands)) { ?>
                                                                        <option value="RENAULT TRUCK" selected>
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="RENAULT TRUCK">
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SINOTRUK", $userBrands)) { ?>
                                                                        <option value="SINOTRUK" selected>
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SINOTRUK">
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA BT", $userBrands)) { ?>
                                                                        <option value="TOYOTA BT" selected>
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA BT">
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA FORKLIFT", $userBrands)) { ?>
                                                                        <option value="TOYOTA FORKLIFT" selected>
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA FORKLIFT">
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("BYD", $userBrands)) { ?>
                                                                        <option value="BYD" selected>
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="BYD">
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("CITROEN", $userBrands)) { ?>
                                                                        <option value="CITROEN" selected>
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="CITROEN">
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES", $userBrands)) { ?>
                                                                        <option value="MERCEDES" selected>
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES">
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MiTSUBISHI", $userBrands)) { ?>
                                                                        <option value="MiTSUBISHI" selected>
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MiTSUBISHI">
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("PEUGEOT", $userBrands)) { ?>
                                                                        <option value="PEUGEOT" selected>
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="PEUGEOT">
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SUZUKI", $userBrands)) { ?>
                                                                        <option value="SUZUKI" selected>
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SUZUKI">
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA", $userBrands)) { ?>
                                                                        <option value="TOYOTA" selected>
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA">
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA BATEAU", $userBrands)) { ?>
                                                                        <option value="YAMAHA BATEAU" selected>
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA BATEAU">
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA MOTO", $userBrands)) { ?>
                                                                        <option value="YAMAHA MOTO" selected>
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA MOTO">
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $brand ?>
                                                                            <?php echo $expert ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="brandEx[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_brand ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $userBrands = []; 
                                                                    foreach ($user['brandExpert'] as $bra) {
                                                                        array_push($userBrands, $bra);
                                                                    }
                                                                    if (in_array("FUSO", $userBrands)) { ?>
                                                                        <option value="FUSO" selected>
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="FUSO">
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("HINO", $userBrands)) { ?>
                                                                        <option value="HINO" selected>
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="HINO">
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("JCB", $userBrands)) { ?>
                                                                        <option value="JCB" selected>
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="JCB">
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("KING LONG", $userBrands)) { ?>
                                                                        <option value="KING LONG" selected>
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="KING LONG">
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("LOVOL", $userBrands)) { ?>
                                                                        <option value="LOVOL" selected>
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="LOVOL">
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES TRUCK", $userBrands)) { ?>
                                                                        <option value="MERCEDES TRUCK" selected>
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES TRUCK">
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("RENAULT TRUCK", $userBrands)) { ?>
                                                                        <option value="RENAULT TRUCK" selected>
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="RENAULT TRUCK">
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SINOTRUK", $userBrands)) { ?>
                                                                        <option value="SINOTRUK" selected>
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SINOTRUK">
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA BT", $userBrands)) { ?>
                                                                        <option value="TOYOTA BT" selected>
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA BT">
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA FORKLIFT", $userBrands)) { ?>
                                                                        <option value="TOYOTA FORKLIFT" selected>
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA FORKLIFT">
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("BYD", $userBrands)) { ?>
                                                                        <option value="BYD" selected>
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="BYD">
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("CITROEN", $userBrands)) { ?>
                                                                        <option value="CITROEN" selected>
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="CITROEN">
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES", $userBrands)) { ?>
                                                                        <option value="MERCEDES" selected>
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES">
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MiTSUBISHI", $userBrands)) { ?>
                                                                        <option value="MiTSUBISHI" selected>
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MiTSUBISHI">
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("PEUGEOT", $userBrands)) { ?>
                                                                        <option value="PEUGEOT" selected>
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="PEUGEOT">
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SUZUKI", $userBrands)) { ?>
                                                                        <option value="SUZUKI" selected>
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SUZUKI">
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA", $userBrands)) { ?>
                                                                        <option value="TOYOTA" selected>
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA">
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA BATEAU", $userBrands)) { ?>
                                                                        <option value="YAMAHA BATEAU" selected>
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA BATEAU">
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA MOTO", $userBrands)) { ?>
                                                                        <option value="YAMAHA MOTO" selected>
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA MOTO">
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $Speciality ?>
                                                                            <?php echo $senior ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="specialitySenior[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_speciality ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $speciality = []; 
                                                                    foreach ($user['specialitySenior'] as $special) {
                                                                        array_push($speciality, $special);
                                                                    }
                                                                     if (in_array("Electricté et Electronique", $speciality)) { ?>
                                                                        <option value="Electricté et Electronique"
                                                                            selected>
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Electricté et Electronique">
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Hydraulique", $speciality)) { ?>
                                                                        <option value="Hydraulique" selected>
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Hydraulique">
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Moteur", $speciality)) { ?>
                                                                        <option value="Moteur" selected>
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Moteur">
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Transmission", $speciality)) { ?>
                                                                        <option value="Transmission" selected>
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Transmission">
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $Speciality ?>
                                                                            <?php echo $expert ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="specialityExpert[]" multiple
                                                                        aria-label="Select a Speciality"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_speciality ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $speciality = []; 
                                                                    foreach ($user['specialityExpert'] as $special) {
                                                                        array_push($speciality, $special);
                                                                    }
                                                                     if (in_array("Electricté et Electronique", $speciality)) { ?>
                                                                        <option value="Electricté et Electronique"
                                                                            selected>
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Electricté et Electronique">
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Hydraulique", $speciality)) { ?>
                                                                        <option value="Hydraulique" selected>
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Hydraulique">
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Moteur", $speciality)) { ?>
                                                                        <option value="Moteur" selected>
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Moteur">
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Transmission", $speciality)) { ?>
                                                                        <option value="Transmission" selected>
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Transmission">
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <?php } ?>
                                                                <?php if ($user["profile"] == "Manager" && $user["test"] == true) { ?>
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $brand ?>
                                                                            <?php echo $junior ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="brandJu[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_brand ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $userBrands = []; 
                                                                    foreach ($user['brandJunior'] as $bra) {
                                                                        array_push($userBrands, $bra);
                                                                    }
                                                                    if (in_array("FUSO", $userBrands)) { ?>
                                                                        <option value="FUSO" selected>
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="FUSO">
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("HINO", $userBrands)) { ?>
                                                                        <option value="HINO" selected>
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="HINO">
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("JCB", $userBrands)) { ?>
                                                                        <option value="JCB" selected>
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="JCB">
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("KING LONG", $userBrands)) { ?>
                                                                        <option value="KING LONG" selected>
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="KING LONG">
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("LOVOL", $userBrands)) { ?>
                                                                        <option value="LOVOL" selected>
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="LOVOL">
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES TRUCK", $userBrands)) { ?>
                                                                        <option value="MERCEDES TRUCK" selected>
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES TRUCK">
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("RENAULT TRUCK", $userBrands)) { ?>
                                                                        <option value="RENAULT TRUCK" selected>
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="RENAULT TRUCK">
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SINOTRUK", $userBrands)) { ?>
                                                                        <option value="SINOTRUK" selected>
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SINOTRUK">
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA BT", $userBrands)) { ?>
                                                                        <option value="TOYOTA BT" selected>
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA BT">
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA FORKLIFT", $userBrands)) { ?>
                                                                        <option value="TOYOTA FORKLIFT" selected>
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA FORKLIFT">
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("BYD", $userBrands)) { ?>
                                                                        <option value="BYD" selected>
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="BYD">
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("CITROEN", $userBrands)) { ?>
                                                                        <option value="CITROEN" selected>
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="CITROEN">
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES", $userBrands)) { ?>
                                                                        <option value="MERCEDES" selected>
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES">
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MiTSUBISHI", $userBrands)) { ?>
                                                                        <option value="MiTSUBISHI" selected>
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MiTSUBISHI">
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("PEUGEOT", $userBrands)) { ?>
                                                                        <option value="PEUGEOT" selected>
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="PEUGEOT">
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SUZUKI", $userBrands)) { ?>
                                                                        <option value="SUZUKI" selected>
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SUZUKI">
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA", $userBrands)) { ?>
                                                                        <option value="TOYOTA" selected>
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA">
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA BATEAU", $userBrands)) { ?>
                                                                        <option value="YAMAHA BATEAU" selected>
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA BATEAU">
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA MOTO", $userBrands)) { ?>
                                                                        <option value="YAMAHA MOTO" selected>
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA MOTO">
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $brand ?>
                                                                            <?php echo $senior ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="brandSe[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_brand ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $userBrands = []; 
                                                                    foreach ($user['brandSenior'] as $bra) {
                                                                        array_push($userBrands, $bra);
                                                                    }
                                                                    if (in_array("FUSO", $userBrands)) { ?>
                                                                        <option value="FUSO" selected>
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="FUSO">
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("HINO", $userBrands)) { ?>
                                                                        <option value="HINO" selected>
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="HINO">
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("JCB", $userBrands)) { ?>
                                                                        <option value="JCB" selected>
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="JCB">
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("KING LONG", $userBrands)) { ?>
                                                                        <option value="KING LONG" selected>
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="KING LONG">
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("LOVOL", $userBrands)) { ?>
                                                                        <option value="LOVOL" selected>
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="LOVOL">
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES TRUCK", $userBrands)) { ?>
                                                                        <option value="MERCEDES TRUCK" selected>
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES TRUCK">
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("RENAULT TRUCK", $userBrands)) { ?>
                                                                        <option value="RENAULT TRUCK" selected>
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="RENAULT TRUCK">
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SINOTRUK", $userBrands)) { ?>
                                                                        <option value="SINOTRUK" selected>
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SINOTRUK">
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA BT", $userBrands)) { ?>
                                                                        <option value="TOYOTA BT" selected>
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA BT">
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA FORKLIFT", $userBrands)) { ?>
                                                                        <option value="TOYOTA FORKLIFT" selected>
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA FORKLIFT">
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("BYD", $userBrands)) { ?>
                                                                        <option value="BYD" selected>
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="BYD">
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("CITROEN", $userBrands)) { ?>
                                                                        <option value="CITROEN" selected>
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="CITROEN">
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES", $userBrands)) { ?>
                                                                        <option value="MERCEDES" selected>
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES">
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MiTSUBISHI", $userBrands)) { ?>
                                                                        <option value="MiTSUBISHI" selected>
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MiTSUBISHI">
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("PEUGEOT", $userBrands)) { ?>
                                                                        <option value="PEUGEOT" selected>
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="PEUGEOT">
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SUZUKI", $userBrands)) { ?>
                                                                        <option value="SUZUKI" selected>
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SUZUKI">
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA", $userBrands)) { ?>
                                                                        <option value="TOYOTA" selected>
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA">
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA BATEAU", $userBrands)) { ?>
                                                                        <option value="YAMAHA BATEAU" selected>
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA BATEAU">
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA MOTO", $userBrands)) { ?>
                                                                        <option value="YAMAHA MOTO" selected>
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA MOTO">
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $brand ?>
                                                                            <?php echo $expert ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="brandEx[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_brand ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $userBrands = []; 
                                                                    foreach ($user['brandExpert'] as $bra) {
                                                                        array_push($userBrands, $bra);
                                                                    }
                                                                    if (in_array("FUSO", $userBrands)) { ?>
                                                                        <option value="FUSO" selected>
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="FUSO">
                                                                            <?php echo $fuso ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("HINO", $userBrands)) { ?>
                                                                        <option value="HINO" selected>
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="HINO">
                                                                            <?php echo $hino ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("JCB", $userBrands)) { ?>
                                                                        <option value="JCB" selected>
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="JCB">
                                                                            <?php echo $jcb ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("KING LONG", $userBrands)) { ?>
                                                                        <option value="KING LONG" selected>
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="KING LONG">
                                                                            <?php echo $kingLong ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("LOVOL", $userBrands)) { ?>
                                                                        <option value="LOVOL" selected>
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="LOVOL">
                                                                            <?php echo $lovol ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES TRUCK", $userBrands)) { ?>
                                                                        <option value="MERCEDES TRUCK" selected>
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES TRUCK">
                                                                            <?php echo $mercedesTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("RENAULT TRUCK", $userBrands)) { ?>
                                                                        <option value="RENAULT TRUCK" selected>
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="RENAULT TRUCK">
                                                                            <?php echo $renaultTruck ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SINOTRUK", $userBrands)) { ?>
                                                                        <option value="SINOTRUK" selected>
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SINOTRUK">
                                                                            <?php echo $sinotruk ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA BT", $userBrands)) { ?>
                                                                        <option value="TOYOTA BT" selected>
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA BT">
                                                                            <?php echo $toyotaBt ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA FORKLIFT", $userBrands)) { ?>
                                                                        <option value="TOYOTA FORKLIFT" selected>
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA FORKLIFT">
                                                                            <?php echo $toyotaForklift ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("BYD", $userBrands)) { ?>
                                                                        <option value="BYD" selected>
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="BYD">
                                                                            <?php echo $byd ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("CITROEN", $userBrands)) { ?>
                                                                        <option value="CITROEN" selected>
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="CITROEN">
                                                                            <?php echo $citroen ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MERCEDES", $userBrands)) { ?>
                                                                        <option value="MERCEDES" selected>
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MERCEDES">
                                                                            <?php echo $mercedes ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("MiTSUBISHI", $userBrands)) { ?>
                                                                        <option value="MiTSUBISHI" selected>
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="MiTSUBISHI">
                                                                            <?php echo $mitsubishi ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("PEUGEOT", $userBrands)) { ?>
                                                                        <option value="PEUGEOT" selected>
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="PEUGEOT">
                                                                            <?php echo $peugeot ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("SUZUKI", $userBrands)) { ?>
                                                                        <option value="SUZUKI" selected>
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="SUZUKI">
                                                                            <?php echo $suzuki ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("TOYOTA", $userBrands)) { ?>
                                                                        <option value="TOYOTA" selected>
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="TOYOTA">
                                                                            <?php echo $toyota ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA BATEAU", $userBrands)) { ?>
                                                                        <option value="YAMAHA BATEAU" selected>
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA BATEAU">
                                                                            <?php echo $yamahaBateau ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("YAMAHA MOTO", $userBrands)) { ?>
                                                                        <option value="YAMAHA MOTO" selected>
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="YAMAHA MOTO">
                                                                            <?php echo $yamahaMoto ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $Speciality ?>
                                                                            <?php echo $senior ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="specialitySenior[]" multiple
                                                                        aria-label="Select a Country"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_speciality ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $speciality = []; 
                                                                    foreach ($user['specialitySenior'] as $special) {
                                                                        array_push($speciality, $special);
                                                                    }
                                                                     if (in_array("Electricté et Electronique", $speciality)) { ?>
                                                                        <option value="Electricté et Electronique"
                                                                            selected>
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Electricté et Electronique">
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Hydraulique", $speciality)) { ?>
                                                                        <option value="Hydraulique" selected>
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Hydraulique">
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Moteur", $speciality)) { ?>
                                                                        <option value="Moteur" selected>
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Moteur">
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Transmission", $speciality)) { ?>
                                                                        <option value="Transmission" selected>
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Transmission">
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <!--begin::Input group-->
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $Speciality ?>
                                                                            <?php echo $expert ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez les marques">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="specialityExpert[]" multiple
                                                                        aria-label="Select a Speciality"
                                                                        data-control="select2"
                                                                        data-placeholder="<?php echo $select_speciality ?>"
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php $speciality = []; 
                                                                    foreach ($user['specialityExpert'] as $special) {
                                                                        array_push($speciality, $special);
                                                                    }
                                                                     if (in_array("Electricté et Electronique", $speciality)) { ?>
                                                                        <option value="Electricté et Electronique"
                                                                            selected>
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Electricté et Electronique">
                                                                            <?php echo $elec ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Hydraulique", $speciality)) { ?>
                                                                        <option value="Hydraulique" selected>
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Hydraulique">
                                                                            <?php echo $hydraulique ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Moteur", $speciality)) { ?>
                                                                        <option value="Moteur" selected>
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Moteur">
                                                                            <?php echo $moteur ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php if (in_array("Transmission", $speciality)) { ?>
                                                                        <option value="Transmission" selected>
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } else { ?>
                                                                        <option value="Transmission">
                                                                            <?php echo $transmission ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--end::Input group-->
                                                                <?php } ?>
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $recrutmentDate ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="text"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="recrutmentDate"
                                                                        value="<?php echo $user->recrutmentDate; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <!--begin::Input group-->
                                                                <div class="fv-row mb-7">
                                                                    <!--begin::Label-->
                                                                    <label
                                                                        class="fs-6 fw-bold mb-2"><?php echo $Password ?></label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <input type="password"
                                                                        class="form-control form-control-solid"
                                                                        placeholder="" name="password"
                                                                        value="<?php echo $user->visiblePassword; ?>" />
                                                                    <!--end::Input-->
                                                                </div>
                                                                <?php if ($user->profile == "Technicien") { ?>
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $userManager ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez le manager de ce technicien">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <select name="manager" aria-label="Select a Country"
                                                                        data-control="select2" data-placeholder=""
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <?php if ($user->manager) {
                                                                        $lead = $users->findOne([
                                                                            "_id" => $user["manager"],
                                                                        ]); ?>
                                                                        <option value="<?php echo $lead->_id; ?>">
                                                                            <?php echo $lead->firstName; ?>
                                                                            <?php echo $lead->lastName; ?>
                                                                        </option>
                                                                        <?php
                                                                    } ?>
                                                                        <?php
                                                                    if ($_SESSION['profile'] == "Admin") {
                                                                        $managers = $users->find([
                                                                            '$and' => [
                                                                                ["profile" => "Manager"],
                                                                                ["subsidiary" => $_SESSION['subsidiary']],
                                                                                ["active" => true],
                                                                            ],
                                                                        ]);
                                                                    }
                                                                    if ($_SESSION['profile'] == "Super Admin") {
                                                                        $managers = $users->find([
                                                                            '$and' => [
                                                                                ["profile" => "Manager"],
                                                                                ["active" => true],
                                                                            ],
                                                                        ]);
                                                                    }
                                                                    foreach ($managers as $manager) { ?>
                                                                        <option value="<?php echo $manager->_id; ?>">
                                                                            <?php echo $manager->firstName; ?>
                                                                            <?php echo $manager->lastName; ?>
                                                                        </option>
                                                                        <?php }
                                                                      ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <?php } ?>
                                                                <?php if ($user->profile == "Manager" && $user->test == true) { ?>
                                                                <div class="d-flex flex-column mb-7 fv-row">
                                                                    <!--begin::Label-->
                                                                    <label class="form-label fw-bolder text-dark fs-6">
                                                                        <span><?php echo $userManager ?></span>
                                                                        <span class="ms-1" data-bs-toggle="tooltip"
                                                                            title="Choississez le manager de ce technicien">
                                                                            <i class="ki-duotone ki-information fs-7"><span
                                                                                    class="path1"></span><span
                                                                                    class="path2"></span><span
                                                                                    class="path3"></span></i>
                                                                        </span>
                                                                    </label>
                                                                    <!--end::Label-->
                                                                    <!--begin::Input-->
                                                                    <?php if (isset($user->manager)) {
                                                                        $lead = $users->findOne([
                                                                            "_id" => $user["manager"],
                                                                        ]); ?>
                                                                    <select name="manager" aria-label="Select a Country"
                                                                        data-control="select2" data-placeholder=""
                                                                        class="form-select form-select-solid fw-bold">
                                                                        <option value="<?php echo $lead->_id ?>">
                                                                            <?php echo $lead->firstName.' '.$lead->lastName; ?>
                                                                        </option>
                                                                        <?php } ?>
                                                                        <?php
                                                                    if ($_SESSION['profile'] == "Admin") {
                                                                        $managers = $users->find([
                                                                            '$and' => [
                                                                                ["profile" => "Manager"],
                                                                                ["subsidiary" => $_SESSION['subsidiary']],
                                                                                ["active" => true],
                                                                            ],
                                                                        ]);
                                                                    }
                                                                    if ($_SESSION['profile'] == "Super Admin") {
                                                                        $managers = $users->find([
                                                                            '$and' => [
                                                                                ["profile" => "Manager"],
                                                                                ["active" => true],
                                                                            ],
                                                                        ]);
                                                                    }
                                                                    foreach ($managers as $manager) { ?>
                                                                        <option value="<?php echo $manager->_id; ?>">
                                                                            <?php echo $manager->firstName; ?>
                                                                            <?php echo $manager->lastName; ?>
                                                                        </option>
                                                                        <?php }
                                                                    ?>
                                                                    </select>
                                                                    <!--end::Input-->
                                                                </div>
                                                                <?php } ?>
                                                                <!--end::Input group-->
                                                            </div>
                                                            <!--end::User form-->
                                                        </div>
                                                        <!--end::Scroll-->
                                                    </div>
                                                    <!--end::Modal body-->
                                                    <!--begin::Modal footer-->
                                                    <div class="modal-footer flex-center">
                                                        <!--begin::Button-->
                                                        <button type="reset" class="btn btn-light me-3"
                                                            data-kt-menu-dismiss="true" data-bs-dismiss="modal"
                                                            data-kt-users-modal-action="cancel"><?php echo $annuler ?></button>
                                                        <!--end::Button-->
                                                        <!--begin::Button-->
                                                        <button type="submit" name="update" class="btn btn-primary">
                                                            <?php echo $valider ?>
                                                        </button>
                                                        <!--end::Button-->
                                                    </div>
                                                    <!--end::Modal footer-->
                                                </form>
                                                <!--end::Form-->
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Modal - Update user details-->
                                    <!--begin::Modal - Invite Friends-->
                                    <div class="modal fade" id="kt_modal_invite_users<?php echo $user->_id; ?>"
                                        tabindex="-1" aria-hidden="true">
                                        <!--begin::Modal dialog-->
                                        <div class="modal-dialog mw-650px">
                                            <!--begin::Modal content-->
                                            <div class="modal-content">
                                                <!--begin::Modal header-->
                                                <div class="modal-header pb-0 border-0 justify-content-end">
                                                    <!--begin::Close-->
                                                    <div class="btn btn-sm btn-icon btn-active-color-primary"
                                                        data-bs-dismiss="modal">
                                                        <i class="ki-duotone ki-cross fs-1"><span
                                                                class="path1"></span><span class="path2"></span></i>
                                                    </div>
                                                    <!--end::Close-->
                                                </div>
                                                <!--begin::Modal header-->
                                                <!--begin::Modal body-->
                                                <div class="modal-body scroll-y mx-5 mx-xl-18 pt-0 pb-15">
                                                    <!--begin::Heading-->
                                                    <div class="text-center mb-13">
                                                        <!--begin::Title-->
                                                        <h1 class="mb-3">
                                                            <?php echo $list_tech ?>
                                                        </h1>
                                                        <!--end::Title-->
                                                    </div>
                                                    <!--end::Heading-->
                                                    <!--begin::Users-->
                                                    <div class="mb-10">
                                                        <!--begin::List-->
                                                        <div class="mh-300px scroll-y me-n7 pe-7">
                                                            <!--begin::User-->
                                                            <?php
                                                            $technicians = $users->find(
                                                                [
                                                                    "_id" => [
                                                                        '$in' =>
                                                                            $user[
                                                                                "users"
                                                                            ],
                                                                    ],
                                                                ]
                                                            );
                                                            foreach (
                                                                $technicians
                                                                as $technician
                                                            ) { ?>
                                                            <div
                                                                class="d-flex flex-stack py-4 border-bottom border-gray-300 border-bottom-dashed">
                                                                <!--begin::Details-->
                                                                <div class="d-flex align-items-center">
                                                                    <!--begin::Avatar-->
                                                                    <div class="symbol symbol-35px symbol-circle">
                                                                        <img alt="Pic"
                                                                            src="../../public/assets/media/avatars/300-1.jpg" />
                                                                    </div>
                                                                    <!--end::Avatar -->
                                                                    <!--begin::Details-->
                                                                    <div class="ms-5">
                                                                        <a href="#"
                                                                            class="fs-5 fw-bold text-gray-900 text-hover-primary mb-2">
                                                                            <?php echo $technician->firstName; ?>
                                                                            <?php echo $technician->lastName; ?>
                                                                        </a>
                                                                        <div class="fw-semibold text-muted">
                                                                            <?php echo $technician->email; ?>
                                                                        </div>
                                                                    </div>
                                                                    <!--end::Details-->
                                                                </div>
                                                                <!--end::Details-->
                                                                <!--begin::Access menu-->
                                                                <!-- <div data-kt-menu-trigger="click">
                                                                    <form method="POST">
                                                                        <input type="hidden" name="userID"
                                                                            value="<?php echo $technician->_id; ?>">
                                                                        <button
                                                                            class="btn btn-light btn-active-light-primary btn-sm"
                                                                            type="submit"
                                                                            name="retire-technician-manager">Supprimer</button>
                                                                    </form>
                                                                </div> -->
                                                                <!--end::Access menu-->
                                                            </div>
                                                            <!--end::User-->
                                                            <?php }
                                                            ?>
                                                        </div>
                                                        <!--end::List-->
                                                    </div>
                                                    <!--end::Users-->
                                                </div>
                                                <!--end::Modal body-->
                                            </div>
                                            <!--end::Modal content-->
                                        </div>
                                        <!--end::Modal dialog-->
                                    </div>
                                    <!--end::Modal - Invite Friend-->
                                    <?php }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div
                                class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                <div class="dataTables_length">
                                    <label><select id="kt_customers_table_length" name="kt_customers_table_length"
                                            class="form-select form-select-sm form-select-solid">
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="300">300</option>
                                            <option value="500">500</option>
                                        </select></label>
                                </div>
                            </div>
                            <div
                                class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
                                <div class="dataTables_paginate paging_simple_numbers">
                                    <ul class="pagination" id="kt_customers_table_paginate">
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Table-->
                </div>
                <!--end::Card body-->
            </div>
            <!--end::Card-->
            <!--begin::Export dropdown-->
            <!-- <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
            <form method="post">
                <button type="submit" name="excel" title="Cliquez ici pour importer la table" class="btn btn-primary">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    Excel
                </button>
            </form>
            </div> -->
            <!--end::Export dropdown-->
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