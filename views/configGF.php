<?php
// configGF.php

return [
    'functionalGroupsByLevel' => [
        'Junior' => [
            'Moteur Diesel', 'Moteur Essence', 'Moteur Thermique', 'Moteur Electrique',
            'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Vitesse Automatique',
            'Boite de Vitesse à Variation Continue', 'Boite de Transfert', 'Pont', 'Reducteur',
            'Arbre de Transmission', 'Demi Arbre de Roue', 'Direction', 'Freinage',
            'Freinage Hydraulique', 'Freinage Electromagnétique', 'Freinage Pneumatique',
            'Suspension à Lame', 'Suspension Ressort', 'Suspension Pneumatique', 'Suspension',
            'Hydraulique', 'Electricité et Electronique', 'Climatisation', 'Assistance à la Conduite',
            'Transversale'
        ],
        'Senior' => [
            'Moteur Diesel', 'Moteur Essence', 'Moteur Thermique', 'Moteur Electrique',
            'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Vitesse Automatique',
            'Boite de Vitesse à Variation Continue', 'Boite de Transfert', 'Pont', 'Reducteur',
            'Arbre de Transmission', 'Demi Arbre de Roue', 'Direction', 'Freinage',
            'Freinage Hydraulique', 'Freinage Electromagnétique', 'Freinage Pneumatique',
            'Suspension à Lame', 'Suspension Ressort', 'Suspension Pneumatique', 'Suspension',
            'Hydraulique', 'Electricité et Electronique', 'Climatisation', 'Assistance à la Conduite',
            'Transversale', 'Réseaux de Communication'
        ],
        'Expert' => [
            'Moteur Thermique', 'Moteur Electrique', 'Boite de Vitesse Mécanique',
            'Boite de Vitesse Automatique', 'Direction', 'Freinage', 'Suspension',
            'Hydraulique', 'Electricité et Electronique', 'Climatisation', 'Réseaux de Communication', "Transversale"
        ]
    ],
    'nonSupportedGroupsByBrand' => [
        "RENAULT TRUCK" => [
            "Moteur Essence",
            "Moteur Electrique",
            "Boite de Vitesse à Variation Continue",
            "Boite de Vitesse Automatique",
            "Freinage Hydraulique",
            "Freinage Electromagnétique",
            "Suspension Ressort"
        ],
        "SINOTRUK" => [
            "Moteur Essence",
            "Moteur Electrique",
            "Boite de Vitesse à Variation Continue",
            "Boite de Vitesse Automatique",
            "Boite de Transfert",
            "Freinage Hydraulique",
            "Freinage Electromagnétique",
            "Suspension Ressort",
            "Suspension Pneumatique"
        ],
        "MERCEDES TRUCK" => [
            "Moteur Essence",
            "Moteur Electrique",
            "Boite de Vitesse à Variation Continue",
            "Boite de Vitesse Automatique",
            "Freinage Hydraulique",
            "Freinage Electromagnétique",
            "Suspension Ressort"
        ],
        "FUSO" => [
            "Moteur Essence",
            "Moteur Electrique",
            "Boite de Vitesse à Variation Continue",
            "Boite de Vitesse Automatique",
            "Boite de Transfert",
            "Freinage Hydraulique",
            "Freinage Electromagnétique",
            "Suspension Ressort",
            "Suspension Pneumatique",
            "Hydraulique",
            "Pneumatique"
        ],
        "HINO" => [
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
        "KING LONG" => [
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
        "LOVOL" => [
            "Moteur Essence",
            "Moteur Electrique",
            "Boite de Vitesse à Variation Continue",
            "Boite de Vitesse Automatique",
            "Freinage Electromagnétique",
            "Freinage Pneumatique",
            "Suspension à Lame",
            "Suspension Ressort",
            "Suspension Pneumatique",
            "Suspension"
        ],
        "JCB" => [
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
        "TOYOTA BT" => [
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
            "Suspension Pneumatique"
        ],
        "TOYOTA FORKLIFT" => [
            "Arbre de Transmission",
            "Boite de Transfert",
            "Boite de Vitesse Mécanique",
            "Boite de Vitesse à Variation Continue",
            "Freinage Pneumatique",
            "Pneumatique",
            "Suspension",
            "Suspension à Lame",
            "Suspension Pneumatique",
            "Suspension Ressort"
        ]
    ]
];

?>
