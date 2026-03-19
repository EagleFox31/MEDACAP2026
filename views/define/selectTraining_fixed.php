<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {
    require_once "../../vendor/autoload.php";
    require "../sendMail.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");
    // Connecting in database
    $academy = $conn->academy; 
    // Connecting in collections
    $users = $academy->users;
    $trainings = $academy->trainings;
    $allocations = $academy->allocations;
    $applications = $academy->applications;
    $validations = $academy->validations;

    // Fix: Add null coalescing operators to provide default values
    $levelFilter = $_GET["level"] ?? 'all';
    $techFilter = $_GET['user'] ?? 'all';
    $brandFilter = $_GET['brand'] ?? 'all';
    $trainingFilter = $_GET['training'] ?? 'all';

    if ($_SESSION['profile'] == 'Manager') {
        $filter = [
            'manager' => new MongoDB\BSON\ObjectId($_SESSION["id"]),
            'profile' => 'Technicien',
            'active' => true
        ];
    } else {
        $filter = [
            'subsidiary' => $_SESSION['subsidiary'],
            'profile' => 'Technicien',
            'active' => true
        ];
    }

    if($brandFilter != 'all') {
        $filter['brandJunior'] = $brandFilter;
    }
    
    $technicians = $users->find($filter)->toArray();

    $levelTechs = [];
    $technicianIds = [];
    foreach ($technicians as $technician) {
        $levelTechs[] = $technician['level'];
        $technicianIds[] = (string)$technician['_id'];
    }
    
    // Supprimer les doublons
    $levelTechs = array_unique($levelTechs);
    
    // ----------------------------------------------------------
    // 5) Extraire la liste des marques (teamBrands)
    // ----------------------------------------------------------
   
    $teamBrands = [];
    
    // Initialiser le tableau de filtres
    $filter = ['active' => true];

    foreach ($technicians as $t) {
        if ($levelFilter === 'all') {
            $levelsToConsider = ['Junior', 'Senior', 'Expert'];
        } else {
            $levelsToConsider = [$levelFilter];
        }
        $brandFields = [];
        foreach ($levelsToConsider as $level) {
            $brandField = 'brand'. ucfirst(strtolower($level));
            foreach ($t[$brandField] as $brand) {
                $brandFields[] = $brand;
            }
            if (!empty($brandFields) && is_array($brandFields)) {
                foreach ($brandFields as $b) {
                    $bTrim = trim($b);
                    if ($bTrim !== '' && !in_array($bTrim, $teamBrands)) {
                        $teamBrands[] = $bTrim;
                    }
                }
            }
        }
    }
    sort($teamBrands);

    $dataTrainings = [];
    $trainingSelected = [];
    $trainingTechSelected = [];
    if ($techFilter == 'all' && $levelFilter == 'all' && $brandFilter == 'all' && $trainingFilter == 'all') {
        foreach ($technicians as $technician) {
            $trainingData = $trainings->find([
                'users' => new MongoDB\BSON\ObjectId($technician['_id']),
                'active' => true
            ])->toArray();
            foreach ($trainingData as $train) {
                $dataTrainings[] = $train['_id'];
            }
            
            $techTrainingDatas = $validations->find([
                'user' => new MongoDB\BSON\ObjectId($technician['_id']),
                'status' => 'Validé',
                'active' => true
            ])->toArray();
            foreach ($techTrainingDatas as $techTrainingData) {
                if (isset($techTrainingData)) {
                    $trainingSelected[] = $techTrainingData['training'];
                    $trainingTechSelected[] = $techTrainingData['user'];
                }
            }
        }
    } else if ($techFilter == 'all') {
        // Ajouter des filtres en fonction des valeurs
        if ($levelFilter != 'all') {
            $filter['level'] = $levelFilter; // Ajouter le filtre de niveau
        }

        if ($brandFilter != 'all') {
            $filter['brand'] = $brandFilter; // Ajouter le filtre de marque
        }

        if ($trainingFilter != 'all') {
            $filter['_id'] = new MongoDB\BSON\ObjectId($trainingFilter); // Ajouter le filtre de foramtion
        }
        
        foreach ($technicians as $technician) {
            $filter['users'] = new MongoDB\BSON\ObjectId($technician['_id']); // Ajouter le filtre du technicien
            $trainingData = $trainings->find($filter)->toArray();
            
            foreach ($trainingData as $train) {
                $dataTrainings[] = $train['_id'];
            }
        
            $techTrainingDatas = $validations->find([
                'user' => new MongoDB\BSON\ObjectId($technician['_id']),
                'status' => 'Validé',
                'active' => true
            ])->toArray();
            foreach ($techTrainingDatas as $techTrainingData) {
                if (isset($techTrainingData)) {
                    $trainingSelected[] = $techTrainingData['training'];
                    $trainingTechSelected[] = $techTrainingData['user'];
                }
            }
        }
    } else if ($techFilter != 'all') {
        // Ajouter des filtres en fonction des valeurs
        $filter['users'] = new MongoDB\BSON\ObjectId($techFilter); // Ajouter le filtre du technicien

        if ($levelFilter != 'all') {
            $filter['level'] = $levelFilter; // Ajouter le filtre de niveau
        }

        if ($brandFilter != 'all') {
            $filter['brand'] = $brandFilter; // Ajouter le filtre de marque
        }

        if ($trainingFilter != 'all') {
            $filter['_id'] = new MongoDB\BSON\ObjectId($trainingFilter); // Ajouter le filtre de foramtion
        }

        // Si un filtre technique est spécifié, récupérer les formations pour ce technicien uniquement
        $trainingData = $trainings->find($filter)->toArray();
        foreach ($trainingData as $train) {
            $dataTrainings[] = $train['_id'];
        }
        
        $techTrainingDatas = $validations->find([
            'user' => new MongoDB\BSON\ObjectId($techFilter),
            'status' => 'Validé',
            'active' => true
        ])->toArray();
        foreach ($techTrainingDatas as $techTrainingData) {
            if (isset($techTrainingData)) {
                $trainingSelected[] = $techTrainingData['training'];
                $trainingTechSelected[] = $techTrainingData['user'];
            }
        }
    }

    // Convertir le tableau associatif en tableau indexé
    $dataTrainings = array_unique($dataTrainings);
    $trainingSelected = array_unique($trainingSelected);
    
    // Réindexer le tableau pour que les clés soient consécutives
    $trainingSelected = array_values($trainingSelected);

    $trainingDtas = count($dataTrainings);

    $trainingDatas = [];

    foreach ($dataTrainings as $index => $trainingData) {
        $training = $trainings->findOne([
            '_id' => new MongoDB\BSON\ObjectId($trainingData),
            'active' => true
        ]);

        $trainingDatas[] = $training;
    }
    
    // Construire le pipeline conditionnellement
    $pipeline2 = [];

    $pipeline2[] = [
        '$match' => [
            'profile'    => 'Manager',
            'subsidiary' => $_SESSION['subsidiary']
        ]
    ];
    // Ajouter le lookup sur les subordinates
    $pipeline2[] = [
        '$lookup' => [
            'from' => 'users',
            'localField' => 'users',
            'foreignField' => '_id',
            'as' => 'subordinates'
        ]
    ];

    // Unwind les subordinates
    $pipeline2[] = [
        '$unwind' => '$subordinates'
    ];

    // Match seulement les techniciens
    $pipeline2[] = [
        '$match' => [
            'subordinates.profile' => 'Technicien'
        ]
    ];

    // Ajouter un match sur technicianId si nécessaire
    if ($techFilter !== 'all') {
        try {
            $filterTechnicianId = new MongoDB\BSON\ObjectId($techFilter);
            $pipeline2[] = [
                '$match' => [
                    'subordinates._id' => $filterTechnicianId
                ]
            ];
        } catch (MongoDB\Driver\Exception\InvalidArgumentException $e) {
            echo "technicianId invalide: " . htmlspecialchars($e->getMessage());
            exit();
        }
    }

    // Lookup sur les trainings
    $pipeline2[] = [
        '$lookup' => [
            'from' => 'trainings',
            'localField' => 'subordinates._id',
            'foreignField' => 'users',
            'as' => 'trainings'
        ]
    ];

    // Unwind les trainings
    $pipeline2[] = [
        '$unwind' => '$trainings'
    ];

    if ($brandFilter !== 'all') {
        $pipeline2[] = [
            '$match' => [
                'trainings.brand' => $brandFilter
            ]
        ];
    }
    // Filtrer par level si nécessaire
    if ($levelFilter !== 'all') {
        $pipeline2[] = [
            '$match' => [
                'trainings.level' => $levelFilter
            ]
        ];
    }

    // Juste avant le $facet :
    $brandAndLevelPipeline = [];

    if ($techFilter === 'all') {
        // CAS DISTINCT : group par trainingId
        $brandAndLevelPipeline = [
            // 1er group => group par brand/level/trainingId
            [
                '$group' => [
                    '_id' => [
                        'brand'      => '$trainings.brand',
                        'level'      => '$trainings.level',
                        'trainingId' => '$trainings._id'
                    ],
                    'count' => ['$sum' => 1]
                ]
            ],
            // 2e group => brand + level => on additionne
            [
                '$group' => [
                    '_id' => [
                        'brand' => '$_id.brand',
                        'level' => '$_id.level'
                    ],
                    'count' => ['$sum' => 1]
                ]
            ],
            // 3e group final
            [
                '$group' => [
                    '_id' => '$_id.brand',
                    'totalByBrand' => ['$sum' => '$count'],
                    'totalByLevel' => [
                        '$push' => [
                            'k' => '$_id.level',
                            'v' => '$count'
                        ]
                    ]
                ]
            ],
            [
                '$addFields' => [
                    'totalByLevel' => ['$arrayToObject' => '$totalByLevel']
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'totalTrainingsByBrandAndLevel' => [
                        '$push' => [
                            'k' => '$_id',
                            'v' => [
                                'totalByBrand' => '$totalByBrand',
                                'totalByLevel' => '$totalByLevel'
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$addFields' => [
                    'totalTrainingsByBrandAndLevel' => [
                        '$arrayToObject' => '$totalTrainingsByBrandAndLevel'
                    ]
                ]
            ],
            [
                '$project' => [
                    '_id' => 0,
                    'totalTrainingsByBrandAndLevel' => 1
                ]
            ]
        ];
    } else {
        // CAS NON-DISTINCT : group par brand/level, on compte TOUT
        $brandAndLevelPipeline = [
            // group par brand + level SANS trainingId
            [
                '$group' => [
                    '_id' => [
                        'brand'      => '$trainings.brand',
                        'level'      => '$trainings.level',
                        'trainingId' => '$trainings._id'
                    ],
                    'count' => ['$sum' => 1]
                ]
            ],
            // on n'a plus besoin du 1er regroupement distinct
            [
                '$group' => [
                    '_id' => '$_id.brand',
                    'totalByBrand' => ['$sum' => '$count'],
                    'totalByLevel' => [
                        '$push' => [
                            'k' => '$_id.level',
                            'v' => '$count'
                        ]
                    ]
                ]
            ],
            [
                '$addFields' => [
                    'totalByLevel' => ['$arrayToObject' => '$totalByLevel']
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'totalTrainingsByBrandAndLevel' => [
                        '$push' => [
                            'k' => '$_id',
                            'v' => [
                                'totalByBrand' => '$totalByBrand',
                                'totalByLevel' => '$totalByLevel'
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$addFields' => [
                    'totalTrainingsByBrandAndLevel' => [
                        '$arrayToObject' => '$totalTrainingsByBrandAndLevel'
                    ]
                ]
            ],
            [
                '$project' => [
                    '_id' => 0,
                    'totalTrainingsByBrandAndLevel' => 1
                ]
            ]
        ];
    }

    $pipeline2[] = [
        '$facet' => [
            'brandAndLevel' => $brandAndLevelPipeline,
            'totalOccurrences' => [
                [
                    '$group' => [
                        '_id' => null,
                        'totalOccurrences' => ['$sum' => 1]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 0,
                        'totalOccurrences' => 1
                    ]
                ]
            ]
        ]
    ];

    // Exécuter le pipeline
    try {
        $result2 = $users->aggregate($pipeline2)->toArray();
        $document2 = $result2[0] ?? null;

        if ($document2) {
            // Aucune donnée trouvée
            $brandAndLevelArr = $document2['brandAndLevel'][0] ?? null;
            if ($brandAndLevelArr) {
                $trainingsByBrandAndLevel = $brandAndLevelArr['totalTrainingsByBrandAndLevel'] ?? [];
            } else {
                $trainingsByBrandAndLevel = [];
            }
            // Récupération de la partie totalOccurrences
            $occurrencesArr = $document2['totalOccurrences'][0] ?? [];
            $totalOccurrences = $occurrencesArr['totalOccurrences'] ?? 0;
        } else {
            // Pas de résultat => vides
            $trainingsByBrandAndLevel = [];
            $totalOccurrences = 0;
        }
        //     $trainingsByBrandAndLevel = [];
        // } else {
        //     $trainingsByBrandAndLevel = $document2['totalTrainingsByBrandAndLevel'] ?? [];
        // }
    } catch (MongoDB\Exception\Exception $e) {
        echo "Erreur lors du pipeline2 : " . htmlspecialchars($e->getMessage());
        exit();
    }
    
    $numDays = $totalOccurrences * 5;
    
    // Sauvegarde des formations avec un status et une date de validation
    if (isset($_POST['selectDates'])) {
        $userIds = $_POST["userIds"] ?? [];
        $trainingId = $_POST["trainingId"] ?? '';
        $selectedDate = $_POST["date"] ?? '';
        $currentYear = $_POST["currentYear"] ?? '';
        $place = $_POST["place"] ?? '';
        $currentUser = $_SESSION['id'];
        
        function sendTrainingMail($training, $selectedDate, $place, $technicians, $manager, $trainingTechSelected, $trainingName, $status) {
            // Assurez-vous que les variables sont définies et valides
            if (isset($manager, $training) && count($technicians) != 0) {
                // Échapper les données pour éviter les problèmes de sécurité
                $managerLastName = htmlspecialchars($manager['lastName']);
                $managerFirstName = htmlspecialchars($manager['firstName']);
                // Utilisation de implode pour joindre les éléments avec un tiret et un retour à la ligne
                $technicianName = implode('<br>- ', $technicians);
                $technicianName = '- ' . $technicianName; // Ajoute un tiret au début
                $trainingLabel = htmlspecialchars($training['label']);
                $trainingLevel = htmlspecialchars($training['level']);
                $trainingPlace = htmlspecialchars($place);

                // Créer le message
                $message = '<p>Bonjour,</p><p>Nous avez reçu une confirmation de pré-inscription de <strong>' . $managerFirstName . ' ' . $managerLastName . '</strong> 
                    pour le(s) collaborateur(s): <br><strong>' . $technicianName . '</strong></p>
                    <p>A la formation <strong>' . $trainingLabel . '</strong>,
                        niveau <strong>' . $trainingLevel . '</strong> pour la période du 
                    <strong>' . $selectedDate . '</strong> à <strong>' . $trainingPlace . '</strong>.</p>
                    <p style="margin-top: 50px; font-size: 20px; font-weight: 100px">Cordialement | Best Regards | よろしくお願いしま。</p>';

                // Sujet de l'e-mail
                $subject = 'Confirmation de la pré-inscription de ' . $managerFirstName . ' ' . $managerLastName;
                // Envoyer l'e-mail
                sendMailSelectDone($subject, $message);
                if ($status == 'Validé') {
                    $response = [
                        'success' => true,
                        'training' => 0,
                        'status' => 'Validé',
                        'technicians' => 0,
                        'message' => 'Formation  « ' . $trainingName . ' » pré-inscrite avec succès.'
                    ];
                    echo json_encode($response);
                    exit(); // Terminer le script après avoir envoyé la réponse
                } else {
                    $response = [
                        'success' => true,
                        'training' => 1,
                        'status' => 'Validé',
                        'technicians' => count($trainingTechSelected),
                        'message' => 'Formation  « ' . $trainingName . ' » pré-inscrite avec succès.'
                    ];
                    echo json_encode($response);
                    exit(); // Terminer le script après avoir envoyé la réponse
                }
            } else {
                if ($status == 'Validé') {
                    $response = [
                        'success' => true,
                        'training' => 0,
                        'status' => 'Validé',
                        'technicians' => 0,
                        'message' => 'Formation  « ' . $trainingName . ' » pré-inscrite avec succès.'
                    ];
                    echo json_encode($response);
                    exit(); // Terminer le script après avoir envoyé la réponse
                } else {
                    $response = [
                        'success' => true,
                        'training' => 1,
                        'status' => 'Validé',
                        'technicians' => count($trainingTechSelected),
                        'message' => 'Formation  « ' . $trainingName . ' » pré-inscrite avec succès.'
                    ];
                    echo json_encode($response);
                    exit(); // Terminer le script après avoir envoyé la réponse
                }
            }
        }
        
        $training = $trainings->findOne(['_id' => new MongoDB\BSON\ObjectId($trainingId)]);
        $trainingName = $training['label'];
        $trainingTechSelected = [];
        $trainingSelected = [];
        $technicians = [];
        $status = [];
        foreach ($userIds as $userId) {
            $exist = $applications->findOne([
                'user' => new MongoDB\BSON\ObjectId($currentUser),
                'period' => $selectedDate // Nouvelle valeur pour le champ 'period'
            ]);

            $applicationTechs = $allocations->findOne([
                '$and' => [
                    [
                        "user" => new MongoDB\BSON\ObjectId($userId), // ID de l'utilisateur
                        "training" => new MongoDB\BSON\ObjectId($trainingId), // ID de la formation
                        "type" => "Training"
                    ]
                ],
            ]);

            if (isset($exist)) {
                $response = [
                    'success' => false,
                    'message' => 'Vous avez déjà sélectionnée cette période pour une autre formation. Veuillez sélectionner une autre période.'
                ];
                echo json_encode($response);
                exit(); // Terminer le script après avoir envoyé la réponse
            } else if (isset($applicationTechs)) {
                $app = $applications->findOne([
                    "user" => new MongoDB\BSON\ObjectId($applicationTechs['user']), // ID de l'utilisateur
                    "training" => new MongoDB\BSON\ObjectId($trainingId), // ID de la formation
                    "active" => false
                ]); 

                $validated = $validations->findOne([
                    "user" => new MongoDB\BSON\ObjectId($applicationTechs['user']), // ID de l'utilisateur
                    "training" => new MongoDB\BSON\ObjectId($trainingId), // ID de la formation
                    "active" => false
                ]);
                
                if (isset($validated)) {
                    $status[] = $validated['status'];
                    $validations->updateOne(
                        [
                            "user" => new MongoDB\BSON\ObjectId($userId), // ID de l'utilisateur
                            "training" => new MongoDB\BSON\ObjectId($trainingId) // ID de la formation
                        ],[
                            '$set' => [
                                'status' => 'Validé', // Nouvelle valeur pour le champ 'year'
                                'updated' => date("d-m-Y H:i:s") // Exemple d'ajout d'un champ de date
                            ]
                        ]                        
                    );
                } else {
                    $validates = [
                        'user' => new MongoDB\BSON\ObjectId($applicationTechs['user']),
                        'training' => new MongoDB\BSON\ObjectId($trainingId),
                        'manager' => new MongoDB\BSON\ObjectId($currentUser),
                        'status' => 'Validé',
                        'active' => true,
                        'created' => date("d-m-Y H:i:s")
                    ];
                    $validations->insertOne($validates);
                }                

                if ($app) {
                    $applications->updateOne(
                        [
                            "user" => new MongoDB\BSON\ObjectId($userId), // ID de l'utilisateur
                            "training" => new MongoDB\BSON\ObjectId($trainingId) // ID de la formation
                        ],[
                            '$set' => [
                                'period' => $selectedDate, // Nouvelle valeur pour le champ 'period'
                                'year' => $currentYear, // Nouvelle valeur pour le champ 'year'
                                'status' => 'En attente', // Nouvelle valeur pour le champ 'year'
                                'active' => true, // Nouvelle valeur pour le champ 'active'
                                'updated' => date("d-m-Y H:i:s") // Exemple d'ajout d'un champ de date
                            ]
                        ]                        
                    );
                } else {
                    $applicates = [
                        'user' => new MongoDB\BSON\ObjectId($applicationTechs['user']),
                        'training' => new MongoDB\BSON\ObjectId($trainingId),
                        'manager' => new MongoDB\BSON\ObjectId($currentUser),
                        'place' => $place,
                        'period' => $selectedDate,
                        'year' => $currentYear,
                        'status' => 'En attente',
                        'active' => true,
                        'created' => date("d-m-Y H:i:s")
                    ];
                    $applications->insertOne($applicates);
                }
                
                $technician = $users->findOne([
                    '$and' => [
                        [
                            "_id" => new MongoDB\BSON\ObjectId($applicationTechs['user']), // ID de l'utilisateur
                            "active" => true
                        ]
                    ],
                ]);

                $technicians[] = $technician['firstName'].' '.$technician['lastName'];

                $technicians = array_unique($technicians);

                $techTrainingDatas = $applications->find([
                    "user" => new MongoDB\BSON\ObjectId($applicationTechs['user']), // ID de l'utilisateur
                    "training" => new MongoDB\BSON\ObjectId($trainingId), // ID de la formation
                    'active' => true
                ])->toArray();

                $trainingTechSelected[] = $applicationTechs['user'];
            }
        }
    
        $manager = $users->findOne([
            '$and' => [
                [
                    "_id" => new MongoDB\BSON\ObjectId($currentUser), // ID du manager
                    "active" => true
                ]
            ],
        ]);
    
        $trainingTechSelected = array_unique($trainingTechSelected);
    
        sendTrainingMail($training, $selectedDate, $place, $technicians, $manager, $trainingTechSelected, $trainingName, $status[0] = 'En attente');
    }

    // Sauvegarde des formations avec un status de validation
    if (isset($_POST['selectValidation'])) {
        $userIds = $_POST["userIds"] ?? [];
        $trainingId = $_POST["trainingId"] ?? '';
        $status = $_POST["selectedValue"] ?? '';
        $currentUser = $_SESSION['id'];

        $trainingTechSelected = [];
        $trainingSelected = [];

        $training = $trainings->findOne(['_id' => new MongoDB\BSON\ObjectId($trainingId)]);
        $trainingName = $training['label'];

        foreach ($userIds as $userId) {
            $applicationTechs = $allocations->findOne([
                "user" => new MongoDB\BSON\ObjectId($userId),
                "training" => new MongoDB\BSON\ObjectId($trainingId),
                "type" => "Training"
            ]);

            if ($applicationTechs) {
                $app = $validations->findOne([
                    "user" => new MongoDB\BSON\ObjectId($applicationTechs['user']),
                    "training" => new MongoDB\BSON\ObjectId($trainingId),
                    "active" => true
                ]);

                if ($app) {
                    // Mise à jour de l'enregistrement existant
                    $validations->updateOne(
                        [
                            "user" => new MongoDB\BSON\ObjectId($userId),
                            "training" => new MongoDB\BSON\ObjectId($trainingId)
                        ],
                        [
                            '$set' => [
                                'status' => $status,
                                'updated' => date("d-m-Y H:i:s")
                            ]
                        ]
                    );
                } else {
                    // Insertion d'un nouvel enregistrement
                    $applicates = [
                        'user' => new MongoDB\BSON\ObjectId($applicationTechs['user']),
                        'training' => new MongoDB\BSON\ObjectId($trainingId),
                        'manager' => new MongoDB\BSON\ObjectId($currentUser ),
                        'status' => $status,
                        'active' => true,
                        'created' => date("d-m-Y H:i:s")
                    ];
                    $validations->insertOne($applicates);
                }

                $trainingTechSelected[] = $applicationTechs['user'];
            }
        }
        if ($status == 'Validé') {
            // Élimination des doublons
            $trainingTechSelected = array_unique($trainingTechSelected);
    
            // Préparation de la réponse
            $response = [
                'success' => true,
                'training' => 1,
                'technicians' => count($trainingTechSelected),
                'message' => 'Formation « ' . htmlspecialchars($trainingName) . ' » ' . htmlspecialchars($status) . '.'
            ];
            echo json_encode($response);
            exit(); // Terminer le script après avoir envoyé la réponse
        } else {
            // Préparation de la réponse
            $response = [
                'success' => true,
                'training' => 0,
                'technicians' => 0,
                'message' => 'Formation « ' . htmlspecialchars($trainingName) . ' » ' . htmlspecialchars($status) . '.'
            ];
            echo json_encode($response);
            exit(); // Terminer le script après avoir envoyé la réponse
        }
    }
    function oid($id): MongoDB\BSON\ObjectId
{
    return $id instanceof MongoDB\BSON\ObjectId
        ? $id                         // déjà bon, on renvoie tel quel
        : new MongoDB\BSON\ObjectId((string) $id);   // sinon on convertit
}
    ?>


<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo 'Validation des Plans de Formations'  ?> | CFAO Mobility Academy</title>
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
                <h1 class="text-dark fw-bolder my-1 fs-1">
                    <?php echo 'Validation des Plans de Formations'  ?> </h1>
                <!--end::Title-->
                <div class="card-title">
                </div>
            </div>
            <!--end::Info-->
        </div>
        <!--begin::Filtres -->
        <div class="container my-4">
            <div class="row g-3 align-items-center">
                <!-- Filtre Level -->
                <div class="col-md-3">
                    <label for="level-filter" class="form-label d-flex align-items-center">
                        <i class="bi bi-bar-chart-fill fs-2 me-2 text-warning"></i> Niveaux
                    </label>
                    <select id="level-filter" name="level" class="form-select" onchange="applyFilters()">
                        <option value="all" <?php if ($levelFilter === 'all') echo 'selected'; ?>>Tous les niveaux</option>
                        <?php foreach (['Junior', 'Senior', 'Expert'] as $levelOption): ?>
                        <option value="<?php echo htmlspecialchars($levelOption); ?>"
                                <?php if ($levelFilter === $levelOption) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($levelOption); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtre Formations -->
