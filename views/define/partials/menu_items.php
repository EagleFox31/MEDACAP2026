<?php
// menu_items.php

$menuItems = [
    "Paramètres" => [
        "assign_roles" => [
            "name" => "Gestion des Accès",
            "icon" => "fas fa-users-cog",
            "icon_type" => "font_awesome",
            "url" => "./managePermissions.php",
            "order" => 1
        ],
        "set_thresholds" => [
            "name" => "Configurer les Seuils",
            "icon" => "fas fa-sliders-h",
            "icon_type" => "font_awesome",
            "url" => "./setThresholds.php",
            "order" => 2
        ],
        "define_skills_objectives" => [
            "name" => "Objectifs de Compétences",
            "icon" => "fas fa-bullseye",
            "icon_type" => "font_awesome",
            "url" => "./defineSkillsObjectives.php",
            "order" => 3
        ],

    ],
    "Formations" => [
        "register_programs" => [
            "name" => "Enregistrer les Programmes",
            "icon" => "ki-duotone ki-abstract-26",
            "icon_type" => "ki_duotone",
            "url" => "./createTraining.php",
            "order" => 1
        ],
        "generate_plans" => [
            "name" => "Générer les Plans",
            "icon" => "ki-duotone ki-abstract-26",
            "icon_type" => "ki_duotone",
            "url" => "./recommendations_table_copy.php",
            "order" => 2
        ],
        "validate_plans" => [
            "name" => "Validation des Plans",
            "icon" => "ki-duotone ki-abstract-26",
            "icon_type" => "ki_duotone",
            "url" => "#",
            "order" => 3
        ],
        "prioritize_plans" => [
            "name" => "Prioriser les Plans",
            "icon" => "ki-duotone ki-abstract-26",
            "icon_type" => "ki_duotone",
            "url" => "#",
            "order" => 4
        ],
        "propose_priorities" => [
            "name" => "Proposer des Priorités",
            "icon" => "ki-duotone ki-abstract-26",
            "icon_type" => "ki_duotone",
            "url" => "#",
            "order" => 5
        ],
        "adjust_team_calendars" => [
            "name" => "Ajuster les Calendriers",
            "icon" => "ki-duotone ki-abstract-26",
            "icon_type" => "ki_duotone",
            "url" => "#",
            "order" => 6
        ],
    ],
    "Suivi des Plans" => [
        "consult_group_plans" => [
            "name" => "Consulter les Plans de Groupe",
            "icon" => "fas fa-users-cog",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 1
        ],
        "consult_branch_plans" => [
            "name" => "Consulter les Plans de Filiale",
            "icon" => "fas fa-building",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 2
        ],
        "consult_team_plans" => [
            "name" => "Consulter les Plans par Équipe",
            "icon" => "fas fa-users",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 3
        ],
        "consult_individual_plans" => [
            "name" => "Consulter les Plans Individuels",
            "icon" => "fas fa-user",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 4
        ],
        // Ajouter d'autres fonctionnalités du groupe "Suivi des Plans" ici...
    ],
    "Suivi des Progressions" => [
        "track_technician_progress" => [
            "name" => "Suivi des Techniciens",
            "icon" => "fas fa-chart-pie",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 1
        ],
        "track_branch_progress" => [
            "name" => "Suivi par Filiale",
            "icon" => "fas fa-chart-bar",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 2
        ],
        "track_group_plan_evolution" => [
            "name" => "Évolution des Plans Groupe",
            "icon" => "fas fa-chart-line",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 3
        ],
        "track_branch_plan_evolution" => [
            "name" => "Évolution des Plans Filiale",
            "icon" => "fas fa-chart-bar",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 4
        ],
        "track_team_progress" => [
            "name" => "Suivi de l'Équipe",
            "icon" => "fas fa-users",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 5
        ],
        "track_individual_progress" => [
            "name" => "Suivi Individuel",
            "icon" => "fas fa-user-check",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 6
        ],
       
    ],
    "Suivi des Besoins" => [
        "consult_global_needs" => [
            "name" => "Consulter les Besoins Globaux",
            "icon" => "fas fa-globe",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 1
        ],
        "consult_branch_needs" => [
            "name" => "Consulter les Besoins par Filiale",
            "icon" => "fas fa-building",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 2
        ],
        "consult_individual_needs" => [
            "name" => "Consulter les Besoins Individuels",
            "icon" => "fas fa-user",
            "icon_type" => "font_awesome",
            "url" => "#",
            "order" => 3
        ],
        
    ],
   
];
?>
