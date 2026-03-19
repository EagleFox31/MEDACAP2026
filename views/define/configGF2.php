<?php
// configGF2.php

return [
    // 1. Liste Totale des Groupes
    'allGroups' => [
        'Moteur Diesel',
        'Moteur Essence',
        'Moteur Thermique',
        'Moteur Electrique',
        'Boite de Vitesse',
        'Boite de Vitesse Mécanique',
        'Boite de Vitesse Automatique',
        'Boite de Vitesse à Variation Continue',
        'Boite de Transfert',
        'Pont',
        'Reducteur',
        'Arbre de Transmission',
        'Demi Arbre de Roue',
        'Direction',
        'Freinage',
        'Freinage Hydraulique',
        'Freinage Electromagnétique',
        'Freinage Pneumatique',
        'Suspension à Lame',
        'Suspension Ressort',
        'Suspension Pneumatique',
        'Suspension',
        'Hydraulique',
        'Electricité et Electronique',
        'Climatisation',
        'Assistance à la Conduite',
        'Transversale',
        'Réseaux de Communication',
        'Pneumatique'
    ],

    // 2. Groupes Supportés par Niveau (importés de configGF.php)
    'supportedGroupsByLevel' => [
        'Junior' => [
            'Moteur Diesel',
            'Moteur Essence',
            'Moteur Thermique',
            'Moteur Electrique',
            'Boite de Vitesse',
            'Boite de Vitesse Mécanique',
            'Boite de Vitesse Automatique',
            'Boite de Vitesse à Variation Continue',
            'Boite de Transfert',
            'Pont',
            'Reducteur',
            'Arbre de Transmission',
            'Demi Arbre de Roue',
            'Direction',
            'Freinage',
            'Freinage Hydraulique',
            'Freinage Electromagnétique',
            'Freinage Pneumatique',
            'Suspension à Lame',
            'Suspension Ressort',
            'Suspension Pneumatique',
            'Suspension',
            'Hydraulique',
            'Electricité et Electronique',
            'Climatisation',
            'Assistance à la Conduite',
            'Transversale'
        ],
        'Senior' => [
            'Moteur Diesel',
            'Moteur Essence',
            'Moteur Thermique',
            'Moteur Electrique',
            'Boite de Vitesse',
            'Boite de Vitesse Mécanique',
            'Boite de Vitesse Automatique',
            'Boite de Vitesse à Variation Continue',
            'Boite de Transfert',
            'Pont',
            'Reducteur',
            'Arbre de Transmission',
            'Demi Arbre de Roue',
            'Direction',
            'Freinage',
            'Freinage Hydraulique',
            'Freinage Electromagnétique',
            'Freinage Pneumatique',
            'Suspension à Lame',
            'Suspension Ressort',
            'Suspension Pneumatique',
            'Suspension',
            'Hydraulique',
            'Electricité et Electronique',
            'Climatisation',
            'Assistance à la Conduite',
            'Transversale',
            'Réseaux de Communication'
        ],
        'Expert' => [
            'Moteur Thermique',
            'Moteur Electrique',
            'Boite de Vitesse Mécanique',
            'Boite de Vitesse Automatique',
            'Direction',
            'Freinage',
            'Suspension',
            'Hydraulique',
            'Electricité et Electronique',
            'Climatisation',
            'Réseaux de Communication'
        ]
    ],

    // 3. Groupes Non Supportés par Marque et par Niveau
    'nonSupportedGroupsByBrandAndLevel' => [
        // 3.1. Renault Truck
        "RENAULT TRUCK" => [
            'Junior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort"
            ],
            'Senior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort"
            ],
            'Expert' => [
                "Arbre de Transmission",
                "Assistance à la Conduite",
                "Boite de Transfert",
                "Boite de Vitesse",
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Demi Arbre de Roue",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Freinage Pneumatique",
                "Moteur Diesel",
                "Moteur Electrique",
                "Moteur Essence",
                "Moteur Thermique",
                "Pneumatique",
                "Pont",
                "Reducteur",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Suspension à Lame"
            ]
        ],

        // 3.2. SINOTRUK
        "SINOTRUK" => [
            'Junior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Hydraulique",
                "Freinage Electromagnétique"
            ],
            'Senior' => [
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Moteur Essence",
                "Moteur Electrique"
            ],

            'Expert' => [
                "Boite de Transfert",
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Moteur Essence",
                "Moteur Electrique",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Arbre de Transmission",
                "Demi Arbre de Roue",
                "Hydraulique",
                "Pneumatique",
                "Suspension à Lame"
            ]

        ],

        // 3.3. Mercedes Truck
        "MERCEDES TRUCK" => [
            'Junior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort"
            ],
            'Senior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort"
            ],
            'Expert' => [
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Moteur Essence",
                "Moteur Electrique",
                "Suspension Ressort",
                "Arbre de Transmission",
                "Assistance à la Conduite",
                "Boite de Transfert",
                "Boite de Vitesse",
                "Demi Arbre de Roue",
                "Freinage Pneumatique",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont",
                "Pneumatique",
                "Reducteur",
                "Suspension à Lame",
                "Suspension Pneumatique"
            ]

        ],

        // 3.4. FUSO
        "FUSO" => [
            'Junior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Boite de Transfert",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort",
                "Suspension Pneumatique",
                "Climatisation"
            ],
            'Senior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Boite de Transfert",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort",
                "Suspension Pneumatique",
                "Hydraulique"
            ],
            'Expert' => [
                "Boite de Transfert",
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Hydraulique",
                "Moteur Essence",
                "Moteur Electrique",
                "Pneumatique",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Arbre de Transmission",
                "Assistance à la Conduite",
                "Boite de Vitesse",
                "Demi Arbre de Roue",
                "Freinage Pneumatique",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont",
                "Reducteur",
                "Suspension à Lame"
            ]

        ],

        // 3.5. HINO
        "HINO" => [
            'Junior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Boite de Transfert",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort",
                "Suspension Pneumatique",
                "Climatisation"
            ],
            'Senior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Vitesse Automatique",
                "Boite de Transfert",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Suspension Ressort",
                "Suspension Pneumatique",
                "Hydraulique"
            ],
            'Expert' => [
                "Boite de Transfert",
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Hydraulique",
                "Moteur Essence",
                "Moteur Electrique",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Arbre de Transmission",
                "Assistance à la Conduite",
                "Boite de Vitesse",
                "Demi Arbre de Roue",
                "Freinage Pneumatique",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont",
                "Pneumatique",
                "Reducteur",
                "Suspension à Lame"
            ]

        ],

        // 3.6. KING LONG
        "KING LONG" => [
            'Junior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",
                "Boite de Transfert",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Hydraulique"
            ],
            'Senior' => [
                "Moteur Essence",
                "Moteur Electrique",
                "Boite de Vitesse à Variation Continue",

                "Boite de Transfert",
                "Freinage Hydraulique",
                "Freinage Electromagnétique",
                "Pneumatique",
                "Hydraulique"
            ],
            'Expert' => [
                "Boite de Transfert",
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Hydraulique",
                "Moteur Essence",
                "Moteur Electrique",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Arbre de Transmission",
                "Demi Arbre de Roue",
                "Pneumatique",
                "Suspension à Lame"
            ]

        ],

        // 3.7. LOVOL
        "LOVOL" => [
            'Junior' => [
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Suspension Pneumatique",
                "Freinage Electromagnétique",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Electrique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Ressort",
                "Electricité et Electronique",
                "Pneumatique"
            ],

            'Senior' => [
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Electrique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Pneumatique"
            ],
            'Expert' => [
                "Freinage Electromagnétique",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Electrique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Demi Arbre de Roue",
                "Pont",
                "Pneumatique",
                "Reducteur",
                "Suspension Ressort"
            ]

        ],

        // 3.8. JCB
        "JCB" => [
            'Junior' => [
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Suspension Pneumatique",
                "Freinage Electromagnétique",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Electrique",
                "Pneumatique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Ressort"
            ],
            'Senior' => [
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Electromagnétique",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Electrique",
                "Pneumatique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Suspension Ressort"
            ],
            'Expert' => [
                "Demi Arbre de Roue",
                "Freinage Electromagnétique",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Electrique",
                "Pont",
                "Pneumatique",
                "Reducteur",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Arbre de Transmission",
                "Boite de Transfert",
                "Boite de Vitesse Automatique",
                "Boite de Vitesse à Variation Continue"
            ]


        ],

        // 3.9. TOYOTA BT
        "TOYOTA BT" => [
            'Junior' => [
                "Arbre de Transmission",
                "Boite de Transfert",
                "Boite de Vitesse Automatique",
                "Boite de Vitesse Mécanique",
                "Boite de Vitesse à Variation Continue",
                "Demi Arbre de Roue",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Freinage"
            ],
            'Senior' => [
                "Arbre de Transmission",
                "Boite de Transfert",
                "Boite de Vitesse Automatique",
                "Boite de Vitesse Mécanique",
                "Boite de Vitesse à Variation Continue",
                "Demi Arbre de Roue",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Boite de Vitesse",
                "Climatisation",
                "Freinage",
                "Freinage Hydraulique",
                "Pneumatique"
            ],
            'Expert' => [
                "Arbre de Transmission",
                "Boite de Transfert",
                "Boite de Vitesse Automatique",
                "Boite de Vitesse Mécanique",
                "Boite de Vitesse à Variation Continue",
                "Demi Arbre de Roue",
                "Freinage Pneumatique",
                "Moteur Essence",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Climatisation",
                "Freinage",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Moteur Electrique",
                "Pneumatique",
                "Reducteur",
                "Suspension Ressort"
            ]

        ],

        // 3.10. TOYOTA FORKLIFT
        "TOYOTA FORKLIFT" => [
            'Junior' => [
                "Arbre de Transmission",
                "Boite de Transfert",
                "Boite de Vitesse Mécanique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Pneumatique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Boite de Vitesse Automatique",
                "Demi Arbre de Roue",
                "Freinage",
                "Moteur Essence",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont"
            ],
            'Senior' => [
                "Arbre de Transmission",
                "Boite de Transfert",
                "Boite de Vitesse Mécanique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Pneumatique",
                "Pneumatique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Boite de Vitesse",
                "Boite de Vitesse Automatique",
                "Climatisation",
                "Demi Arbre de Roue",
                "Freinage",
                "Freinage Hydraulique",
                "Moteur Essence",
                "Moteur Diesel",
                "Moteur Thermique",
                "Pont"
            ],
            'Expert' => [
                "Arbre de Transmission",
                "Boite de Transfert",
                "Boite de Vitesse Mécanique",
                "Boite de Vitesse à Variation Continue",
                "Freinage Pneumatique",
                "Pneumatique",
                "Suspension",
                "Suspension à Lame",
                "Suspension Pneumatique",
                "Suspension Ressort",
                "Boite de Vitesse Automatique",
                "Climatisation",
                "Demi Arbre de Roue",
                "Freinage",
                "Freinage Electromagnétique",
                "Freinage Hydraulique",
                "Moteur Essence",
                "Moteur Diesel",
                "Moteur Electrique",
                "Moteur Thermique",
                "Pont",
                "Reducteur"
            ]

        ],

    ]
];
