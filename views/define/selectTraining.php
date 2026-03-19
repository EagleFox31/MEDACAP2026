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
    
    // Brand logos mapping
    $brandLogos = [
        'RENAULT TRUCK'   => 'renaultTrucks.png',
        'HINO'            => 'Hino_logo.png',
        'TOYOTA BT'       => 'bt.png',
        'SINOTRUK'        => 'sinotruk.png',
        'JCB'             => 'jcb.png',
        'MERCEDES TRUCK'  => 'mercedestruck.png',
        'TOYOTA FORKLIFT' => 'forklift.png',
        'FUSO'            => 'fuso.png',
        'LOVOL'           => 'lovol.png',
        'KING LONG'       => 'kl2.png',
        'MERCEDES'        => 'mercedestruck.png',
        'TOYOTA'          => 'toyota-logo.png',
        'SUZUKI'          => 'suzuki-logo.png',
        'MITSUBISHI'      => 'mitsubishi-logo.png',
        'BYD'             => 'byd-logo.png',
        'CITROEN'         => 'citroen-logo.png',
        'PEUGEOT'         => 'peugeot-logo.png',
    ];

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



<?php
// Inclure le header mais masquer son spinner via la balise style
echo '<style>#spinner { display: none !important; }</style>';
include_once "partials/header.php";
?>
<!--begin::Title-->
<title><?php echo 'Validation des Plans de Formations par la filiale'  ?> | CFAO Mobility Academy</title>
<!--end::Title-->

<!--begin::Body-->
<style>
    :root {
        /* Couleurs de l'entreprise */
        --primary-black: #1a1a1a;
        --primary-red: #dc2626;
        --primary-navy: #1e3a8a;
        --secondary-navy: #3b82f6;
        --light-gray: #f8fafc;
        --medium-gray: #64748b;
        --dark-gray: #334155;
        --white: #ffffff;
        --border-color: #e2e8f0;
        --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --border-radius: 12px;
        --border-radius-lg: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Effet de glassmorphisme pour les cartes */
    .glass-effect {
        background: rgba(255, 255, 255, 0.7) !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1) !important;
        border-radius: 15px !important;
        transition: all 0.3s ease !important;
    }
    
    /* Effet hover sur les titres */
    .glass-effect:hover {
        background: rgba(255, 255, 255, 0.8) !important;
        box-shadow: 0 10px 40px 0 rgba(31, 38, 135, 0.2) !important;
        transform: translateY(-3px) !important;
    }
    
    /* Style pastel pour les en-têtes de filtre et formations */
    .filter-header, .training-header {
        background: linear-gradient(135deg,rgb(248, 250, 251) 0%,rgb(250, 251, 251) 100%) !important;
        color: #333 !important;
        text-align: center !important;
        padding: 1rem !important;
    }
    
    .filter-header h5, .training-header h5 {
        color: #333 !important;
        font-weight: 700 !important;
        display: inline-block !important;
        margin: 0 auto !important;
    }
    
    /* Style personnalisé pour le bouton "Appliquer les filtres" */
    #applyFiltersButton,
    #applyFiltersButton.btn,
    #applyFiltersButton.btn-primary {
        background: white !important;
        background-color: white !important;
        background-image: none !important;
        color: black !important;
        border: 1px solid #ddd !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
    }
    
    #applyFiltersButton:hover,
    #applyFiltersButton.btn:hover,
    #applyFiltersButton.btn-primary:hover {
        background-color: #f8f9fa !important;
        background-image: none !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
        color: black !important;
    }
    
    #applyFiltersButton .ki-duotone,
    #applyFiltersButton .ki-duotone .path1,
    #applyFiltersButton .ki-duotone .path2 {
        color: black !important;
    }
    
    /* Styles pour augmenter la taille des textes dans la zone de filtres */
    .col-lg-3 .form-label {
        font-size: 1.1rem !important;
    }
    
    .col-lg-3 .form-label .fw-bold {
        font-size: 1.2rem !important;
    }
    
    .col-lg-3 .form-select {
        font-size: 1.05rem !important;
        padding: 0.5rem !important;
    }
    
    .col-lg-3 .form-select option {
        font-size: 1.05rem !important;
    }
    
    .col-lg-3 .card-title {
        font-size: 1.25rem !important;
    }
    
    /* Effet de profondeur pour les cartes */
    .depth-effect {
        transform: translateZ(0);
        box-shadow:
            0 4px 6px -1px rgba(0, 0, 0, 0.1),
            0 2px 4px -1px rgba(0, 0, 0, 0.06),
            0 12px 20px -2px rgba(0, 0, 0, 0.05) !important;
        transition: all 0.3s ease-in-out !important;
    }
    
    .depth-effect:hover {
        transform: translateY(-5px) translateZ(0);
        box-shadow:
            0 10px 15px -3px rgba(0, 0, 0, 0.1),
            0 4px 6px -2px rgba(0, 0, 0, 0.05),
            0 20px 25px -5px rgba(0, 0, 0, 0.03) !important;
    }

    /* Cards principales */
    .card {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: var(--border-radius);
        box-shadow:
            0 4px 6px -1px rgba(0, 0, 0, 0.1),
            0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        overflow: hidden;
        margin-bottom: 1.5rem;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .card:hover {
        box-shadow:
            0 10px 25px -5px rgba(0, 0, 0, 0.1),
            0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: translateY(-8px);
    }
    
    /* Style pour les titres dans les cartes glassmorphisme */
    .glass-effect h1, .glass-effect h2, .glass-effect h6 {
        color: var(--primary-black) !important;
        font-weight: 700;
        letter-spacing: -0.025em;
        text-shadow: 0px 1px 1px rgba(255, 255, 255, 0.5);
    }

    /* Stats cards spécifiques - Style compact horizontal */
    .custom-card {
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow:
            0 4px 20px 0 rgba(0, 0, 0, 0.1),
            0 1px 5px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1);
        overflow: hidden;
        min-height: 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-radius: var(--border-radius);
    }

    .custom-card:hover {
        box-shadow:
            0 15px 30px 0 rgba(0, 0, 0, 0.1),
            0 5px 15px 0 rgba(0, 0, 0, 0.05);
        transform: translateY(-8px) scale(1.02);
        border-color: rgba(30, 58, 138, 0.3);
    }
    
    /* Nous n'avons pas besoin de définitions CSS spécifiques pour l'arrière-plan
       car nous utiliserons le style en ligne comme dans dashboard.php */
