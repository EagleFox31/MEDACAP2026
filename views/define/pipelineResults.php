<?php

//pipelineResults.php

require_once "../../vendor/autoload.php";
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

// Connexion MongoDB
try {
    $mongoClient = new Client("mongodb://localhost:27017");
    $academy = $mongoClient->selectDatabase("academy");
} catch (\MongoDB\Exception\Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Erreur de connexion à MongoDB: " . $e->getMessage()]);
    exit();
}

// Vérification de l'ID du manager
if (!isset($_GET['managerId'])) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Paramètre managerId manquant."]);
    exit();
}

$managerId = $_GET['managerId'];
try {
    $managerId = new ObjectId($managerId);
} catch (\Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Format d'ID invalide pour managerId."]);
    exit();
}

// Pipeline MongoDB
$pipeline = [
    // Étape 1 : Filtrer le manager
    [
        '$match' => [
            '_id' => $managerId,
            'profile' => 'Manager'
        ]
    ],
    // Étape 2 : Lookup pour récupérer les subordonnés
    [
        '$lookup' => [
            'from' => 'users',
            'localField' => 'users',
            'foreignField' => '_id',
            'as' => 'subordinates'
        ]
    ],
    // Étape 3 : Compter les techniciens, les managers et leurs détails
    [
        '$addFields' => [
            'totalManagers' => [
                '$size' => [
                    '$filter' => [
                        'input' => '$subordinates',
                        'as' => 'subordinate',
                        'cond' => ['$eq' => ['$$subordinate.profile', 'Manager']]
                    ]
                ]
            ],
            'technicians' => [
                '$filter' => [
                    'input' => '$subordinates',
                    'as' => 'subordinate',
                    'cond' => ['$eq' => ['$$subordinate.profile', 'Technicien']]
                ]
            ],
            'totalTechnicians' => [
                '$size' => [
                    '$filter' => [
                        'input' => '$subordinates',
                        'as' => 'subordinate',
                        'cond' => ['$eq' => ['$$subordinate.profile', 'Technicien']]
                    ]
                ]
            ]
        ]
    ],
    // Étape 4 : Lookup pour les formations
    [
        '$lookup' => [
            'from' => 'trainings',
            'let' => ['technicianIds' => '$technicians._id'],
            'pipeline' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$gt' => [
                                ['$size' => ['$setIntersection' => ['$users', '$$technicianIds']]],
                                0
                            ]
                        ]
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 1,
                        'brand' => 1,
                        'level' => 1,
                        'users' => 1
                    ]
                ]
            ],
            'as' => 'allTrainings'
        ]
    ],
    // Étape 5 : Ajouter les détails pour chaque technicien
    [
        '$addFields' => [
            'technicians' => [
                '$map' => [
                    'input' => '$technicians',
                    'as' => 'technician',
                    'in' => [
                        '_id' => '$$technician._id',
                        'firstName' => '$$technician.firstName',
                        'lastName' => '$$technician.lastName',
                        'distinctBrands' => [
                            '$setUnion' => [
                                [
                                    '$map' => [
                                        'input' => [
                                            '$filter' => [
                                                'input' => '$allTrainings',
                                                'as' => 'training',
                                                'cond' => ['$in' => ['$$technician._id', '$$training.users']]
                                            ]
                                        ],
                                        'as' => 'training',
                                        'in' => '$$training.brand'
                                    ]
                                ]
                            ]
                        ],
                        'totalDistinctBrands' => [
                            '$size' => [
                                '$setUnion' => [
                                    [
                                        '$map' => [
                                            'input' => [
                                                '$filter' => [
                                                    'input' => '$allTrainings',
                                                    'as' => 'training',
                                                    'cond' => ['$in' => ['$$technician._id', '$$training.users']]
                                                ]
                                            ],
                                            'as' => 'training',
                                            'in' => '$$training.brand'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'totalTrainings' => [
                            '$size' => [
                                '$filter' => [
                                    'input' => '$allTrainings',
                                    'as' => 'training',
                                    'cond' => ['$in' => ['$$technician._id', '$$training.users']]
                                ]
                            ]
                        ],
                        'trainingsByLevel' => [
                            '$arrayToObject' => [
                                '$map' => [
                                    'input' => [
                                        '$setUnion' => [
                                            [
                                                '$map' => [
                                                    'input' => [
                                                        '$filter' => [
                                                            'input' => '$allTrainings',
                                                            'as' => 'training',
                                                            'cond' => ['$in' => ['$$technician._id', '$$training.users']]
                                                        ]
                                                    ],
                                                    'as' => 'training',
                                                    'in' => '$$training.level'
                                                ]
                                            ]
                                        ]
                                    ],
                                    'as' => 'level',
                                    'in' => [
                                        'k' => '$$level',
                                        'v' => [
                                            '$size' => [
                                                '$filter' => [
                                                    'input' => '$allTrainings',
                                                    'as' => 'training',
                                                    'cond' => [
                                                        '$and' => [
                                                            ['$in' => ['$$technician._id', '$$training.users']],
                                                            ['$eq' => ['$$training.level', '$$level']]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'trainingsByBrandAndLevel' => [
                            '$arrayToObject' => [
                                '$map' => [
                                    'input' => [
                                        '$setUnion' => [
                                            [
                                                '$map' => [
                                                    'input' => [
                                                        '$filter' => [
                                                            'input' => '$allTrainings',
                                                            'as' => 'training',
                                                            'cond' => ['$in' => ['$$technician._id', '$$training.users']]
                                                        ]
                                                    ],
                                                    'as' => 'training',
                                                    'in' => '$$training.brand'
                                                ]
                                            ]
                                        ]
                                    ],
                                    'as' => 'brand',
                                    'in' => [
                                        'k' => '$$brand',
                                        'v' => [
                                            '$arrayToObject' => [
                                                '$map' => [
                                                    'input' => [
                                                        '$setUnion' => [
                                                            [
                                                                '$map' => [
                                                                    'input' => [
                                                                        '$filter' => [
                                                                            'input' => '$allTrainings',
                                                                            'as' => 'training',
                                                                            'cond' => [
                                                                                '$and' => [
                                                                                    ['$in' => ['$$technician._id', '$$training.users']],
                                                                                    ['$eq' => ['$$training.brand', '$$brand']]
                                                                                ]
                                                                            ]
                                                                        ]
                                                                    ],
                                                                    'as' => 'training',
                                                                    'in' => '$$training.level'
                                                                ]
                                                            ]
                                                        ]
                                                    ],
                                                    'as' => 'level',
                                                    'in' => [
                                                        'k' => '$$level',
                                                        'v' => [
                                                            '$size' => [
                                                                '$filter' => [
                                                                    'input' => '$allTrainings',
                                                                    'as' => 'training',
                                                                    'cond' => [
                                                                        '$and' => [
                                                                            ['$in' => ['$$technician._id', '$$training.users']],
                                                                            ['$eq' => ['$$training.brand', '$$brand']],
                                                                            ['$eq' => ['$$training.level', '$$level']]
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    // Étape 6 : Calculer les totaux globaux
    [
        '$addFields' => [
            'totalDistinctBrands' => [
                '$size' => [
                    '$setUnion' => [
                        [
                            '$map' => [
                                'input' => '$allTrainings',
                                'as' => 'training',
                                'in' => '$$training.brand'
                            ]
                        ]
                    ]
                ]
            ],
            'totalTrainings' => [
                '$sum' => [
                    '$map' => [
                        'input' => '$technicians',
                        'as' => 'technician',
                        'in' => '$$technician.totalTrainings'
                    ]
                ]
            ],
            'totalTrainingsByLevel' => [
                '$arrayToObject' => [
                    '$map' => [
                        'input' => [
                            '$setUnion' => [
                                [
                                    '$map' => [
                                        'input' => '$allTrainings',
                                        'as' => 'training',
                                        'in' => '$$training.level'
                                    ]
                                ]
                            ]
                        ],
                        'as' => 'level',
                        'in' => [
                            'k' => '$$level',
                            'v' => [
                                '$sum' => [
                                    '$map' => [
                                        'input' => '$technicians',
                                        'as' => 'technician',
                                        'in' => [
                                            '$ifNull' => [
                                                ['$getField' => ['field' => '$$level', 'input' => '$$technician.trainingsByLevel']],
                                                0
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'totalTrainingsByBrandAndLevel' => [
                '$arrayToObject' => [
                    '$map' => [
                        'input' => [
                            '$setUnion' => [
                                [
                                    '$map' => [
                                        'input' => '$allTrainings',
                                        'as' => 'training',
                                        'in' => '$$training.brand'
                                    ]
                                ]
                            ]
                        ],
                        'as' => 'brand',
                        'in' => [
                            'k' => '$$brand',
                            'v' => [
                                'totalByBrand' => [
                                    '$sum' => [
                                        '$map' => [
                                            'input' => '$technicians',
                                            'as' => 'technician',
                                            'in' => [
                                                '$reduce' => [
                                                    'input' => [
                                                        '$objectToArray' => [
                                                            '$ifNull' => [
                                                                ['$getField' => ['field' => '$$brand', 'input' => '$$technician.trainingsByBrandAndLevel']],
                                                                []
                                                            ]
                                                        ]
                                                    ],
                                                    'initialValue' => 0,
                                                    'in' => ['$add' => ['$$value', '$$this.v']]
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'totalByLevel' => [
                                    '$arrayToObject' => [
                                        '$map' => [
                                            'input' => [
                                                '$setUnion' => [
                                                    [
                                                        '$map' => [
                                                            'input' => '$allTrainings',
                                                            'as' => 'training',
                                                            'in' => '$$training.level'
                                                        ]
                                                    ]
                                                ]
                                            ],
                                            'as' => 'level',
                                            'in' => [
                                                'k' => '$$level',
                                                'v' => [
                                                    '$sum' => [
                                                        '$map' => [
                                                            'input' => '$technicians',
                                                            'as' => 'technician',
                                                            'in' => [
                                                                '$ifNull' => [
                                                                    ['$getField' => ['field' => '$$level', 'input' => ['$getField' => ['field' => '$$brand', 'input' => '$$technician.trainingsByBrandAndLevel']]]],
                                                                    0
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    // Étape 7 : Projection finale
    [
        '$project' => [
            '_id' => 0,
            'managerDetails' => [
                'firstName' => '$firstName',
                'lastName' => '$lastName'
            ],
            'totalManagers' => 1,
            'totalTechnicians' => 1,
            'technicians' => 1,
            'totalDistinctBrands' => 1,
            'totalTrainings' => 1,
            'totalTrainingsByLevel' => 1,
            'totalTrainingsByBrandAndLevel' => 1
        ]
    ]
];

// Exécution du pipeline et renvoi des résultats
try {
    $result = $academy->users->aggregate($pipeline)->toArray();

    if (empty($result)) {
        echo json_encode(["error" => "Aucune donnée trouvée pour ce manager."]);
        exit();
    }

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\MongoDB\Exception\Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Erreur lors de l'exécution du pipeline: " . $e->getMessage()]);
    exit();
}
?>
