<?php
//define/core/constants.php
/**
 * Constantes globales de l'application
 */

// Niveaux de techniciens
define('TECH_LEVEL_JUNIOR', 'Junior');
define('TECH_LEVEL_SENIOR', 'Senior');
define('TECH_LEVEL_EXPERT', 'Expert');

// Profils utilisateurs
define('PROFILE_ADMIN', 'Super Admin');
define('PROFILE_ADMIN_LOCAL', 'Admin');
define('PROFILE_DIRECTOR_GROUP', 'Directeur Groupe');
define('PROFILE_DIRECTOR_GENERAL', 'Directeur Général');
define('PROFILE_DIRECTOR_SERVICE', 'Directeur Pièce et Service');
define('PROFILE_DIRECTOR_OPERATIONS', 'Directeur des Opérations');
define('PROFILE_MANAGER', 'Manager');
define('PROFILE_TECHNICIAN', 'Technicien');
define('PROFILE_HR', 'Ressource Humaine');

// Types de tests et évaluations
define('TEST_TYPE_FACTUAL', 'Factuel');
define('TEST_TYPE_DECLARATIVE', 'Declaratif');
define('TEST_TYPE_TRAINING', 'Training');

// Statuts
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_PENDING', 'pending');
define('STATUS_VALIDATED', 'validated');
define('STATUS_REJECTED', 'rejected');

// Types de compétences
define('SKILL_TYPE_KNOWLEDGE', 'knowledge');
define('SKILL_TYPE_KNOW_HOW', 'knowhow');

// Seuils de score
define('SCORE_THRESHOLD_LOW', 60);
define('SCORE_THRESHOLD_MEDIUM', 80);
define('SCORE_THRESHOLD_HIGH', 90);

// Durées (en jours)
define('DURATION_EVALUATION_VALIDITY', 365); // Validité d'une évaluation
define('DURATION_TRAINING_DEFAULT', 5); // Durée par défaut d'une formation

// Codes de couleur
define('COLOR_SUCCESS', '#28a745');
define('COLOR_WARNING', '#ffc107');
define('COLOR_DANGER', '#dc3545');
define('COLOR_INFO', '#17a2b8');
define('COLOR_PRIMARY', '#007bff');
define('COLOR_SECONDARY', '#6c757d');
define('COLOR_JUNIOR', '#17a2b8');
define('COLOR_SENIOR', '#6c757d');
define('COLOR_EXPERT', '#343a40');

// Paramètres de pagination
define('ITEMS_PER_PAGE', 10);
define('MAX_PAGINATION_LINKS', 5);

// Chemins des ressources
define('BRAND_LOGOS_PATH', '../assets/img/brands/');
define('USER_AVATARS_PATH', '../assets/img/avatars/');

// Paramètres d'e-mail
define('EMAIL_FROM', 'noreply@medacap.com');
define('EMAIL_ADMIN', 'admin@medacap.com');

/**
 * Tableau des profils autorisés à accéder aux tableaux de bord
 */
$AUTHORIZED_DASHBOARD_PROFILES = [
    PROFILE_ADMIN,
    PROFILE_ADMIN_LOCAL,
    PROFILE_DIRECTOR_GROUP,
    PROFILE_DIRECTOR_GENERAL,
    PROFILE_DIRECTOR_SERVICE,
    PROFILE_DIRECTOR_OPERATIONS,
    PROFILE_MANAGER,
    PROFILE_TECHNICIAN,
    PROFILE_HR
];

/**
 * Tableau des profils de gestion (directeurs et managers)
 */
$MANAGEMENT_PROFILES = [
    PROFILE_DIRECTOR_GROUP,
    PROFILE_DIRECTOR_GENERAL,
    PROFILE_DIRECTOR_SERVICE,
    PROFILE_DIRECTOR_OPERATIONS,
    PROFILE_MANAGER
];

/**
 * Tableau des profils administrateurs
 */
$ADMIN_PROFILES = [
    PROFILE_ADMIN,
    PROFILE_ADMIN_LOCAL
];

// Rendre les tableaux globaux
$GLOBALS['AUTHORIZED_DASHBOARD_PROFILES'] = $AUTHORIZED_DASHBOARD_PROFILES;
$GLOBALS['MANAGEMENT_PROFILES'] = $MANAGEMENT_PROFILES;
$GLOBALS['ADMIN_PROFILES'] = $ADMIN_PROFILES;