</style>

<!-- Loading Overlay -->
<div id="loading-overlay" style="display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.8); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <div class="mt-3 fw-bold fs-4">Chargement des données...</div>
    <button onclick="document.getElementById('loading-overlay').style.display='none';" class="btn btn-sm btn-light mt-3">
        Cliquez ici si le chargement persiste
    </button>
</div>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    style="position: relative; border-radius: 25px; overflow: hidden;"
    data-select2-id="select2-data-kt_content">
    
    <!-- Background overlay with blur effect -->
    <div style="
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        filter: blur(10px);
        transform: scale(1.05);
        background-image: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1)), url('/MEDACAP/public/images/welcome_tech.png');
        background-size: cover;
        background-position: center;
        border-radius: 25px;
        z-index: 0;">
    </div>
    
    <!-- Content container -->
    <div style="position: relative; z-index: 1; border-radius: 25px; overflow: hidden;">

    
    <!-- Titre principal en pleine largeur -->
    <div class="container-xxl pt-5">
        <div class="card mb-5" style="background-color: #FFFFFF !important; border-radius: 25px !important; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); border: 1px solid rgba(255, 255, 255, 0.18);">
            <div class="card-body text-center py-4">
                <h1 class="card-title mb-0"><i class="ki-duotone ki-document fs-2 me-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>Validation des Plans de Formations par la filiale</h1>
            </div>
        </div>
    </div>
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <div class="card-title">
                </div>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    
    <!-- Container principal avec contenu à gauche et filtres à droite -->
    <div class="container-xxl">
        <div class="row">
            <!-- Colonne de contenu principal à gauche -->
            <div class="col-lg-9">
                <!-- begin:: Cartes de statistiques -->
                <div class="text-center mb-3">
                    <div class="row justify-content-center">
                        <div class='col-6 col-sm-4 col-md-6'>
                            <div class='card glass-effect depth-effect h-70' style="background: rgba(224, 224, 72, 0.74) !important; border: 1px solid rgba(255, 255, 150, 0.3) !important; box-shadow: 0 8px 32px 0 rgba(255, 200, 0, 0.2) !important; ">
                                <div class='card-body d-flex flex-column justify-content-center align-items-center py-2'>
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div class="fs-lg-3hx fs-1x fw-bolder text-gray-800 d-flex justify-content-center text-center">
                                        <div class="min-w-100px" data-kt-countup="true" data-kt-countup-value="<?php echo count($trainingDatas) ?>"></div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-4 fw-bold mb-2">
                                        <?php echo 'Formations Proposées par l\'Academy' ?>
                                    </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                            </div>
                        </div>
                        <div class='col-6 col-sm-4 col-md-6'>
                            <div class='card glass-effect depth-effect h-70' style="background: rgba(109, 180, 235, 0.7) !important; border: 1px solid rgba(150, 200, 255, 0.3) !important; box-shadow: 0 8px 32px 0 rgba(0, 100, 255, 0.2) !important; ">
                                <div class='card-body d-flex flex-column justify-content-center align-items-center py-2'>
                                    <!--begin::Name-->
                                    <!--begin::Animation-->
                                    <div class="fs-lg-3hx fs-1x fw-bolder text-gray-800 d-flex justify-content-center text-center">
                                        <div id="selectTraining" class="min-w-100px" data-kt-countup="true"></div>
                                    </div>
                                    <!--end::Animation-->
                                    <!--begin::Title-->
                                    <div class="fs-4 fw-bold mb-2">
                                        <?php echo 'Formations Validées par la Filiale' ?>
                                    </div>
                                    <!--end::Title-->
                                    <!--end::Name-->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card du titre liste des formations -->
                <div style="margin-top: 15px; margin-bottom: 15px;">
                    <div class="card mb-4" style="background-color: #FFFFFF !important; border-radius: 25px !important; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1); border: 1px solid rgba(255, 255, 255, 0.18);">
                        <div class="card-body text-center py-3">
                            <h6 class="form-label text-dark my-1 fs-3">
                                <i class="fas fa-list fs-2 me-3"></i>
                                <?php
                                if ($techFilter !== 'all') {
                                    // Trouver les informations du technicien sélectionné
                                    $selectedTech = null;
                                    foreach ($technicians as $tech) {
                                        if ((string)$tech['_id'] === $techFilter) {
                                            $selectedTech = $tech;
                                            break;
                                        }
                                    }
                                    if ($selectedTech) {
                                        echo 'Liste des formations préconisées par l\'Academy et à valider par la filiale pour:  M. ' . htmlspecialchars($selectedTech['firstName'] . ' ' . $selectedTech['lastName']);
                                    } else {
                                        echo 'Liste des formations préconisées par l\'Academy et à valider par la filiale pour:   Tous les Techniciens';
                                    }
                                } else {
                                    echo 'Liste des formations préconisées par l\'Academy et à valider par la filiale pour :   Tous les Techniciens';
                                }
                                ?>
                            </h6>
                        </div>
                    </div>
                </div>
                
                <!-- Tableau des formations -->
                <div id="message"></div>
                
                <!--begin::Form-->
                <form name="form" method="POST">
                    <!--begin::Card-->
                    <div class="card glass-effect depth-effect h-100" style="min-height: 700px; display: flex; flex-direction: column;">
                        <!--begin::Card body-->
                        <div class="card-body pt-0 d-flex flex-column flex-grow-1 h-100" style="flex: 1 1 auto; padding-bottom: 0;">
                            <!--begin::Table-->
                            <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer h-100 d-flex flex-column" style="flex: 1 1 auto; min-height: 100%;">
                                <div class="table-responsive" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; height: auto; min-height: 700px; margin-top: 15px;">
                                    <table aria-describedby=""
                                        class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer"
                                        id="kt_customers_table" style="width: 100%;">
                                <thead style="background-color: white;">
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-130px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Brand: activate to sort column ascending"
                                            style="width: 130px; text-align: center; vertical-align: middle; height: 20px;">
                                            <?php echo $Brand ?>
                                        </th>
                                        <th class="min-w-100px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Level: activate to sort column ascending"
                                            style="width: 100px; text-align: center; vertical-align: middle; height: 20px;">
                                            <?php echo $Level ?>
                                        </th>
                                        <th class="min-w-300px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Training Label: activate to sort column ascending"
                                            style="width: 300px; text-align: center; vertical-align: middle; height: 20px;">
                                            <?php echo $label_training ?>
                                        </th>
                                        <th class="min-w-120px sorting" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="1" colspan="1"
                                            aria-label="Validation: activate to sort column ascending"
                                            style="width: 120px; text-align: center; vertical-align: middle; height: 20px;">
                                            <?php echo 'Validation de la filiale' ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table" style='text-align: center; vertical-align: middle; height: 70px;'>
                                    <?php foreach ($trainingDatas as $index => $training) {
                                        $periods = [];
                                        $trainingValidations = [];
                                        $currentYear = date('Y'); // Get the current year

                                        if ($techFilter == 'all') {
                                            // Récupérer tous les techniciens associés à cette formation
                                            foreach ($technicians as $technician) {
                                                $trainingAllocates = $applications->find([
                                                    'user' => oid($technician['_id']),
                                                    'training' => new MongoDB\BSON\ObjectId($training['_id']),
                                                    'active' => true
                                                ])->toArray();

                                                // Vérifier si tous les techniciens ont l'année courante dans leurs années d'allocation
                                                foreach ($trainingAllocates as $trainingAllocate) {
                                                    $periods[] = $trainingAllocate['period'];
                                                }

                                                $trainingValidates = $validations->find([
                                                    'user' => oid($technician['_id']),
                                                    'training' => new MongoDB\BSON\ObjectId($training['_id']),
                                                    'active' => true
                                                ])->toArray();

                                                foreach ($trainingValidates as $trainingValidate) {
                                                    $trainingValidations[] = $trainingValidate['status'];
                                                }
                                            }
                                        } else {
                                            // Initialiser le tableau de filtres
                                            $filter = [
                                                'user' => new MongoDB\BSON\ObjectId($techFilter),
                                                'training' => new MongoDB\BSON\ObjectId($training['_id']),
                                                'active' => true
                                            ];

                                            $trainingAllocates = $applications->find($filter)->toArray();
                                            foreach ($trainingAllocates as $trainingAllocate) {
                                                $periods[] = $trainingAllocate['period'];
                                            }
                                            
                                            $trainingValidates = $validations->find([
                                                'user' => new MongoDB\BSON\ObjectId($techFilter),
                                                'training' => new MongoDB\BSON\ObjectId($training['_id']),
                                                'active' => true
                                            ])->toArray();
                                            
                                            foreach ($trainingValidates as $trainingValidate) {
                                                $trainingValidations[] = $trainingValidate['status'];
                                            }
                                        }

                                        $periods = array_unique($periods);
                                        $trainingValidations = array_unique($trainingValidations);
                                    ?>
                                    <tr class="odd" etat="<?php echo htmlspecialchars($training->active); ?>">
                                        <td style="text-align: center; vertical-align: middle;">
                                            <?php
                                            $brandName = htmlspecialchars($training->brand);
                                            $logoFile = $brandLogos[$brandName] ?? '';
                                            if ($logoFile) {
                                                echo '<img src="../../public/images/' . $logoFile . '" alt="' . $brandName . '" style="max-height: 40px; max-width: 120px;">';
                                            } else {
                                                echo $brandName;
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($training->level); ?></td>
                                        <td><?php echo htmlspecialchars($training->label); ?></td>
                                        <input type="text" id="training-<?php echo $index; ?>"
                                        value="<?php echo htmlspecialchars($training['_id']); ?>" hidden>
                                        <td>
                                            <select id="select-<?php echo $index; ?>" name="validation" class="form-select">
                                                <?php foreach (['En attente', 'Validé', 'Non validé'] as $option): ?>
                                                    <option value="<?php echo $option ?>"
                                                        <?php if (in_array($option, $trainingValidations)) echo 'selected'; ?>>
                                                        <?php
                                                        if ($option == 'Validé') {
                                                            echo 'Validé en 2025';
                                                        } elseif ($option == 'En attente') {
                                                            echo 'Validé en 2026';
                                                        } elseif ($option == 'Non validé'){
                                                            echo 'Refusé';;
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                                    </table>
                                </div>
                            </div>
                            <!--end::Table-->
                        </div>
                        <!--end::Card body-->
                    </div>
                    <!--end::Card-->
                </form>
                <!--end::Form-->
            </div>
            
            <!-- Colonne des filtres à droite -->
            <div class="col-lg-3">
                <div class="card glass-effect depth-effect" style="position: sticky; top: 20px; height: 100%; min-height: 700px;">
                    <div class="card-header-bg filter-header text-start">
                        <i class="ki-duotone ki-filter fs-4 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h5 class="card-title mb-0 d-inline">FILTRE DES DONNÉES</h5>
                    </div>
                    <div class="card-body py-5">
                        <div id="dynamicFilters">
                             <!-- Espacement supplémentaire -->
                            <div class="spacer py-4"></div>
                            <!-- Filtre Techniciens -->
                            <div class="mb-5">
                                <label for="tech-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-person-fill fs-5 me-2 text-info"></i> <span class="fw-bold">Techniciens</span>
                                </label>
                                <select id="tech-filter" class="form-select form-select-sm mt-2" onchange="setTimeout(applyFilters, 300)">
                                    <option value="all" <?php if ($techFilter === 'all') echo 'selected'; ?>>
                                        Tous les techniciens
                                    </option>
                                    <?php foreach ($technicians as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t['_id']); ?>"
                                        <?php if ($techFilter === htmlspecialchars($t['_id'])) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($t['firstName'] .' '. $t['lastName']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Espacement supplémentaire -->
                            <div class="spacer py-4"></div>

                            <!-- Filtre Marques -->
                            <div class="mb-5">
                                <label for="brand-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-car-front-fill fs-5 me-2 text-danger"></i> <span class="fw-bold">Marques</span>
                                </label>
                                <select id="brand-filter" class="form-select form-select-sm mt-2" onchange="setTimeout(applyFilters, 300)">
                                    <option value="all" <?php if ($brandFilter === 'all') echo 'selected'; ?>>Toutes les marques
                                    </option>
                                    <?php foreach ($teamBrands as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>"
                                        <?php if ($brandFilter === $b) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($b); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                         <!-- Espacement supplémentaire -->
                            <div class="spacer py-4"></div>

                            <!-- Filtre Level -->
                            <div class="mb-5">
                                <label for="level-filter" class="form-label d-flex align-items-center">
                                    <i class="bi bi-bar-chart-fill fs-5 me-2 text-warning"></i> <span class="fw-bold">Niveaux</span>
                                </label>
                                <select id="level-filter" name="level" class="form-select form-select-sm mt-2" onchange="setTimeout(applyFilters, 300)">
                                    <option value="all" <?php if ($levelFilter === 'all') echo 'selected'; ?>>Tous les niveaux</option>
                                    <?php foreach (['Junior', 'Senior', 'Expert'] as $levelOption): ?>
                                    <option value="<?php echo htmlspecialchars($levelOption); ?>"
                                            <?php if ($levelFilter === $levelOption) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($levelOption); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Espacement supplémentaire -->
                            <div class="spacer py-4"></div>

                            <!-- Filtre Formations -->
                            <div class="mb-5">
                                <label for="training-filter" class="form-label d-flex align-items-center">
                                    <i class="fas fa-book-open fs-5 text-success me-2"></i> <span class="fw-bold">Formations</span>
                                </label>
                                <select id="training-filter" class="form-select form-select-sm mt-2" onchange="setTimeout(applyFilters, 300)">
                                    <option value="all" <?php if ($trainingFilter === 'all') echo 'selected'; ?>>Toutes les formations
                                    </option>
                                    <?php foreach ($trainingDatas as $td): ?>
                                    <option value="<?php echo htmlspecialchars($td['_id']); ?>"
                                        <?php if ($trainingFilter === htmlspecialchars($td['_id'])) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($td['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            


                            <!-- Le bouton Appliquer a été supprimé -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--
    <div class="text-center mb-6">
        <div class="row justify-content-center">
            <div class='col-6 col-sm-4 col-md-5'>
                <div class='card glass-effect depth-effect h-80'>
                    <div class='card-body d-flex flex-column justify-content-center align-items-center'>
                        <i class="fas fa-calendar-alt fs-2 text-warning mb-2"></i>
                        <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                            <div class="min-w-80px" data-kt-countup="true"
                            data-kt-countup-value="<?php echo (int)$numDays; ?>" data-kt-countup-separator=" "></div>
                        </div>
                        <div class="fs-5 fw-bold mb-2">
                            <?php echo $training_duration ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class='col-6 col-sm-4 col-md-5'>
                <div class='card glass-effect depth-effect h-80'>
                    <div class='card-body d-flex flex-column justify-content-center align-items-center'>
                        <i class="fas fa-calendar-alt fs-2 text-warning mb-2"></i>
                        <div class="fs-lg-1hx fs-2x fw-bold text-gray-800 d-flex justify-content-center text-center">
                            <div id="durationTraining" class="min-w-80px" data-kt-countup="true" data-kt-countup-separator=" "></div>
                        </div>
                        <div class="fs-5 fw-bold mb-2">
                            <?php echo $training_duration ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    -->
    <!-- end::Marques du Technicien -->

    
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!-- Le tableau a été déplacé dans la colonne de gauche -->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
    </div>
</div>
<!--end::Body-->
<script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
    crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js">
</script>
<script src="../../public/js/main.js"></script>
<?php include_once "partials/footer.php"; ?>
<script>
    
    const technicians = <?php echo json_encode($technicianIds); ?>;
    const trainingSelect = <?php echo json_encode($trainingSelected); ?>;
    const trainingTechSelected = <?php echo json_encode($trainingTechSelected); ?>;
    const trainingDatas = <?php echo json_encode($trainingDatas); ?>;
    const result2 = <?php echo json_encode($result2); ?>;

    console.log("Technicians:", technicians);
    console.log("trainingSelect:", trainingSelect);
    console.log("trainingTechSelected:", trainingTechSelected);
    console.log("trainingDatas:", trainingDatas);
    console.log("result2:", result2);

    // Sélectionner toutes les selects dans le tableau
    const selectValidations = document.querySelectorAll('select[name="validation"]');
    const trainingSelected = document.querySelector('#selectTraining');
    const trainingDuration = document.querySelector('#durationTraining');

    var totalTechs  = trainingTechSelected.length;
    
    trainingSelected.setAttribute('data-kt-countup-value', trainingSelect.length);
    trainingDuration.setAttribute('data-kt-countup-value', (totalTechs * 5));
    
    // Initialisation des variables
    var total = trainingSelected.getAttribute("data-kt-countup-value");

    // Parcourir chaque case de validation
    selectValidations.forEach(select => {
        // Écouter les changements de la case de validation
        select.addEventListener('change', function() {
            const rowIndex = this.id.split('-')[1]; // Récupérer l'index de la ligne
            const selectedValue = this.value; // Récupérer la valeur du select
            const userId = document.querySelector('#tech-filter').value; // Récupérer la valeur des techniciens
            const trainingId = document.querySelector('#training-' + rowIndex).value; // Récupérer la'id de la formation'

            console.log('rowIndex', rowIndex);
            console.log('selectedValue', selectedValue);
            console.log('trainingId', trainingId);
            
            // Récupérer les IDs des techniciens
            let techniciansIds = userId === 'all' ? technicians : [userId];

            console.log('techniciansIds', techniciansIds);

            // Envoyer les données via AJAX
            const datas = {
                userIds: techniciansIds,
                trainingId: trainingId,
                selectedValue: selectedValue,
                currentYear: currentYear
            };
            
            // Envoyer une requête AJAX pour activer
            $.ajax({
                type: 'POST',
                data: { ...datas, selectValidation: 1 },
                dataType: 'json',
                success: function(response) {
                    // Si la réponse est déjà un objet JSON, pas besoin de la parser
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    console.log('response', response);
                    if (response && response.success) {
                        total = parseInt(total) + response.training;
                        totalTechs = parseInt(totalTechs) + response.technicians;
                        updateUI();
                        showMessage('success', response.message);
                    } else {
                        showMessage('error', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('error', error);
                    showMessage('error', 'Une erreur est survenue. Veuillez réessayer.');
                }
            });
        });
    });


    // Fonction pour mettre à jour l'interface utilisateur
    function updateUI() {
        trainingSelected .setAttribute('data-kt-countup-value', total);
        trainingDuration.setAttribute('data-kt-countup-value', (totalTechs * 5));
        trainingSelected.innerHTML = total;
        trainingDuration.innerHTML = (totalTechs * 5);
    }

    // Fonction pour afficher les messages
    function showMessage(type, message) {
        const alertType = type === 'success' ? 'alert-success' : 'alert-danger';
        $('#message').html(`
            <div class="alert ${alertType} alert-dismissible fade show" role="alert">
                <center><strong>${message}</strong></center>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `);
    }

    // Fonction pour appliquer les filtres et recharger la page avec de nouveaux paramètres (s'exécute directement au changement des sélects)
    function applyFilters() {
        const level = document.getElementById('level-filter').value;
        const brand = document.getElementById('brand-filter').value;
        const technician = document.getElementById('tech-filter').value;
        const training = document.getElementById('training-filter').value;
        
        // Masquer le spinner du header s'il est présent
        const headerSpinner = document.getElementById('spinner');
        if (headerSpinner) {
            headerSpinner.style.display = 'none';
        }
        
        // Toujours afficher notre propre loader pour une meilleure UX
        document.getElementById('loading-overlay').style.display = 'flex';

        let query = `?user=${encodeURIComponent(technician)}&training=${encodeURIComponent(training)}&brand=${encodeURIComponent(brand)}&level=${encodeURIComponent(level)}`;
        window.location.href = query;
    }

    // Gestion du loader au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        // Afficher uniquement notre loader personnalisé
        const headerSpinner = document.getElementById('spinner');
        if (headerSpinner) {
            headerSpinner.style.display = 'none';
        }
        
        // Forcer la disparition du loader après le chargement du DOM
        setTimeout(function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
        }, 800); // Délai plus court pour une meilleure réactivité
    });

    $(document).ready(function() {
        $("#excel").on("click", function() {
            let table = document.getElementsByTagName("table");
            debugger;
            TableToExcel.convert(table[0], {
                name: `Users.xlsx`
            })
        });
    });
</script>

<!-- Script pour s'assurer que le loader est caché -->
<script>
(function() {
    // S'exécute immédiatement à la fin de la page
    var loader = document.getElementById('loading-overlay');
    if (loader) {
        loader.style.display = 'none';
    }
    
    // Double vérification après un délai très court
    setTimeout(function() {
        var headerSpinner = document.getElementById('spinner');
        var pageLoader = document.getElementById('loading-overlay');
        
        // S'assurer que les deux loaders sont masqués
        if (headerSpinner) headerSpinner.style.display = 'none';
        if (pageLoader) pageLoader.style.display = 'none';
    }, 100);
})();
</script>
<?php } ?>
