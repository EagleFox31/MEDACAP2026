<?php
/**
 * Fichier unique — Briques 1→5 refactor + nouvelles règles + Strategy/Factory/AJAX (PHP 7.3 / MongoDB 4.2)
 * - SRP/POO, render(), username/password auto (2 premières lettres du prénom + nom + matricule ; pwd = nom + 4 derniers matricule + "!")
 * - PAS de générateur de matricule (saisi manuellement)
 * - Profil Candidat (Factuel only)
 * - Champs techniques (brands/specialities/certificate) persistés UNIQUEMENT pour Technicien & Candidat
 * - UI pays Afrique uniquement
 * - Strategy Pattern + Factory Method pour la création des profils
 * - Endpoint AJAX: configuration profil (profile_config) UNIQUEMENT
 */

session_start();
include_once "../language.php";
require_once "../../vendor/autoload.php";
require_once "../sendMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

/* ==========================
|  Garde de session
|========================== */
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

/* ==========================
|  Constantes
|========================== */
const AFRICAN_COUNTRIES = [
    'Afrique du Sud','Algérie','Angola','Bénin','Botswana','Burkina Faso','Burundi','Cabo Verde','Cameroun',
    'République Centrafricaine','Tchad','Comores','Congo','République Démocratique du Congo','Djibouti','Égypte',
    'Érythrée','Eswatini','Éthiopie','Gabon','Gambie','Ghana','Guinée','Guinée-Bissau',"Côte d'Ivoire",'Kenya',
    'Lesotho','Liberia','Libye','Madagascar','Malawi','Mali','Île Maurice','Mauritanie','Maroc','Mozambique',
    'Namibie','Niger','Nigéria','Rwanda','Sao Tomé-et-Principe','Sénégal','Seychelles','Sierra Leone','Somalie',
    'Soudan','Soudan du Sud','Tanzanie','Togo','Tunisie','Zambie','Zimbabwe'
];

const BRANDS_EQUIPMENT = [
    'FUSO','HINO','JCB','KING LONG','LOVOL','MERCEDES TRUCK','RENAULT TRUCK','SINOTRUCK','TOYOTA BT','TOYOTA FORKLIFT'
];
const BRANDS_MOTORS = [
    'BYD','CITROEN','MERCEDES','MITSUBISHI','PEUGEOT','SUZUKI','TOYOTA','YAMAHA BATEAU','YAMAHA MOTO'
];

const SPECIALITIES_SENIOR = ['Electricté','Hydraulique','Moteur','Transmission'];
const SPECIALITIES_EXPERT = ['Electricté et Electronique','Hydraulique','Moteur','Transmission'];

/* ==========================
|  DB
|========================== */
final class Db {
    public static $users;
    public static $vehicles;
    public static $tests;
    public static $allocations;
    public static $profiles;
    public static $specialities;

    public static function init(): void {
        // Compatible MongoDB 4.2
        $client = new Client("mongodb://localhost:27017");
        $db     = $client->academy;

        self::$users        = $db->users;
        self::$vehicles     = $db->vehicles;
        self::$tests        = $db->tests;
        self::$allocations  = $db->allocations;
        self::$profiles     = $db->profiles;
        self::$specialities = $db->specialities;
    }
}
Db::init();

/* ==========================
|  IO & Validation
|========================== */
final class Input {
    public static function isPost(): bool {
        return (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');
    }
    public static function post(string $key, $default = null) {
        if (!isset($_POST[$key])) return $default;
        return self::sanitize($_POST[$key]);
    }
    private static function sanitize($v) {
        if (is_array($v)) {
            foreach ($v as $k=>$val) $v[$k]=self::sanitize($val);
            return $v;
        }
        if (!is_string($v)) return $v;
        $v = filter_var($v, FILTER_SANITIZE_STRING);
        return trim($v);
    }
}
final class Validator {
    public static function required(array $data, array $fields): array {
        $missing = [];
        foreach ($fields as $f) if (!isset($data[$f]) || $data[$f]==='' || $data[$f]===null) $missing[]=$f;
        return $missing;
    }
    public static function email(?string $email): bool {
        if ($email === null || $email === '') return true;
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

/* ==========================
|  Username & Password
|========================== */
final class StringUtil {
    public static function ascii(string $s): string {
        $out = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
        return $out !== false ? $out : $s;
    }
    public static function onlyAlnum(string $s): string {
        return preg_replace('/[^A-Za-z0-9]/', '', $s);
    }
}

final class UsernameFactory {
    // 2 premières lettres du prénom + nom + matricule (sans accents), minuscules
    public static function base(string $first, string $last, $matricule): string {
        $fn = strtolower(StringUtil::onlyAlnum(StringUtil::ascii($first)));
        $ln = strtolower(StringUtil::onlyAlnum(StringUtil::ascii($last)));
        $p2 = substr($fn, 0, 2);
        $m2 = substr((string)$matricule, 2);
        return strtolower($p2 . $ln . $m2);
    }
    public static function unique(string $first, string $last, $matricule): string {
        $base = self::base($first,$last,$matricule);
        $candidate = $base; $i=1;
        while (UserService::existsActiveUsername($candidate)) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }
}

final class PasswordFactory {
    public static function buildVisible(string $lastName, $matricule): string {
        // Normalisation nom
        $ln = ucfirst(strtolower(preg_replace('/\s+/', '', StringUtil::ascii($lastName))));

        // Matricule -> 2 premiers + 2 derniers chiffres
        $mStr   = preg_replace('/\D/', '', (string)$matricule);
        $m2f    = substr($mStr, 0, 2);
        $m2l    = substr($mStr, -2);

        // Bloc lisible
        $core   = $ln . $m2f . $m2l . '@';

        // Suffixe aléatoire (longueur ajustée pour atteindre 14+ caractères)
        $needed = max(14 - strlen($core), 6); // minimum 6 chars random
        $suffix = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, $needed);

        return $core . $suffix;
    }

    public static function buildHashed(string $visiblePassword): string {
        // Hash SHA-1
        return sha1($visiblePassword);
    }
}



/* ==========================
|  Mailer
|========================== */
final class Mailer {
    public static function notifyManagerForTechnician(array $manager, array $technician): void {
        if (empty($manager['email'])) return;

        $to = (string)$manager['email'];
        $fn = htmlspecialchars((string)$technician['firstName'], ENT_QUOTES, 'UTF-8');
        $ln = htmlspecialchars((string)$technician['lastName'], ENT_QUOTES, 'UTF-8');
        $lvl = htmlspecialchars((string)($technician['level'] ?? ''), ENT_QUOTES, 'UTF-8');
        $subs = htmlspecialchars((string)($technician['subsidiary'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dept = htmlspecialchars((string)($technician['department'] ?? ''), ENT_QUOTES, 'UTF-8');
        $agency = htmlspecialchars((string)($technician['agency'] ?? ''), ENT_QUOTES, 'UTF-8');
        $mFirst = htmlspecialchars((string)($manager['firstName'] ?? ''), ENT_QUOTES, 'UTF-8');
        $mLast = htmlspecialchars((string)($manager['lastName'] ?? 'Manager'), ENT_QUOTES, 'UTF-8');

        $subject = '🎯 MEDACAP — Nouveau talent dans votre équipe !';
        
        $emailData = [
            'companyName'    => 'MEDACAP',
            'companyTagline' => 'CFAO Mobility Academy',
            'userName'       => trim($mFirst . ' ' . $mLast),
            'title'          => 'Collaborateur activé',
            'icon'           => '🚀',
            'message'        => "Excellente nouvelle ! <strong>{$fn} {$ln}</strong> vient de rejoindre officiellement votre équipe sur MEDACAP et est prêt à démarrer son parcours d'excellence.<br><br>
                🎯 <strong>Mission :</strong> Accompagner ce talent vers la certification<br>
                📊 <strong>Objectif :</strong> Développer ses compétences techniques<br>
                ⚡ <strong>Impact :</strong> Renforcer la performance de votre équipe<br><br>
                Votre rôle de catalyseur commence maintenant : guidez-le vers l'excellence et la reconnaissance de ses compétences.",
            'details' => [
                ['label' => 'Collaborateur', 'value' => $fn . ' ' . $ln, 'highlight' => true],
                ['label' => 'Profil', 'value' => 'Technicien'],
                ['label' => 'Niveau', 'value' => $lvl ?: 'À définir'],
                ['label' => 'Filiale', 'value' => $subs ?: 'Non spécifiée'],
                ['label' => 'Département', 'value' => $dept ?: 'Non spécifié'],
                ['label' => 'Agence', 'value' => $agency ?: 'Non spécifiée'],
            ],
            'ctaText' => 'Suivre mon équipe',
            'ctaLink' => medacapLinkForCountry(isset($technician['country']) ? (string)$technician['country'] : null),
            'additionalInfo' => "🔥 <strong>Action requise :</strong> Complétez vos QCM Tâches Professionnelles Manager pour optimiser votre accompagnement et débloquer tout le potentiel de votre collaborateur.",
            'supportEmail' => SUPER_ADMIN_EMAIL,
        ];

        sendTemplatedMedacapEmail($to, $subject, $emailData);
    }

    public static function notifyCandidateRecruitment(string $email, string $firstName, string $lastName, string $username, string $visiblePwd, ?string $country): void {
        if (!$email) return;

        $to = $email;
        $fn = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $ln = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
        $user = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $pwd = htmlspecialchars($visiblePwd, ENT_QUOTES, 'UTF-8');
        $link = medacapLinkForCountry($country);

        $subject = '🎓 MEDACAP — Votre chance de briller commence maintenant !';
        
        $emailData = [
            'companyName'    => 'MEDACAP',
            'companyTagline' => 'CFAO Mobility Academy',
            'userName'       => $fn . ' ' . $ln,
            'title'          => 'Tests de recrutement activés',
            'icon'           => '⚡',
            'message'        => "Le moment que vous attendiez est arrivé ! Vous êtes officiellement convoqué(e) aux <strong>tests de recrutement MEDACAP</strong> pour votre candidature de stage.<br><br>
                🎯 <strong>Mission :</strong> Démontrer vos compétences factuelles<br>
                💡 <strong>Opportunité :</strong> Prouver votre potentiel technique<br>
                🚪 <strong>Objectif :</strong> Ouvrir la porte à votre avenir professionnel<br><br>
                C'est votre moment de vérité. Chaque question est une occasion de montrer ce dont vous êtes capable. Saisissez cette chance !",
            'details' => [
                ['label' => 'Type de test', 'value' => 'QCM Factuels (Stage)', 'highlight' => true],
                ['label' => 'Identifiant', 'value' => $user, 'highlight' => true],
                ['label' => 'Mot de passe', 'value' => $pwd, 'highlight' => true],
                ['label' => 'Plateforme', 'value' => 'MEDACAP - Tests en ligne'],
                ['label' => 'Support', 'value' => SUPER_ADMIN_EMAIL],
            ],
            'ctaText' => 'Commencer mes tests',
            'ctaLink' => $link,
            'additionalInfo' => "⚡ <strong>Information importante :</strong> Vous passerez exclusivement des QCM <strong>Factuels</strong> adaptés au niveau sélectionné. Concentrez-vous sur vos connaissances techniques pour maximiser vos résultats.",
            'supportEmail' => SUPER_ADMIN_EMAIL,
        ];

        sendTemplatedMedacapEmail($to, $subject, $emailData);
    }

    public static function notifySelfAccess(string $email, string $lastName, string $firstName, string $username, string $visiblePwd, ?string $country, string $profileFlavor): void {
        if (!$email) return;

        $to = $email;
        $fn = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
        $ln = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
        $user = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $pwd = htmlspecialchars($visiblePwd, ENT_QUOTES, 'UTF-8');
        $link = medacapLinkForCountry($country);

        // Messages dynamiques selon le profil
        $profileMessages = [
            'Manager' => [
                'subject' => '🧑‍✈️ MEDACAP — Votre cockpit de management est prêt !',
                'title' => 'Accès Manager activé',
                'icon' => '🎛️',
                'message' => "Félicitations ! Vous venez d'être nommé <strong>Manager</strong> sur MEDACAP et votre tableau de bord stratégique est maintenant opérationnel.<br><br>
                    👥 <strong>Pouvoir :</strong> Pilotage et suivi de vos équipes en temps réel<br>
                    📊 <strong>Vision :</strong> Analyse des performances et des progressions<br>
                    🚀 <strong>Impact :</strong> Catalyseur de l'excellence de vos collaborateurs<br><br>
                    Vous êtes désormais le chef d'orchestre de la montée en puissance des compétences. Prenez les commandes !",
                'additionalInfo' => "🔥 <strong>Actions prioritaires :</strong> Vérifiez immédiatement la liste de vos utilisateurs et complétez vos QCM Tâches Professionnelles Manager pour débloquer tout votre potentiel de leadership."
            ],
            'Admin' => [
                'subject' => '🔐 MEDACAP — Vous prenez le contrôle de votre filiale !',
                'title' => 'Accès Administrateur activé',
                'icon' => '🧭',
                'message' => "Bravo ! Vous êtes désormais <strong>Administrateur</strong> MEDACAP avec des responsabilités stratégiques majeures.<br><br>
                    🎯 <strong>Rôle :</strong> Supervision globale et pilotage des évaluations<br>
                    📈 <strong>Mission :</strong> Garantir l'excellence opérationnelle de votre filiale<br>
                    ⚡ <strong>Pouvoir :</strong> Vision 360° sur les talents et leurs évolutions<br><br>
                    Vous êtes le gardien de la performance collective. Votre leadership fait la différence !",
                'additionalInfo' => "📊 <strong>Responsabilités clés :</strong> Consultez régulièrement la liste des utilisateurs, analysez les résultats des techniciens et assurez-vous que tous les processus d'évaluation sont optimisés."
            ],
            'Directeur' => [
                'subject' => '📊 MEDACAP — Votre vision stratégique est activée !',
                'title' => 'Accès Direction débloqué',
                'icon' => '📈',
                'message' => "Vous voici aux commandes ! Votre accès <strong>Direction</strong> MEDACAP vous donne une vision stratégique inégalée sur vos talents.<br><br>
                    💡 <strong>Intelligence :</strong> Décryptage des performances et potentiels<br>
                    🎯 <strong>Stratégie :</strong> Alignement compétences-objectifs business<br>
                    🚀 <strong>Leadership :</strong> Décisions éclairées par la data factuelle<br><br>
                    Transformez vos insights en actions concrètes pour propulser votre organisation vers l'excellence.",
                'additionalInfo' => "🔥 <strong>Avantage concurrentiel :</strong> Exploitez vos tableaux de bord pour identifier les forces, détecter les axes d'amélioration et orchestrer le développement stratégique de vos équipes."
            ]
        ];

        // Message par défaut si le profil n'est pas reconnu
        $defaultMessage = [
            'subject' => '✨ MEDACAP — Bienvenue dans l\'excellence !',
            'title' => 'Compte activé avec succès',
            'icon' => '🚀',
            'message' => "Bienvenue ! Vous êtes maintenant inscrit(e) en tant que <strong>{$profileFlavor}</strong> sur la plateforme MEDACAP.<br><br>
                🎯 <strong>Accès :</strong> Plateforme de développement des compétences<br>
                📊 <strong>Objectif :</strong> Excellence et certification professionnelle<br>
                ⚡ <strong>Impact :</strong> Montée en puissance de vos performances<br><br>
                Votre parcours vers l'excellence commence maintenant. Chaque étape vous rapproche de vos objectifs !",
            'additionalInfo' => "🔥 <strong>Première étape :</strong> Explorez votre espace personnalisé, consultez la liste des utilisateurs disponibles et familiarisez-vous avec les outils de suivi des résultats."
        ];

        $content = $profileMessages[$profileFlavor] ?? $defaultMessage;
        
        $emailData = [
            'companyName'    => 'MEDACAP',
            'companyTagline' => 'CFAO Mobility Academy',
            'userName'       => $fn . ' ' . $ln,
            'title'          => $content['title'],
            'icon'           => $content['icon'],
            'message'        => $content['message'],
            'details' => [
                ['label' => 'Profil', 'value' => $profileFlavor, 'highlight' => true],
                ['label' => 'Identifiant', 'value' => $user, 'highlight' => true],
                ['label' => 'Mot de passe', 'value' => $pwd, 'highlight' => true],
                ['label' => 'Plateforme', 'value' => 'MEDACAP - Interface ' . $profileFlavor],
                ['label' => 'Support', 'value' => SUPER_ADMIN_EMAIL],
            ],
            'ctaText' => 'Prendre le contrôle',
            'ctaLink' => $link,
            'additionalInfo' => $content['additionalInfo'],
            'supportEmail' => SUPER_ADMIN_EMAIL,
        ];

        sendTemplatedMedacapEmail($to, $content['subject'], $emailData);
    }
}


/* ==========================
|  Users
|========================== */
final class UserService {
    public static function createUser(array $payload, ?string $managerId, string $profileToPersist, bool $testFlag = true, bool $isTechOrCandidate=false): ObjectId {
        $doc = [
            "users"           => [],
            "username"        => $payload['username'], // généré serveur (à partir du matricule saisi)
            "matricule"       => (int)$payload['matricule'],
            "firstName"       => ucfirst($payload['firstName']),
            "lastName"        => ucfirst($payload['lastName']),
            "email"           => $payload['email'],
            "phone"           => $payload['phone'],
            "gender"          => $payload['gender'],
            "level"           => isset($payload['level']) ? $payload['level'] : "",
            "country"         => $payload['country'],
            "profile"         => $profileToPersist,
            "birthdate"       => $payload['birthdate'],
            "recrutmentDate"  => $payload['recrutmentDate'],
            "role"            => ucfirst($payload['role']),
            "password"        => $payload['password_hash'],
            "visiblePassword" => $payload['visiblePassword'],
            "manager"         => $managerId ? new ObjectId($managerId) : "",
            "test"            => $testFlag,
            "active"          => true,
            "created"         => date("d-m-Y H:i:s"),
            // Normalisé pour tout le monde
            "subsidiary"      => ucfirst($payload['subsidiary']),
            "agency"          => ucfirst($payload['agency']),
            "department"      => ucfirst($payload['department']),
        ];

        // Champs techniques — uniquement pour Technicien / Candidat
        if ($isTechOrCandidate) {
            $doc["certificate"]      = ucfirst($payload['certificate']);
            $doc["brandJunior"]      = isset($payload['brandJunior']) ? $payload['brandJunior'] : [];
            $doc["brandSenior"]      = isset($payload['brandSenior']) ? $payload['brandSenior'] : [];
            $doc["brandExpert"]      = isset($payload['brandExpert']) ? $payload['brandExpert'] : [];
            $doc["specialitySenior"] = isset($payload['specialitySenior']) ? $payload['specialitySenior'] : [];
            $doc["specialityExpert"] = isset($payload['specialityExpert']) ? $payload['specialityExpert'] : [];
        } else {
            $doc["certificate"]      = "";
            $doc["brandJunior"]      = [];
            $doc["brandSenior"]      = [];
            $doc["brandExpert"]      = [];
            $doc["specialitySenior"] = [];
            $doc["specialityExpert"] = [];
        }

        $res = Db::$users->insertOne($doc);
        $userId = $res->getInsertedId();

        if ($managerId) {
            Db::$users->updateOne(
                ["_id" => new ObjectId($managerId)],
                ['$push' => ["users" => new ObjectId($userId)]]
            );
        }
        return $userId;
    }

    public static function propagateBrands(ObjectId $userId, array $brandSenior, array $brandExpert): void {
        if (!empty($brandExpert)) {
            // Expert ► aussi Senior & Junior
            foreach ($brandExpert as $b) {
                Db::$users->updateOne(["_id"=>$userId], ['$addToSet'=>["brandSenior"=>$b]]);
                Db::$users->updateOne(["_id"=>$userId], ['$addToSet'=>["brandJunior"=>$b]]);
            }
        }
        if (!empty($brandSenior)) {
            // Senior ► aussi Junior
            foreach ($brandSenior as $b) {
                Db::$users->updateOne(["_id"=>$userId], ['$addToSet'=>["brandJunior"=>$b]]);
            }
        }
    }

    public static function getById(ObjectId $id): ?array {
        $doc = Db::$users->findOne(["_id" => $id, "active" => true]);
        return $doc ? (array)$doc : null;
    }
    public static function existsActiveUsername(string $username): bool {
        return (bool) Db::$users->findOne(['$and' => [["username"=>$username], ["active"=>true]]]);
    }
    public static function getActiveManager(?string $managerId): ?array {
        if (!$managerId) return null;
        $doc = Db::$users->findOne(['$and' => [["_id"=>new ObjectId($managerId)], ["active"=>true]]]);
        return $doc ? (array)$doc : null;
    }
}

/* ==========================
|  Tests & Allocations
|========================== */
final class TestPlanBuilder {
    const TYPE_FACTUEL     = 'Factuel';
    const TYPE_DECLARATIF  = 'Declaratif';
    const SPEC_FACTUELLE   = 'Factuelle';
    const SPEC_DECLARATIVE = 'Declarative';

    private static function createTest(ObjectId $userId, array $brandList, string $type, string $level): ObjectId {
        $doc = ["quizzes"=>[], "user"=>$userId, "brand"=>$brandList, "type"=>$type, "level"=>$level, "total"=>0, "active"=>true, "created"=>date("d-m-Y H:i:s")];
        return Db::$tests->insertOne($doc)->getInsertedId();
    }
    private static function addFromVehicles(ObjectId $testId, string $brand, string $type, string $level): void {
        $vehicle = Db::$vehicles->findOne(['$and' => [["brand"=>$brand], ["type"=>$type], ["level"=>$level], ["active"=>true]]]);
        if ($vehicle && isset($vehicle->quizzes) && is_array($vehicle->quizzes) && $vehicle->quizzes) {
            Db::$tests->updateOne(["_id"=>$testId], ['$addToSet'=>["quizzes"=>['$each'=>$vehicle->quizzes]]]);
        }
    }
    private static function addFromSpecialities(ObjectId $testId, string $label, string $typeSpec, string $level): void {
        $spec = Db::$specialities->findOne(['$and' => [["label"=>$label], ["type"=>$typeSpec], ["level"=>$level], ["active"=>true]]]);
        if ($spec && isset($spec->quizzes) && is_array($spec->quizzes) && $spec->quizzes) {
            Db::$tests->updateOne(["_id"=>$testId], ['$addToSet'=>["quizzes"=>['$each'=>$spec->quizzes]]]);
        }
    }
    private static function finalize(ObjectId $testId): void {
        $t = Db::$tests->findOne(["_id"=>$testId]);
        $total = ($t && isset($t->quizzes) && is_array($t->quizzes)) ? count($t->quizzes) : 0;
        Db::$tests->updateOne(["_id"=>$testId], ['$set'=>["total"=>$total]]);
    }
    private static function allocate(ObjectId $userId, ObjectId $testId, string $type, string $level, bool $activeTest, bool $activeManager=false): void {
        Db::$allocations->insertOne([
            "user"=>$userId,"test"=>$testId,"type"=>$type,"level"=>$level,
            "activeTest"=>$activeTest,"activeManager"=>$activeManager,"active"=>false,"created"=>date("d-m-Y H:i:s")
        ]);
    }

    /** Niveau Junior (Tech) — active immédiatement Factuel & Déclaratif Junior */
    public static function buildForJunior(ObjectId $userId, array $brandJunior, bool $activateJuniorTests = true): void {
        $tFac   = self::createTest($userId, $brandJunior, self::TYPE_FACTUEL,    'Junior');
        $tDecla = self::createTest($userId, $brandJunior, self::TYPE_DECLARATIF, 'Junior');
        foreach ($brandJunior as $b) {
            self::addFromVehicles($tFac,$b,self::TYPE_FACTUEL,'Junior');
            self::addFromVehicles($tDecla,$b,self::TYPE_DECLARATIF,'Junior');
        }
        self::finalize($tFac); self::finalize($tDecla);
        self::allocate($userId,$tFac,self::TYPE_FACTUEL,'Junior',$activateJuniorTests);
        self::allocate($userId,$tDecla,self::TYPE_DECLARATIF,'Junior',$activateJuniorTests,false);
    }

    /** Niveau Senior (Tech) — active rattrapage Junior, garde Senior inactif */
    public static function buildForSenior(ObjectId $userId, array $brandJunior, array $brandSenior, array $specSenior, bool $activateJuniorCatchup = true): void {
        // Junior catch-up
        $tJuF = self::createTest($userId,$brandJunior,self::TYPE_FACTUEL,'Junior');
        $tJuD = self::createTest($userId,$brandJunior,self::TYPE_DECLARATIF,'Junior');
        foreach ($brandJunior as $b) {
            self::addFromVehicles($tJuF,$b,self::TYPE_FACTUEL,'Junior');
            self::addFromVehicles($tJuD,$b,self::TYPE_DECLARATIF,'Junior');
        }
        self::finalize($tJuF); self::finalize($tJuD);
        self::allocate($userId,$tJuF,self::TYPE_FACTUEL,'Junior',$activateJuniorCatchup);
        self::allocate($userId,$tJuD,self::TYPE_DECLARATIF,'Junior',$activateJuniorCatchup,false);

        // Senior build (inactive)
        $tSeF = self::createTest($userId,$brandSenior,self::TYPE_FACTUEL,'Senior');
        $tSeD = self::createTest($userId,$brandSenior,self::TYPE_DECLARATIF,'Senior');
        foreach ($brandSenior as $b) {
            self::addFromVehicles($tSeF,$b,self::TYPE_FACTUEL,'Senior');
            self::addFromVehicles($tSeD,$b,self::TYPE_DECLARATIF,'Senior');
        }
        foreach ($specSenior as $s) {
            self::addFromSpecialities($tSeF,$s,self::SPEC_FACTUELLE,'Senior');
            self::addFromSpecialities($tSeD,$s,self::SPEC_DECLARATIVE,'Senior');
        }
        self::finalize($tSeF); self::finalize($tSeD);
        self::allocate($userId,$tSeF,self::TYPE_FACTUEL,'Senior',false);
        self::allocate($userId,$tSeD,self::TYPE_DECLARATIF,'Senior',false,false);
    }

    /** Niveau Expert (Tech) — active Junior, Senior & Expert restent inactifs (sauf Junior actif) */
    public static function buildForExpert(
        ObjectId $userId,
        array $brandJunior, array $brandSenior, array $brandExpert,
        array $specSenior, array $specExpert
    ): void {
        // Junior (actif)
        $tJuF = self::createTest($userId,$brandJunior,self::TYPE_FACTUEL,'Junior');
        $tJuD = self::createTest($userId,$brandJunior,self::TYPE_DECLARATIF,'Junior');
        foreach ($brandJunior as $b) {
            self::addFromVehicles($tJuF,$b,self::TYPE_FACTUEL,'Junior');
            self::addFromVehicles($tJuD,$b,self::TYPE_DECLARATIF,'Junior');
        }
        self::finalize($tJuF); self::finalize($tJuD);
        self::allocate($userId,$tJuF,self::TYPE_FACTUEL,'Junior',true);
        self::allocate($userId,$tJuD,self::TYPE_DECLARATIF,'Junior',true,false);

        // Senior (inactif)
        $tSeF = self::createTest($userId,$brandSenior,self::TYPE_FACTUEL,'Senior');
        $tSeD = self::createTest($userId,$brandSenior,self::TYPE_DECLARATIF,'Senior');
        foreach ($brandSenior as $b) {
            self::addFromVehicles($tSeF,$b,self::TYPE_FACTUEL,'Senior');
            self::addFromVehicles($tSeD,$b,self::TYPE_DECLARATIF,'Senior');
        }
        foreach ($specSenior as $s) {
            self::addFromSpecialities($tSeF,$s,self::SPEC_FACTUELLE,'Senior');
            self::addFromSpecialities($tSeD,$s,self::SPEC_DECLARATIVE,'Senior');
        }
        self::finalize($tSeF); self::finalize($tSeD);
        self::allocate($userId,$tSeF,self::TYPE_FACTUEL,'Senior',false);
        self::allocate($userId,$tSeD,self::TYPE_DECLARATIF,'Senior',false,false);

        // Expert (inactif)
        $tExF = self::createTest($userId,$brandExpert,self::TYPE_FACTUEL,'Expert');
        $tExD = self::createTest($userId,$brandExpert,self::TYPE_DECLARATIF,'Expert');
        foreach ($brandExpert as $b) {
            self::addFromVehicles($tExF,$b,self::TYPE_FACTUEL,'Expert');
            self::addFromVehicles($tExD,$b,self::TYPE_DECLARATIF,'Expert');
        }
        foreach ($specExpert as $s) {
            self::addFromSpecialities($tExF,$s,self::SPEC_FACTUELLE,'Expert');
            self::addFromSpecialities($tExD,$s,self::SPEC_DECLARATIVE,'Expert');
        }
        self::finalize($tExF); self::finalize($tExD);
        self::allocate($userId,$tExF,self::TYPE_FACTUEL,'Expert',false);
        self::allocate($userId,$tExD,self::TYPE_DECLARATIF,'Expert',false,false);
    }

    /** Candidat — Factuel uniquement, selon le niveau choisi */
    public static function buildForCandidate(ObjectId $userId, string $level, array $brands, array $specs): void {
        $tF = self::createTest($userId, $brands, self::TYPE_FACTUEL, $level);
        foreach ($brands as $b) self::addFromVehicles($tF,$b,self::TYPE_FACTUEL,$level);

        // Spécialités factuelles si Senior/Expert
        if ($level === 'Senior' && !empty($specs)) {
            foreach ($specs as $s) self::addFromSpecialities($tF,$s,self::SPEC_FACTUELLE,'Senior');
        }
        if ($level === 'Expert' && !empty($specs)) {
            foreach ($specs as $s) self::addFromSpecialities($tF,$s,self::SPEC_FACTUELLE,'Expert');
        }
        self::finalize($tF);
        self::allocate($userId,$tF,self::TYPE_FACTUEL,$level,true);
    }
}

/* ==========================
|  Render helpers (UI)
|========================== */
function renderOptions(array $items, ?array $selected = null): string {
    $buf = '';
    foreach ($items as $opt) {
        $sel = ($selected && in_array($opt,$selected)) ? ' selected' : '';
        $label = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
        $buf .= "<option value=\"{$label}\"{$sel}>{$label}</option>";
    }
    return $buf;
}

/* =================================================================
|  >>>>>>>>>  Strategy + Factory  (compatibles PHP 7.3)  <<<<<<
|================================================================= */
interface UserCreationStrategy {
    public function needsTechnicalFields(): bool;
    public function needsTestCreation(): bool;
    public function needsLevelSelection(): bool;
    public function create(array $payload, ?string $managerId): array;
}
class TechnicianStrategy implements UserCreationStrategy {
    public function needsTechnicalFields(): bool { return true; }
    public function needsTestCreation(): bool { return true; }
    public function needsLevelSelection(): bool { return true; }
    public function create(array $payload, ?string $managerId): array {
        $userId = UserService::createUser($payload, $managerId, "Technicien", true, true);
        UserService::propagateBrands($userId, isset($payload['brandSenior'])?$payload['brandSenior']:[], isset($payload['brandExpert'])?$payload['brandExpert']:[]);
        $level = $payload['level'];
        switch ($level) {
            case "Junior":
                TestPlanBuilder::buildForJunior($userId, $payload['brandJunior'], true); break;
            case "Senior":
                TestPlanBuilder::buildForSenior($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['specialitySenior'], true); break;
            case "Expert":
                TestPlanBuilder::buildForExpert($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['brandExpert'], $payload['specialitySenior'], $payload['specialityExpert']); break;
        }
        if ($managerId) {
            $manager = UserService::getActiveManager($managerId);
            $technician = UserService::getById($userId);
            if ($manager && $technician) Mailer::notifyManagerForTechnician($manager, $technician);
        }
        return ['success'=>true,'message'=>"Technicien créé avec succès."];
    }
}
class CandidateStrategy implements UserCreationStrategy {
    public function needsTechnicalFields(): bool { return true; }
    public function needsTestCreation(): bool { return true; }
    public function needsLevelSelection(): bool { return true; }
    public function create(array $payload, ?string $managerId): array {
        $userId = UserService::createUser($payload, $managerId, "Candidat", true, true);
        $level = $payload['level'];
        if ($level === "Junior") {
            TestPlanBuilder::buildForCandidate($userId, 'Junior', $payload['brandJunior'], []);
        } elseif ($level === "Senior") {
            TestPlanBuilder::buildForCandidate($userId, 'Senior', $payload['brandSenior'], $payload['specialitySenior']);
        } else {
            TestPlanBuilder::buildForCandidate($userId, 'Expert', $payload['brandExpert'], $payload['specialityExpert']);
        }
        Mailer::notifyCandidateRecruitment(
            $payload['email'], $payload['firstName'], $payload['lastName'],
            $payload['username'], $payload['visiblePassword'], $payload['country']
        );
        return ['success'=>true,'message'=>"Candidat créé avec succès."];
    }
}
class ManagerStrategy implements UserCreationStrategy {
    private $isTestable; // pas de propriétés typées en PHP 7.3
    public function __construct(bool $isTestable = false) { $this->isTestable = $isTestable; }
    public function needsTechnicalFields(): bool { return (bool)$this->isTestable; }
    public function needsTestCreation(): bool { return (bool)$this->isTestable; }
    public function needsLevelSelection(): bool { return (bool)$this->isTestable; }
    public function create(array $payload, ?string $managerId): array {
        $userId = UserService::createUser($payload, $managerId, "Manager", $this->isTestable, $this->isTestable);
        if ($this->isTestable && !empty($payload['level'])) {
            UserService::propagateBrands($userId, isset($payload['brandSenior'])?$payload['brandSenior']:[], isset($payload['brandExpert'])?$payload['brandExpert']:[]);
            switch ($payload['level']) {
                case "Junior": TestPlanBuilder::buildForJunior($userId, $payload['brandJunior'], true); break;
                case "Senior": TestPlanBuilder::buildForSenior($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['specialitySenior'], true); break;
                case "Expert": TestPlanBuilder::buildForExpert($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['brandExpert'], $payload['specialitySenior'], $payload['specialityExpert']); break;
            }
        }
        if ($managerId) {
            $manager = UserService::getActiveManager($managerId);
            $user = UserService::getById($userId);
            if ($manager && $user) Mailer::notifyManagerForTechnician($manager, $user);
        } else {
            Mailer::notifySelfAccess(
                $payload['email'], $payload['lastName'], $payload['firstName'],
                $payload['username'], $payload['visiblePassword'], $payload['country'], 'Manager'
            );
        }
        return ['success'=>true,'message'=> $this->isTestable ? "Manager testable créé." : "Manager créé."];
    }
}
class AdminStrategy implements UserCreationStrategy {
    public function needsTechnicalFields(): bool { return false; }
    public function needsTestCreation(): bool { return false; }
    public function needsLevelSelection(): bool { return false; }
    public function create(array $payload, ?string $managerId): array {
        UserService::createUser($payload, null, "Admin", false, false);
        Mailer::notifySelfAccess($payload['email'], $payload['lastName'], $payload['firstName'], $payload['username'], $payload['visiblePassword'], $payload['country'], 'Admin');
        return ['success'=>true,'message'=>"Admin créé."];
    }
}
class HRStrategy implements UserCreationStrategy {
    public function needsTechnicalFields(): bool { return false; }
    public function needsTestCreation(): bool { return false; }
    public function needsLevelSelection(): bool { return false; }
    public function create(array $payload, ?string $managerId): array {
        UserService::createUser($payload, null, "Ressource Humaine", false, false);
        Mailer::notifySelfAccess($payload['email'], $payload['lastName'], $payload['firstName'], $payload['username'], $payload['visiblePassword'], $payload['country'], 'RH');
        return ['success'=>true,'message'=>"RH créé."];
    }
}
class DirectorStrategy implements UserCreationStrategy {
    private $profileName; // pas de propriétés typées en PHP 7.3
    public function __construct(string $profileName) { $this->profileName = $profileName; }
    public function needsTechnicalFields(): bool { return false; }
    public function needsTestCreation(): bool { return false; }
    public function needsLevelSelection(): bool { return false; }
    public function create(array $payload, ?string $managerId): array {
        UserService::createUser($payload, null, $this->profileName, false, false);
        Mailer::notifySelfAccess($payload['email'], $payload['lastName'], $payload['firstName'], $payload['username'], $payload['visiblePassword'], $payload['country'], 'Direction');
        return ['success'=>true,'message'=>"Compte direction créé."];
    }
}

final class UserStrategyFactory {
    public static function create(string $profile, bool $isManagerTestable = false): UserCreationStrategy {
        // Pas de "match" (PHP 8) —> switch (compatible PHP 7.3)
        switch ($profile) {
            case 'Technicien':            return new TechnicianStrategy();
            case 'Candidat':              return new CandidateStrategy();
            case 'Manager':               return new ManagerStrategy($isManagerTestable);
            case 'Manager - Technicien':  return new ManagerStrategy(true); // legacy : toujours testable
            case 'Admin':                 return new AdminStrategy();
            case 'Ressource Humaine':     return new HRStrategy();
            case 'Directeur Pièce et Service':
            case 'Directeur des Opérations':
            case 'Directeur Général':
            case 'Directeur Groupe':      return new DirectorStrategy($profile);
            default: throw new InvalidArgumentException("Profil non supporté: ".$profile);
        }
    }
}

/* ==========================
|  Controller principal
|========================== */
final class UserController {
    private static function validateInput(array $data): array {
        $errors = [];
        // matricule requis (plus d'auto-génération)
        $required = ['firstName','lastName','matricule','department','agency','profile'];
        $missing = Validator::required($data, $required);
        if (!empty($missing)) $errors['general'] = "Champs obligatoires manquants: " . implode(', ', $missing);
        if (!empty($data['email']) && !Validator::email($data['email'])) $errors['email'] = "Email invalide.";
        return $errors;
    }
    private static function preparePayload(array $input): array {
        // matricule SAISI par l'utilisateur
        $matricule   = $input['matricule'];
        // Credentials auto basés sur le matricule saisi
        $username        = UsernameFactory::unique($input['firstName'], $input['lastName'], $matricule);
        $visiblePassword = PasswordFactory::buildVisible($input['lastName'], $matricule);
        $passwordHash = PasswordFactory::buildHashed($visiblePassword);

        // Dates formatées
        $birthdate  = !empty($input['birthdate']) ? date("d-m-Y", strtotime($input['birthdate'])) : "";
        $recrDate   = !empty($input['recrutmentDate']) ? date("d-m-Y", strtotime($input['recrutmentDate'])) : "";

        return [
            'username'         => $username,
            'matricule'        => $matricule,
            'firstName'        => $input['firstName'],
            'lastName'         => $input['lastName'],
            'email'            => isset($input['email']) ? $input['email'] : '',
            'phone'            => isset($input['phone']) ? $input['phone'] : '0',
            'gender'           => isset($input['gender']) ? $input['gender'] : '',
            'level'            => isset($input['level']) ? $input['level'] : '',
            'country'          => isset($input['country']) ? $input['country'] : '',
            'birthdate'        => $birthdate,
            'recrutmentDate'   => $recrDate,
            'certificate'      => isset($input['certificate']) ? $input['certificate'] : '',
            'subsidiary'       => $input['subsidiary'],
            'agency'           => $input['agency'],
            'department'       => $input['department'],
            'role'             => isset($input['role']) ? $input['role'] : '',
            'password_hash'    => $passwordHash,
            'visiblePassword'  => $visiblePassword,
            'brandJunior'      => isset($input['brandJu']) ? $input['brandJu'] : [],
            'brandSenior'      => isset($input['brandSe']) ? $input['brandSe'] : [],
            'brandExpert'      => isset($input['brandEx']) ? $input['brandEx'] : [],
            'specialitySenior' => isset($input['specialitySenior']) ? $input['specialitySenior'] : [],
            'specialityExpert' => isset($input['specialityExpert']) ? $input['specialityExpert'] : [],
        ];
    }
    public static function handleRequest(): array {
        if (!Input::isPost()) return ['success'=>false,'message'=>'Méthode non autorisée'];

        $input = [
            'firstName'         => Input::post("firstName"),
            'lastName'          => Input::post("lastName"),
            'matricule'         => Input::post("matricule"),
            'email'             => Input::post("email"),
            'phone'             => Input::post("phone"),
            'subsidiary'        => Input::post("subsidiary"),
            'department'        => Input::post("department"),
            'role'              => Input::post("role"),
            'profile'           => Input::post("profile"),
            'agency'            => Input::post("agency"),
            'gender'            => Input::post("gender"),
            'country'           => Input::post("country"),
            'certificate'       => Input::post("certificate"),
            'birthdate'         => Input::post("birthdate"),
            'recrutmentDate'    => Input::post("recrutmentDate"),
            'level'             => Input::post("level"),
            'brandJu'           => Input::post("brandJu", []),
            'brandSe'           => Input::post("brandSe", []),
            'brandEx'           => Input::post("brandEx", []),
            'specialitySenior'  => Input::post("specialitySenior", []),
            'specialityExpert'  => Input::post("specialityExpert", []),
            'manager'           => Input::post("manager"),
            'isManagerTestable' => Input::post("isManagerTestable") === '1'
        ];

        $errors = self::validateInput($input);
        if (!empty($errors)) return ['success'=>false,'errors'=>$errors];

        try {
            $payload  = self::preparePayload($input);
            $strategy = UserStrategyFactory::create($input['profile'], $input['isManagerTestable']);
            $result   = $strategy->create($payload, $input['manager'] ? $input['manager'] : null);
            return $result;
        } catch (Throwable $e) {
            return ['success'=>false,'message'=>"Erreur: ".$e->getMessage()];
        }
    }
}

/* ==========================
|  AJAX Endpoint (config profil uniquement)
|========================== */
if (isset($_GET['action']) && $_GET['action'] === 'profile_config') {
    header('Content-Type: application/json');
    $profile = isset($_GET['profile']) ? $_GET['profile'] : '';
    $isManagerTestable = (isset($_GET['testable']) ? $_GET['testable'] : '0') === '1';
    try {
        $strategy = UserStrategyFactory::create($profile, $isManagerTestable);
        echo json_encode([
            'needsTechnicalFields' => $strategy->needsTechnicalFields(),
            'needsLevelSelection'  => $strategy->needsLevelSelection(),
            'needsTestCreation'    => $strategy->needsTestCreation()
        ]);
    } catch (InvalidArgumentException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/* ==========================
|  Traitement Principal
|========================== */
$success_msg = $error_msg = $email_error = null;

$profileOptions = iterator_to_array(Db::$profiles->find(['active'=>true]));
// filtre "Super Admin" (sans fonctions fléchées)
$profileOptions = array_values(array_filter($profileOptions, function($p){
    return (isset($p['name']) ? $p['name'] : '') !== 'Super Admin';
}));

if (Input::isPost()) {
    $result = UserController::handleRequest();
    if ($result['success']) {
        $success_msg = $result['message'];
    } else {
        if (isset($result['errors'])) {
            $error_msg   = isset($result['errors']['general']) ? $result['errors']['general'] : '';
            $email_error = isset($result['errors']['email']) ? $result['errors']['email'] : null;
        } else {
            $error_msg = $result['message'];
        }
    }
}

/* ==========================
|  View (UI Afrique + dynamique + aperçu credentials)
|========================== */
include_once "partials/header.php";
?>
<title><?php echo $title_addUser ?> | CFAO Mobility Academy</title>

<style>
input, select { background-color:#fff !important; border:1px solid #ced4da; }
select { color:#495057; padding:.375rem .75rem; border-radius:.25rem; }
select option { background-color:#fff !important; color:#495057; }
input::placeholder, select::placeholder { color:#6c757d; }
.form-select { background-color:#fff !important; color:#495057; border:1px solid #ced4da; border-radius:.25rem; padding:.375rem .75rem; font-size:1rem; line-height:1.5; }
.form-select option { background-color:#fff !important; color:#495057; }
.field-hidden { display: none !important; }
.field-required::after { content: ' *'; color: red; }
.auto-generated { background-color: #f8f9fa !important; font-style: italic; }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
  <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div class="container-xxl">
      <div class="container mt-5 w-50">
        <img src="../../public/images/logo.png" alt="logo" height="170" style="display:block;max-width:75%;height:auto;margin-left:25px;">
        <h1 class='my-3 text-center'><?php echo $title_addUser ?></h1>

        <?php if ($success_msg): ?>
          <div class='alert alert-success alert-dismissible fade show' role='alert'>
            <center><strong><?php echo htmlspecialchars($success_msg); ?></strong></center>
            <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
          </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
          <div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <center><strong><?php echo htmlspecialchars($error_msg); ?></strong></center>
            <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
          </div>
        <?php endif; ?>

        <form method="POST" id="userForm"><br>
          <div class='row fv-row mb-7'>
            <div class='row g-9 mb-7'>
              <div class='col-md-6 fv-row'>
                <label class='required form-label fw-bolder text-dark fs-6'><?php echo $prenoms ?></label>
                <input class='form-control form-control-solid' name='firstName' id='firstName' required />
              </div>
              <div class='col-md-6 fv-row'>
                <label class='required form-label fw-bolder text-dark fs-6' style="margin-left:35px;"><?php echo $noms ?></label>
                <input class='form-control form-control-solid' name='lastName' id='lastName' style="margin-left:35px;" required />
              </div>
            </div>

            <!-- Matricule SAISI (pas d’auto-génération) + aperçu credentials -->
            <div class='fv-row mb-7'>
              <label class='required form-label fw-bolder text-dark fs-6'><?php echo $matricule ?></label>
              <input type='text' class='form-control form-control-solid' name='matricule' id='matricule' required />
              <small class="text-muted d-block mt-1">Le <strong>nom d’utilisateur</strong> et le <strong>mot de passe</strong> seront dérivés de vos saisies (aperçu ci-dessous).</small>
              <div id="credentialsPreview" class="alert alert-info mt-2 field-hidden"></div>
            </div>

            <!-- Profil -->
            <div class="d-flex flex-column mb-7 fv-row">
              <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $profil ?></span></label>
              <select name="profile" class="form-select form-select-solid fw-bold" id="profileSelect" required>
                <option value="">-- <?php echo $select_profil ?> --</option>
                <?php foreach ($profileOptions as $option): $name = htmlspecialchars($option['name'],ENT_QUOTES,'UTF-8'); ?>
                  <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                <?php endforeach; ?>
                <option value="Candidat">Candidat</option>
              </select>
            </div>

            <!-- Manager testable -->
            <div class="fv-row mb-7 field-hidden" id="managerTestableBlock">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="isManagerTestable" value="1" id="isManagerTestable">
                <label class="form-check-label" for="isManagerTestable">Manager testable (avec tests techniques)</label>
              </div>
            </div>

            <!-- Niveau (masqué si non nécessaire) -->
            <div class="d-flex flex-column mb-7 fv-row field-hidden" id="levelBlock">
              <label class="form-label fw-bolder text-dark fs-6"><span class="field-required"><?php echo $levelTech ?></span></label>
              <div class="form-check" style="margin-top:10px">
                <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Junior" id="junior">
                <label class="form-check-label text-black"><?php echo $junior ?> (<?php echo $maintenance ?>)</label>
              </div>
              <div class="form-check" style="margin-top:10px">
                <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Senior" id="senior">
                <label class="form-check-label text-black"><?php echo $senior ?> (<?php echo $reparation ?>)</label>
              </div>
              <div class="form-check" style="margin-top:10px">
                <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Expert" id="expert">
                <label class="form-check-label text-black"><?php echo $expert ?> (<?php echo $diagnostic ?>)</label>
              </div>
            </div>

            <!-- Genre -->
            <div class='d-flex flex-column mb-7 fv-row'>
              <label class='required form-label fw-bolder text-dark fs-6'><?php echo $gender ?></label>
              <select name='gender' class='form-select form-select-solid fw-bold' required>
                <option value="">-- <?php echo $select_gender ?> --</option>
                <option value='Feminin'><?php echo $female ?></option>
                <option value='Masculin'><?php echo $male ?></option>
              </select>
            </div>
            <!-- Email -->
            <div class='fv-row mb-7'>
              <label class='form-label fw-bolder text-dark fs-6'><?php echo $email ?></label>
              <input type='email' class='form-control form-control-solid' name='email' />
              <?php if ($email_error): ?><span class='text-danger'><?php echo htmlspecialchars($email_error); ?></span><?php endif; ?>
            </div>
            <!-- Téléphone -->
            <div class='fv-row mb-7'>
              <label class='required form-label fw-bolder text-dark fs-6'><?php echo $phoneNumber ?></label>
              <input type='text' class='form-control form-control-solid' name='phone' />
            </div>
            <!-- Date naissance -->
            <div class='fv-row mb-15'>
              <label class='form-label fw-bolder text-dark fs-6'><?php echo $birthdate ?></label>
              <input type='date' class='form-control form-control-solid' name='birthdate' />
            </div>

            <!-- Pays (Afrique only) -->
            <div class='d-flex flex-column mb-7 fv-row'>
              <label class='form-label fw-bolder text-dark fs-6'><span class='required'>Pays</span></label>
              <select name='country' class='form-select form-select-solid fw-bold' required>
                <option value="">-- Sélectionnez un pays --</option>
                <?php echo renderOptions(AFRICAN_COUNTRIES); ?>
              </select>
            </div>

            <!-- Filiale -->
            <div class="d-flex flex-column mb-7 fv-row">
              <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $filiale ?></span></label>
              <select name="subsidiary" id="subsidiary" class="form-select form-select-solid fw-bold" required>
                <option value="">-- <?php echo $select_subsidiary ?> --</option>
                <option value="CAMEROON MOTORS INDUSTRIES"><?php echo $cami ?></option>
                <option value="CFAO MOTORS BENIN"><?php echo $cfao_benin ?></option>
                <option value="CFAO MOTORS BURKINA"><?php echo $cfao_burkina ?></option>
                <option value="CFAO MOTORS CENTRAFRIQUE"><?php echo $cfao_centrafrique ?></option>
                <option value="CFAO MOTORS CONGO"><?php echo $cfao_congo ?></option>
                <option value="CFAO MOTORS COTE D'IVOIRE"><?php echo $cfao_cote_divoire ?></option>
                <option value="CFAO MOTORS GABON"><?php echo $cfao_gabon ?></option>
                <option value="CFAO (GAMBIA) LIMITED"><?php echo $cfao_gambia ?></option>
                <option value="CFAO MOTORS GHANA"><?php echo $cfao_ghana ?></option>
                <option value="CFAO MOTORS GUINEE"><?php echo $cfao_guinee ?></option>
                <option value="CFAO MOTORS GUINEE BISSAU"><?php echo $cfao_guinee_bissau ?></option>
                <option value="CFAO MOTORS GUINEA EQUATORIAL"><?php echo $cfao_guinee_equatorial ?></option>
                <option value="CFAO MOTORS MADAGASCAR"><?php echo $cfao_madagascar ?></option>
                <option value="CFAO MOTORS MALI"><?php echo $cfao_mali ?></option>
                <option value="CFAO MOTORS NIGER"><?php echo $cfao_niger ?></option>
                <option value="CFAO MOTORS NIGERIA"><?php echo $cfao_nigeria ?></option>
                <option value="CFAO MOTORS RDC"><?php echo $cfao_rdc ?></option>
                <option value="CFAO MOTORS SENEGAL"><?php echo $cfao_senegal ?></option>
                <option value="CFAO MOTORS TCHAD"><?php echo $cfao_tchad ?></option>
                <option value="CFAO MOTORS TOGO"><?php echo $cfao_togo ?></option>
                <option value="COMPAGNIE MAURITANIENNE DE DISTRIBUTION AUTOMOBILE"><?php echo $cfao_cmda ?></option>
              </select>
            </div>

            <!-- Agence -->
            <div class="fv-row mb-7">
              <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $agence ?></span></label>
              <input type="text" class="form-control form-control-solid" name="agency" required />
            </div>

            <!-- Département -->
            <div class="d-flex flex-column mb-7 fv-row">
              <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $department ?></span></label>
              <select onchange="enableBrand(this)" name="department" class="form-select form-select-solid fw-bold" id="departmentSelect" required>
                <option value="">-- <?php echo $select_department ?> --</option>
                <option value="Equipment">Equipment</option>
                <option value="Motors">Motors</option>
                <option value="Equipment & Motors">Equipment & Motors</option>
              </select>
            </div>

            <!-- Bloc TECH ONLY (marques/spécialités/certificat) -->
            <div id="techOnly">
              <!-- JUNIOR brands -->
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEquipmentJu">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $junior ?></span></label>
                <select name="brandJu[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(BRANDS_EQUIPMENT); ?>
                </select>
              </div>
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandMotorsJu">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $junior ?></span></label>
                <select name="brandJu[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(BRANDS_MOTORS); ?>
                </select>
              </div>
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEqMoJu">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $junior ?></span></label>
                <select name="brandJu[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(array_merge(BRANDS_EQUIPMENT, BRANDS_MOTORS)); ?>
                </select>
              </div>

              <!-- SENIOR brands -->
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEquipmentSe">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $senior ?></span></label>
                <select name="brandSe[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(BRANDS_EQUIPMENT); ?>
                </select>
              </div>
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandMotorsSe">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $senior ?></span></label>
                <select name="brandSe[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(BRANDS_MOTORS); ?>
                </select>
              </div>
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEqMoSe">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $senior ?></span></label>
                <select name="brandSe[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(array_merge(BRANDS_EQUIPMENT, BRANDS_MOTORS)); ?>
                </select>
              </div>

              <!-- EXPERT brands -->
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEquipmentEx">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $expert ?></span></label>
                <select name="brandEx[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(BRANDS_EQUIPMENT); ?>
                </select>
              </div>
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandMotorsEx">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $expert ?></span></label>
                <select name="brandEx[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(BRANDS_MOTORS); ?>
                </select>
              </div>
              <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEqMoEx">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?> <?php echo $expert ?></span></label>
                <select name="brandEx[]" multiple data-control="select2" data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                  <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                  <?php echo renderOptions(array_merge(BRANDS_EQUIPMENT, BRANDS_MOTORS)); ?>
                </select>
              </div>

              <!-- SPECIALITIES -->
              <div class="d-flex flex-column mb-7 fv-row d-none" id="metierSe">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $Speciality ?> <?php echo $senior ?></span></label>
                <select name="specialitySenior[]" multiple data-control="select2" data-placeholder="<?php echo $select_speciality ?>" class="form-select form-select-solid fw-bold">
                  <?php echo renderOptions(SPECIALITIES_SENIOR); ?>
                </select>
              </div>
              <div class="d-flex flex-column mb-7 fv-row d-none" id="metierEx">
                <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $Speciality ?> <?php echo $expert ?></span></label>
                <select name="specialityExpert[]" multiple data-control="select2" data-placeholder="<?php echo $select_speciality ?>" class="form-select form-select-solid fw-bold">
                  <?php echo renderOptions(SPECIALITIES_EXPERT); ?>
                </select>
              </div>

              <!-- CERTIFICATE -->
              <div class="fv-row mb-7 field-hidden" id="certificateBlock">
                <label class="form-label fw-bolder text-dark fs-6 field-required"><?php echo $certificat ?></label>
                <input type="text" class="form-control form-control-solid" name="certificate" />
              </div>
            </div><!-- /#techOnly -->

            <!-- Rôle -->
            <div class="fv-row mb-7">
              <label class="form-label fw-bolder text-dark fs-6"><?php echo $role ?></label>
              <input type="text" class="form-control form-control-solid fw-bold" name="role" />
            </div>

            <!-- Date recrutement -->
            <div class="fv-row mb-7">
              <label class="form-label fw-bolder text-dark fs-6"><?php echo $recrutmentDate ?></label>
              <input type="date" class="form-control form-control-solid" name="recrutmentDate" />
            </div>

            <!-- Manager (optionnel) -->
            <div class="d-flex flex-column mb-7 fv-row">
              <label class="form-label fw-bolder text-dark fs-6"><span class=""><?php echo $manager ?></span></label>
              <select name="manager" data-control="select2" data-placeholder="<?php echo $select_manager ?>" class="form-select form-select-solid fw-bold">
                <option value=""><?php echo $select_manager ?></option>
                <?php
                  if ($_SESSION['profile'] == "Admin") {
                      $managers = Db::$users->find(['$and'=>[["profile"=>"Manager"],["subsidiary"=>$_SESSION['subsidiary']],["active"=>true]]]);
                  } elseif ($_SESSION['profile'] == "Super Admin") {
                      $managers = Db::$users->find(['$and'=>[["profile"=>"Manager"],["active"=>true]]]);
                  } else { $managers = []; }
                  foreach ($managers as $m) {
                    $id=(string)$m->_id; $fn=htmlspecialchars($m->firstName,ENT_QUOTES,'UTF-8'); $ln=htmlspecialchars($m->lastName,ENT_QUOTES,'UTF-8');
                    echo "<option value='{$id}'>{$fn} {$ln}</option>";
                  }
                ?>
              </select>
            </div>

            <div class="modal-footer flex-center">
              <button type="submit" name="submit" class="btn btn-primary">
                <span class="indicator-label"><?php echo $valider ?></span>
                <span class="indicator-progress">Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
              </button>
            </div>

          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include "./partials/footer.php"; ?>

<!-- =========================
     JS (Strategy UI + credentials preview - PAS de génération matricule)
========================== -->
<script>
// Configuration éléments UI
var UI_CONFIG = {
  elements: {
    profileSelect: document.getElementById('profileSelect'),
    managerTestableBlock: document.getElementById('managerTestableBlock'),
    managerTestableCheckbox: document.getElementById('isManagerTestable'),
    levelBlock: document.getElementById('levelBlock'),
    technicalFields: document.getElementById('techOnly'),
    certificateBlock: document.getElementById('certificateBlock'),
    firstName: document.getElementById('firstName'),
    lastName: document.getElementById('lastName'),
    matricule: document.getElementById('matricule'),
    departmentSelect: document.getElementById('departmentSelect'),
    credentialsPreview: document.getElementById('credentialsPreview')
  }
};

/* ====== Brique legacy : bascule marques / spécialités par niveau & département ====== */
function enableBrand(answer) {
    checkedRa(answer.value);
    BrandManager.handleDepartmentChange();
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

    if (junior && junior.checked) {
        if (departValue == 'Motors') {
            brandMotorsJu.classList.remove('d-none');
            brandEquipmentJu.classList.add('d-none'); brandEqMoJu.classList.add('d-none');
            brandMotorsSe.classList.add('d-none'); brandEquipmentSe.classList.add('d-none'); brandEqMoSe.classList.add('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.add('d-none');
        } else if (departValue == 'Equipment') {
            brandMotorsJu.classList.add('d-none');
            brandEquipmentJu.classList.remove('d-none'); brandEqMoJu.classList.add('d-none');
            brandMotorsSe.classList.add('d-none'); brandEquipmentSe.classList.add('d-none'); brandEqMoSe.classList.add('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.add('d-none');
        } else if (departValue == 'Equipment & Motors') {
            brandMotorsJu.classList.add('d-none'); brandEquipmentJu.classList.add('d-none');
            brandEqMoJu.classList.remove('d-none');
            brandMotorsSe.classList.add('d-none'); brandEquipmentSe.classList.add('d-none'); brandEqMoSe.classList.add('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.add('d-none');
        }
    } else if (senior && senior.checked) {
        if (departValue == 'Motors') {
            brandMotorsJu.classList.remove('d-none'); brandEquipmentJu.classList.add('d-none'); brandEqMoJu.classList.add('d-none');
            brandMotorsSe.classList.remove('d-none'); brandEquipmentSe.classList.add('d-none'); brandEqMoSe.classList.add('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.add('d-none');
        } else if (departValue == 'Equipment') {
            brandMotorsJu.classList.add('d-none'); brandEquipmentJu.classList.remove('d-none'); brandEqMoJu.classList.add('d-none');
            brandMotorsSe.classList.add('d-none'); brandEquipmentSe.classList.remove('d-none'); brandEqMoSe.classList.add('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.add('d-none');
        } else if (departValue == 'Equipment & Motors') {
            brandMotorsJu.classList.add('d-none'); brandEquipmentJu.classList.add('d-none'); brandEqMoJu.classList.remove('d-none');
            brandMotorsSe.classList.add('d-none'); brandEquipmentSe.classList.add('d-none'); brandEqMoSe.classList.remove('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.add('d-none');
        }
    } else if (expert && expert.checked) {
        if (departValue == 'Motors') {
            brandMotorsJu.classList.remove('d-none'); brandEquipmentJu.classList.add('d-none'); brandEqMoJu.classList.add('d-none');
            brandMotorsSe.classList.remove('d-none'); brandEquipmentSe.classList.add('d-none'); brandEqMoSe.classList.add('d-none');
            brandMotorsEx.classList.remove('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.add('d-none');
        } else if (departValue == 'Equipment') {
            brandMotorsJu.classList.add('d-none'); brandEquipmentJu.classList.remove('d-none'); brandEqMoJu.classList.add('d-none');
            brandMotorsSe.classList.add('d-none'); brandEquipmentSe.classList.remove('d-none'); brandEqMoSe.classList.add('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.remove('d-none'); brandEqMoEx.classList.add('d-none');
        } else if (departValue == 'Equipment & Motors') {
            brandMotorsJu.classList.add('d-none'); brandEquipmentJu.classList.add('d-none'); brandEqMoJu.classList.remove('d-none');
            brandMotorsSe.classList.add('d-none'); brandEquipmentSe.classList.add('d-none'); brandEqMoSe.classList.remove('d-none');
            brandMotorsEx.classList.add('d-none'); brandEquipmentEx.classList.add('d-none'); brandEqMoEx.classList.remove('d-none');
        }
    }
    if (senior && senior.checked) {
        metierSe.classList.remove('d-none'); metierEx.classList.add('d-none');
    } else if (expert && expert.checked) {
        metierSe.classList.remove('d-none'); metierEx.classList.remove('d-none');
    } else {
        metierSe.classList.add('d-none'); metierEx.classList.add('d-none');
    }
}

/* ====== Profile Manager (appelle l’endpoint profile_config) ====== */
var ProfileManager = {
  handleProfileChange: function() {
    var profile = UI_CONFIG.elements.profileSelect ? UI_CONFIG.elements.profileSelect.value : '';
    var isManagerTestable = UI_CONFIG.elements.managerTestableCheckbox && UI_CONFIG.elements.managerTestableCheckbox.checked ? true : false;

    if (!profile) {
      ProfileManager.hideAllConditionalFields();
      return;
    }
    if (profile === 'Manager') {
      if (UI_CONFIG.elements.managerTestableBlock) UI_CONFIG.elements.managerTestableBlock.classList.remove('field-hidden');
    } else {
      if (UI_CONFIG.elements.managerTestableBlock) UI_CONFIG.elements.managerTestableBlock.classList.add('field-hidden');
      if (UI_CONFIG.elements.managerTestableCheckbox) UI_CONFIG.elements.managerTestableCheckbox.checked = false;
    }

    var url = '?action=profile_config&profile=' + encodeURIComponent(profile) + '&testable=' + (isManagerTestable ? '1' : '0');
    fetch(url).then(function(r){return r.json();}).then(function(config){
      if (config.error) { console.error('Profil non supporté', config.error); return; }
      ProfileManager.updateFieldsVisibility(config);
    }).catch(function(e){ console.error('Erreur config profil:', e); });
  },
  updateFieldsVisibility: function(config) {
    if (config.needsLevelSelection) {
      if (UI_CONFIG.elements.levelBlock) UI_CONFIG.elements.levelBlock.classList.remove('field-hidden');
    } else {
      if (UI_CONFIG.elements.levelBlock) UI_CONFIG.elements.levelBlock.classList.add('field-hidden');
    }
    if (config.needsTechnicalFields) {
      if (UI_CONFIG.elements.technicalFields) UI_CONFIG.elements.technicalFields.classList.remove('field-hidden');
      if (UI_CONFIG.elements.certificateBlock) UI_CONFIG.elements.certificateBlock.classList.remove('field-hidden');
    } else {
      if (UI_CONFIG.elements.technicalFields) UI_CONFIG.elements.technicalFields.classList.add('field-hidden');
      if (UI_CONFIG.elements.certificateBlock) UI_CONFIG.elements.certificateBlock.classList.add('field-hidden');
    }
  },
  hideAllConditionalFields: function() {
    ['managerTestableBlock','levelBlock','techOnly','certificateBlock'].forEach(function(id){
      var el = document.getElementById(id);
      if (el) el.classList.add('field-hidden');
    });
  }
};

/* ====== Brand Manager (masque/affiche les groupes de marques selon mapping) ====== */
var BrandManager = {
  handleDepartmentChange: function() {
    var department = UI_CONFIG.elements.departmentSelect ? UI_CONFIG.elements.departmentSelect.value : '';
    var levelEl = document.querySelector('input[name="level"]:checked');
    var level = levelEl ? levelEl.value : '';
    if (!department || !level) return;

    var allIds = [
      'brandEquipmentJu','brandMotorsJu','brandEqMoJu',
      'brandEquipmentSe','brandMotorsSe','brandEqMoSe',
      'brandEquipmentEx','brandMotorsEx','brandEqMoEx'
    ];
    allIds.forEach(function(id){
      var el = document.getElementById(id);
      if (el) el.classList.add('d-none');
    });

    BrandManager.showRelevantBrandBlocks(department, level);
  },
  showRelevantBrandBlocks: function(department, level) {
    var mapping = {
      'Equipment': {
        'Junior': ['brandEquipmentJu'],
        'Senior': ['brandEquipmentJu','brandEquipmentSe'],
        'Expert': ['brandEquipmentJu','brandEquipmentSe','brandEquipmentEx']
      },
      'Motors': {
        'Junior': ['brandMotorsJu'],
        'Senior': ['brandMotorsJu','brandMotorsSe'],
        'Expert': ['brandMotorsJu','brandMotorsSe','brandMotorsEx']
      },
      'Equipment & Motors': {
        'Junior': ['brandEqMoJu'],
        'Senior': ['brandEqMoJu','brandEqMoSe'],
        'Expert': ['brandEqMoJu','brandEqMoSe','brandEqMoEx']
      }
    };
    var ids = (mapping[department] && mapping[department][level]) ? mapping[department][level] : [];
    ids.forEach(function(id){
      var el = document.getElementById(id);
      if (el) el.classList.remove('d-none');
    });

    BrandManager.handleSpecialitiesVisibility(level);
  },
  handleSpecialitiesVisibility: function(level) {
    var se = document.getElementById('metierSe');
    var ex = document.getElementById('metierEx');
    if (level === 'Senior') {
      if (se) se.classList.remove('d-none');
      if (ex) ex.classList.add('d-none');
    } else if (level === 'Expert') {
      if (se) se.classList.remove('d-none');
      if (ex) ex.classList.remove('d-none');
    } else {
      if (se) se.classList.add('d-none');
      if (ex) ex.classList.add('d-none');
    }
  }
};

/* ====== Aperçu identifiants (sans génération du matricule) ====== */
var CredentialsPreview = {
  update: function() {
    var fn = UI_CONFIG.elements.firstName ? UI_CONFIG.elements.firstName.value.trim() : '';
    var ln = UI_CONFIG.elements.lastName ? UI_CONFIG.elements.lastName.value.trim() : '';
    var ma = UI_CONFIG.elements.matricule ? UI_CONFIG.elements.matricule.value.trim() : '';

    var preview = UI_CONFIG.elements.credentialsPreview;
    if (!fn || !ln || !ma) {
      if (preview) {
        preview.classList.add('field-hidden');
        preview.innerHTML = '';
      }
      return;
    }
    var firstTwo = fn.toLowerCase().replace(/[^a-z0-9]/g,'').substr(0,2);
    var cleanLast = ln.toLowerCase().replace(/[^a-z0-9]/g,'');
    var username = firstTwo + cleanLast + ma;
    var lastFour = String(ma).slice(-4);
    var password = cleanLast.replace(/\s+/g,'') + lastFour + '!';

    if (preview) {
      preview.classList.remove('field-hidden');
      preview.innerHTML = '<small><strong>Aperçu des identifiants :</strong><br>Nom d\'utilisateur : <code>'+username+
        '</code><br>Mot de passe : <code>'+password+'</code></small>';
    }
  }
};

/* ====== Init ====== */
document.addEventListener('DOMContentLoaded', function() {
  // Profils dynamiques
  if (UI_CONFIG.elements.profileSelect) UI_CONFIG.elements.profileSelect.addEventListener('change', ProfileManager.handleProfileChange);
  if (UI_CONFIG.elements.managerTestableCheckbox) UI_CONFIG.elements.managerTestableCheckbox.addEventListener('change', ProfileManager.handleProfileChange);

  // Département / niveau
  if (UI_CONFIG.elements.departmentSelect) UI_CONFIG.elements.departmentSelect.addEventListener('change', BrandManager.handleDepartmentChange);
  var lvlRadios = document.querySelectorAll('input[name="level"]');
  for (var i=0;i<lvlRadios.length;i++) {
    lvlRadios[i].addEventListener('change', function(){
      var department = UI_CONFIG.elements.departmentSelect ? UI_CONFIG.elements.departmentSelect.value : '';
      if (department) BrandManager.showRelevantBrandBlocks(department, this.value);
    });
  }

  // Aperçu credentials (sur saisie)
  ['firstName','lastName','matricule'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', CredentialsPreview.update);
      el.addEventListener('change', CredentialsPreview.update);
    }
  });

  // Fermeture des alertes
  var closes = document.querySelectorAll('.alert .close');
  for (var j=0;j<closes.length;j++){
    closes[j].addEventListener('click', function(){
      var alert = this.closest('.alert');
      if (alert) alert.remove();
    });
  }

  // Validation simple
  var form = document.getElementById('userForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      if (!validateForm()) { e.preventDefault(); return false; }
    });
  }
});

/* ====== Validation ====== */
function validateForm() {
  var requiredFields = document.querySelectorAll('[required]');
  var ok = true;
  for (var i=0;i<requiredFields.length;i++){
    var field = requiredFields[i];
    if (!field.value || !String(field.value).trim()) {
      field.style.borderColor = '#dc3545'; ok = false;
    } else { field.style.borderColor = '#ced4da'; }
  }
  var profile = UI_CONFIG.elements.profileSelect ? UI_CONFIG.elements.profileSelect.value : '';
  var needsTech = (profile === 'Technicien' || profile === 'Candidat') ||
                  (profile === 'Manager' && UI_CONFIG.elements.managerTestableCheckbox && UI_CONFIG.elements.managerTestableCheckbox.checked);
  if (needsTech) {
    var level = document.querySelector('input[name="level"]:checked');
    if (!level) { alert('Veuillez sélectionner un niveau technique.'); return false; }
  }
  return ok;
}

// Compatibilité legacy
function toggleTechFields() { ProfileManager.handleProfileChange(); }
</script>
<?php
// fin du fichier
