<?php
require_once __DIR__ . '/../../core/medacap_config.php';
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
require_once "../measure/includes/background-manager.php";
require_once "../measure/components/ui/title-main.php";

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
  'Afrique du Sud',
  'Algérie',
  'Angola',
  'Bénin',
  'Botswana',
  'Burkina Faso',
  'Burundi',
  'Cabo Verde',
  'Cameroun',
  'République Centrafricaine',
  'Tchad',
  'Comores',
  'Congo',
  'République Démocratique du Congo',
  'Djibouti',
  'Égypte',
  'Érythrée',
  'Eswatini',
  'Éthiopie',
  'Gabon',
  'Gambie',
  'Ghana',
  'Guinée',
  'Guinée-Bissau',
  "Côte d'Ivoire",
  'Kenya',
  'Lesotho',
  'Liberia',
  'Libye',
  'Madagascar',
  'Malawi',
  'Mali',
  'Île Maurice',
  'Mauritanie',
  'Maroc',
  'Mozambique',
  'Namibie',
  'Niger',
  'Nigéria',
  'Rwanda',
  'Sao Tomé-et-Principe',
  'Sénégal',
  'Seychelles',
  'Sierra Leone',
  'Somalie',
  'Soudan',
  'Soudan du Sud',
  'Tanzanie',
  'Togo',
  'Tunisie',
  'Zambie',
  'Zimbabwe'
];

const BRANDS_EQUIPMENT = [
  'FUSO',
  'HINO',
  'JCB',
  'KING LONG',
  'LOVOL',
  'MERCEDES TRUCK',
  'RENAULT TRUCK',
  'SINOTRUCK',
  'TOYOTA BT',
  'TOYOTA FORKLIFT'
];
const BRANDS_MOTORS = [
  'BYD',
  'CITROEN',
  'MERCEDES',
  'MITSUBISHI',
  'PEUGEOT',
  'SUZUKI',
  'TOYOTA',
  'YAMAHA BATEAU',
  'YAMAHA MOTO'
];

const SPECIALITIES_SENIOR = ['Electricté', 'Hydraulique', 'Moteur', 'Transmission'];
const SPECIALITIES_EXPERT = ['Electricté et Electronique', 'Hydraulique', 'Moteur', 'Transmission'];

/* ==========================
|  DB
|========================== */
final class Db
{
  public static $users;
  public static $vehicles;
  public static $tests;
  public static $allocations;
  public static $profiles;
  public static $specialities;

  public static function init(): void
  {
    // Compatible MongoDB 4.2
    $client = new Client(medacapMongoUri());
    $db = $client->academy;

    self::$users = $db->users;
    self::$vehicles = $db->vehicles;
    self::$tests = $db->tests;
    self::$allocations = $db->allocations;
    self::$profiles = $db->profiles;
    self::$specialities = $db->specialities;
  }
}
Db::init();

/* ==========================
|  IO & Validation
|========================== */
final class Input
{
  public static function isPost(): bool
  {
    return (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');
  }
  public static function post(string $key, $default = null)
  {
    if (!isset($_POST[$key]))
      return $default;
    return self::sanitize($_POST[$key]);
  }
  private static function sanitize($v)
  {
    if (is_array($v)) {
      foreach ($v as $k => $val)
        $v[$k] = self::sanitize($val);
      return $v;
    }
    if (!is_string($v))
      return $v;
    // Équivalent de FILTER_SANITIZE_STRING (déprécié en PHP 8.1) : balises retirées, guillemets encodés.
    $v = str_replace(['"', "'"], ['&#34;', '&#39;'], strip_tags($v));
    return trim($v);
  }
}
final class Validator
{
  public static function required(array $data, array $fields): array
  {
    $missing = [];
    foreach ($fields as $f)
      if (!isset($data[$f]) || $data[$f] === '' || $data[$f] === null)
        $missing[] = $f;
    return $missing;
  }
  public static function email(?string $email): bool
  {
    if ($email === null || $email === '')
      return true;
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
  }
}

/* ==========================
|  Username & Password
|========================== */
final class StringUtil
{
  public static function ascii(string $s): string
  {
    static $map = [
      'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A','Æ'=>'AE','Ç'=>'C',
      'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E','Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
      'Ð'=>'D','Ñ'=>'N','Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
      'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ý'=>'Y','Þ'=>'B','ß'=>'ss',
      'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae','ç'=>'c',
      'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
      'ð'=>'o','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
      'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','þ'=>'b','ÿ'=>'y'
    ];
    $clean = strtr($s, $map);
    $out = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
    $res = ($out !== false) ? $out : $clean;
    return preg_replace('/[\'`^~"\']/', '', $res);
  }
  public static function onlyAlnum(string $s): string
  {
    return preg_replace('/[^A-Za-z0-9]/', '', self::ascii($s));
  }
}

final class UsernameFactory
{
  public static function base(string $first, string $last): string
  {
    return trim($first . ' ' . $last);
  }
  public static function unique(string $first, string $last): string
  {
    $base = self::base($first, $last);
    $candidate = $base;
    $i = 1;
    while (UserService::existsActiveUsername($candidate)) {
      $candidate = $base . ' ' . $i;
      $i++;
    }
    return $candidate;
  }
}

final class PasswordFactory
{
  public static function buildVisible(string $firstName): string
  {
    $fn = ucfirst(strtolower(trim(StringUtil::onlyAlnum($firstName))));
    if ($fn === '') {
      $fn = 'User';
    }
    $year = date("Y");
    return "Academy." . $fn . "@" . $year;
  }

  public static function buildHashed(string $visiblePassword): string
  {
    // Hash SHA-1
    return sha1($visiblePassword);
  }
}



/* ==========================
|  Mailer
|========================== */
final class Mailer
{
  /**
   * Notification au manager/admin rattaché lorsqu'un technicien est créé.
   * Inclut les identifiants du technicien pour transmission.
   */
  public static function notifyManagerForTechnician(array $manager, array $technician): void
  {
    if (empty($manager['email']))
      return;

    $to = (string) $manager['email'];
    $fn = htmlspecialchars((string) $technician['firstName'], ENT_QUOTES, 'UTF-8');
    $ln = htmlspecialchars((string) $technician['lastName'], ENT_QUOTES, 'UTF-8');
    $lvl = htmlspecialchars((string) ($technician['level'] ?? ''), ENT_QUOTES, 'UTF-8');
    $profile = htmlspecialchars((string) ($technician['profile'] ?? 'Technicien'), ENT_QUOTES, 'UTF-8');
    $subs = htmlspecialchars((string) ($technician['subsidiary'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dept = htmlspecialchars((string) ($technician['department'] ?? ''), ENT_QUOTES, 'UTF-8');
    $agency = htmlspecialchars((string) ($technician['agency'] ?? ''), ENT_QUOTES, 'UTF-8');
    $mFirst = htmlspecialchars((string) ($manager['firstName'] ?? ''), ENT_QUOTES, 'UTF-8');
    $mLast = htmlspecialchars((string) ($manager['lastName'] ?? ''), ENT_QUOTES, 'UTF-8');
    $username = htmlspecialchars((string) ($technician['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $visiblePwd = htmlspecialchars((string) ($technician['visiblePassword'] ?? ''), ENT_QUOTES, 'UTF-8');
    $techEmail = isset($technician['email']) ? trim((string) $technician['email']) : '';

    $subject = 'MEDACAP — Nouveau collaborateur rattaché à votre compte';

    // Message conditionnel selon que le technicien a un email ou non
    if (empty($techEmail)) {
      $additionalInfo = "<strong>Action requise :</strong> Ce collaborateur ne dispose pas d'adresse email. Merci de lui transmettre ses identifiants de connexion (ci-dessus) afin qu'il puisse accéder à la plateforme MEDACAP.";
    } else {
      $additionalInfo = "Un email contenant ses identifiants de connexion a également été envoyé directement au collaborateur à l'adresse <strong>" . htmlspecialchars($techEmail, ENT_QUOTES, 'UTF-8') . "</strong>.";
    }

    $emailData = [
      'companyName' => 'MEDACAP',
      'companyTagline' => 'CFAO Mobility Academy',
      'userName' => trim($mFirst . ' ' . $mLast),
      'title' => 'Nouveau collaborateur inscrit',
      'message' => "Un nouveau collaborateur a été inscrit sur la plateforme MEDACAP et rattaché à votre compte.<br><br>Vous trouverez ci-dessous les informations de son compte.",
      'details' => [
        ['label' => 'Collaborateur', 'value' => $fn . ' ' . $ln, 'highlight' => true],
        ['label' => 'Profil', 'value' => $profile],
        ['label' => 'Niveau', 'value' => $lvl ?: 'À définir'],
        ['label' => 'Identifiant', 'value' => $username, 'highlight' => true],
        ['label' => 'Mot de passe', 'value' => $visiblePwd, 'highlight' => true],
        ['label' => 'Filiale', 'value' => $subs ?: '-'],
        ['label' => 'Département', 'value' => $dept ?: '-'],
        ['label' => 'Agence', 'value' => $agency ?: '-'],
      ],
      'ctaText' => 'Accéder à MEDACAP',
      'ctaLink' => medacapLinkForCountry(isset($technician['country']) ? (string) $technician['country'] : null),
      'additionalInfo' => $additionalInfo,
      'supportEmail' => SUPER_ADMIN_EMAIL,
    ];

    // Mot de passe en clair : pas de copie statique
    sendTemplatedMedacapEmail($to, $subject, $emailData, [], null, false);
  }

  /**
   * Notification au candidat avec ses identifiants pour les tests de recrutement.
   */
  public static function notifyCandidateRecruitment(string $email, string $firstName, string $lastName, string $username, string $visiblePwd, ?string $country): void
  {
    if (!$email)
      return;

    $to = $email;
    $fn = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $ln = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
    $user = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $pwd = htmlspecialchars($visiblePwd, ENT_QUOTES, 'UTF-8');
    $link = medacapLinkForCountry($country);

    $subject = 'MEDACAP — Vos identifiants pour les tests de recrutement';

    $emailData = [
      'companyName' => 'MEDACAP',
      'companyTagline' => 'CFAO Mobility Academy',
      'userName' => $fn . ' ' . $ln,
      'title' => 'Tests de recrutement',
      'message' => "Votre compte candidat a été créé sur la plateforme MEDACAP.<br><br>Vous êtes invité(e) à passer les <strong>tests de recrutement (QCM Factuels)</strong> dans le cadre de votre candidature. Connectez-vous avec les identifiants ci-dessous.",
      'details' => [
        ['label' => 'Type de test', 'value' => 'QCM Factuels', 'highlight' => true],
        ['label' => 'Identifiant', 'value' => $user, 'highlight' => true],
        ['label' => 'Mot de passe', 'value' => $pwd, 'highlight' => true],
        ['label' => 'Plateforme', 'value' => 'MEDACAP'],
        ['label' => 'Support', 'value' => SUPER_ADMIN_EMAIL],
      ],
      'ctaText' => 'Accéder aux tests',
      'ctaLink' => $link,
      'additionalInfo' => "Ces identifiants sont personnels et confidentiels. Merci de ne pas les partager.",
      'supportEmail' => SUPER_ADMIN_EMAIL,
    ];

    // Mot de passe en clair : pas de copie statique
    sendTemplatedMedacapEmail($to, $subject, $emailData, [], null, false);
  }

  /**
   * Notification à un utilisateur (Manager, Admin, RH, Directeur) avec ses propres identifiants.
   */
  public static function notifySelfAccess(string $email, string $lastName, string $firstName, string $username, string $visiblePwd, ?string $country, string $profileFlavor): void
  {
    if (!$email)
      return;

    $to = $email;
    $fn = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $ln = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
    $user = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $pwd = htmlspecialchars($visiblePwd, ENT_QUOTES, 'UTF-8');
    $link = medacapLinkForCountry($country);

    // Messages professionnels par profil
    $profileMessages = [
      'Manager' => [
        'subject' => 'MEDACAP — Votre accès Manager est activé',
        'title' => 'Accès Manager',
        'message' => "Votre compte <strong>Manager</strong> a été créé sur MEDACAP.<br><br>Vous pouvez désormais suivre vos équipes, consulter les résultats de tests et accompagner le développement des compétences de vos collaborateurs.",
        'additionalInfo' => "Nous vous recommandons de vérifier la liste de vos collaborateurs rattachés dès votre première connexion."
      ],
      'Admin' => [
        'subject' => 'MEDACAP — Votre accès Administrateur est activé',
        'title' => 'Accès Administrateur',
        'message' => "Votre compte <strong>Administrateur</strong> a été créé sur MEDACAP.<br><br>Vous avez accès à la supervision de votre filiale : gestion des utilisateurs, suivi des évaluations et pilotage des résultats.",
        'additionalInfo' => "En tant qu'administrateur, vous êtes le point de contact principal pour les utilisateurs de votre filiale."
      ],
      'Directeur' => [
        'subject' => 'MEDACAP — Votre accès Direction est activé',
        'title' => 'Accès Direction',
        'message' => "Votre compte <strong>Direction</strong> a été créé sur MEDACAP.<br><br>Vous disposez d'une vue d'ensemble sur les résultats et les compétences de vos équipes pour éclairer vos décisions stratégiques.",
        'additionalInfo' => "Consultez vos tableaux de bord pour un aperçu des performances de votre périmètre."
      ],
      'RH' => [
        'subject' => 'MEDACAP — Votre accès Ressources Humaines est activé',
        'title' => 'Accès Ressources Humaines',
        'message' => "Votre profil <strong>Ressources Humaines</strong> a été créé sur MEDACAP.<br><br>Cet espace vous permet d'organiser et suivre les sessions d'évaluation des candidats au recrutement, de consulter les rapports en temps réel et d'exploiter les résultats pour vos décisions de sélection.",
        'additionalInfo' => "Vous recevrez également par email le récapitulatif des identifiants des candidats rattachés à vos campagnes de recrutement."
      ]
    ];

    $defaultMessage = [
      'subject' => 'MEDACAP — Votre compte est activé',
      'title' => 'Compte activé',
      'message' => "Votre compte <strong>{$profileFlavor}</strong> a été créé sur la plateforme MEDACAP.<br><br>Connectez-vous avec les identifiants ci-dessous pour accéder à votre espace.",
      'additionalInfo' => "Ces identifiants sont personnels et confidentiels."
    ];

    $content = isset($profileMessages[$profileFlavor]) ? $profileMessages[$profileFlavor] : $defaultMessage;

    $emailData = [
      'companyName' => 'MEDACAP',
      'companyTagline' => 'CFAO Mobility Academy',
      'userName' => $fn . ' ' . $ln,
      'title' => $content['title'],
      'message' => $content['message'],
      'details' => [
        ['label' => 'Profil', 'value' => $profileFlavor, 'highlight' => true],
        ['label' => 'Identifiant', 'value' => $user, 'highlight' => true],
        ['label' => 'Mot de passe', 'value' => $pwd, 'highlight' => true],
        ['label' => 'Plateforme', 'value' => 'MEDACAP'],
        ['label' => 'Support', 'value' => SUPER_ADMIN_EMAIL],
      ],
      'ctaText' => 'Se connecter',
      'ctaLink' => $link,
      'additionalInfo' => $content['additionalInfo'],
      'supportEmail' => SUPER_ADMIN_EMAIL,
    ];

    // Mot de passe en clair : pas de copie statique
    sendTemplatedMedacapEmail($to, $content['subject'], $emailData, [], null, false);
  }
}


/* ==========================
|  Users
|========================== */
final class UserService
{
  public static function createUser(array $payload, ?string $managerId, string $profileToPersist, bool $testFlag = true, bool $isTechOrCandidate = false): ObjectId
  {
    $doc = [
      "matricule" => (int) $payload['matricule'],
      "firstName" => ucfirst($payload['firstName']),
      "lastName" => ucfirst($payload['lastName']),
      "email" => $payload['email'],
      "phone" => $payload['phone'],
      "gender" => $payload['gender'],
      "level" => isset($payload['level']) ? $payload['level'] : "",
      "country" => $payload['country'],
      "profile" => $profileToPersist,
      "birthdate" => $payload['birthdate'],
      "recrutmentDate" => $payload['recrutmentDate'],
      "role" => ucfirst($payload['role']),
      "password" => $payload['password_hash'],
      "visiblePassword" => $payload['visiblePassword'],
      "manager" => $managerId ? new ObjectId($managerId) : "",
      "test" => $testFlag,
      "active" => true,
      "updated" => date("d-m-Y H:i:s"),
      "subsidiary" => ucfirst($payload['subsidiary']),
      "agency" => ucfirst($payload['agency']),
      "department" => ucfirst($payload['department']),
    ];

    if ($isTechOrCandidate) {
      $doc["certificate"] = ucfirst($payload['certificate']);
      $doc["brandJunior"] = isset($payload['brandJunior']) ? $payload['brandJunior'] : [];
      $doc["brandSenior"] = isset($payload['brandSenior']) ? $payload['brandSenior'] : [];
      $doc["brandExpert"] = isset($payload['brandExpert']) ? $payload['brandExpert'] : [];
      $doc["specialitySenior"] = isset($payload['specialitySenior']) ? $payload['specialitySenior'] : [];
      $doc["specialityExpert"] = isset($payload['specialityExpert']) ? $payload['specialityExpert'] : [];
    }

    $existing = self::getByMatricule($payload['matricule']);
    if ($existing) {
      $userId = $existing['_id'];
      Db::$users->updateOne(["_id" => $userId], ['$set' => $doc]);
    } else {
      $doc["username"] = $payload['username'];
      $doc["users"] = [];
      $doc["created"] = date("d-m-Y H:i:s");
      $res = Db::$users->insertOne($doc);
      $userId = $res->getInsertedId();
    }

    if ($managerId) {
      Db::$users->updateOne(
        ["_id" => new ObjectId($managerId)],
        ['$push' => ["users" => new ObjectId($userId)]]
      );
    }
    return $userId;
  }

  public static function propagateBrands(ObjectId $userId, array $brandSenior, array $brandExpert): void
  {
    if (!empty($brandExpert)) {
      // Expert ► aussi Senior & Junior
      foreach ($brandExpert as $b) {
        Db::$users->updateOne(["_id" => $userId], ['$addToSet' => ["brandSenior" => $b]]);
        Db::$users->updateOne(["_id" => $userId], ['$addToSet' => ["brandJunior" => $b]]);
      }
    }
    if (!empty($brandSenior)) {
      // Senior ► aussi Junior
      foreach ($brandSenior as $b) {
        Db::$users->updateOne(["_id" => $userId], ['$addToSet' => ["brandJunior" => $b]]);
      }
    }
  }

  public static function getById(ObjectId $id): ?array
  {
    $doc = Db::$users->findOne(["_id" => $id, "active" => true]);
    return $doc ? (array) $doc : null;
  }
  public static function existsActiveUsername(string $username): bool
  {
    return (bool) Db::$users->findOne(['$and' => [["username" => $username], ["active" => true]]]);
  }
  public static function getActiveManager(?string $managerId): ?array
  {
    if (!$managerId)
      return null;
    $doc = Db::$users->findOne(['$and' => [["_id" => new ObjectId($managerId)], ["active" => true]]]);
    return $doc ? (array) $doc : null;
  }
  public static function getByMatricule($matricule): ?array
  {
    $doc = Db::$users->findOne(["matricule" => (int) $matricule, "active" => true]);
    return $doc ? (array) $doc : null;
  }
}

/* ==========================
|  Tests & Allocations
|========================== */
final class TestPlanBuilder
{
  const TYPE_FACTUEL = 'Factuel';
  const TYPE_DECLARATIF = 'Declaratif';
  const SPEC_FACTUELLE = 'Factuelle';
  const SPEC_DECLARATIVE = 'Declarative';

  private static function createTest(ObjectId $userId, array $brandList, string $type, string $level): ObjectId
  {
    $doc = ["quizzes" => [], "user" => $userId, "brand" => $brandList, "type" => $type, "level" => $level, "total" => 0, "active" => true, "created" => date("d-m-Y H:i:s")];
    return Db::$tests->insertOne($doc)->getInsertedId();
  }
  private static function quizRefs($doc): array
  {
    if (!$doc || !isset($doc->quizzes))
      return [];
    $q = $doc->quizzes;
    if (is_array($q))
      return $q;
    if ($q instanceof Traversable)
      return iterator_to_array($q);
    return [];
  }
  private static function addFromVehicles(ObjectId $testId, string $brand, string $type, string $level): void
  {
    $vehicle = Db::$vehicles->findOne(['$and' => [["brand" => $brand], ["type" => $type], ["level" => $level], ["active" => true]]]);
    $quizRefs = self::quizRefs($vehicle);
    if ($quizRefs) {
      Db::$tests->updateOne(["_id" => $testId], ['$addToSet' => ["quizzes" => ['$each' => $quizRefs]]]);
    }
  }
  private static function addFromSpecialities(ObjectId $testId, string $label, string $typeSpec, string $level): void
  {
    $spec = Db::$specialities->findOne(['$and' => [["label" => $label], ["type" => $typeSpec], ["level" => $level], ["active" => true]]]);
    $quizRefs = self::quizRefs($spec);
    if ($quizRefs) {
      Db::$tests->updateOne(["_id" => $testId], ['$addToSet' => ["quizzes" => ['$each' => $quizRefs]]]);
    }
  }
  private static function finalize(ObjectId $testId): void
  {
    $t = Db::$tests->findOne(["_id" => $testId]);
    $total = self::quizRefs($t) ? count(self::quizRefs($t)) : 0;
    Db::$tests->updateOne(["_id" => $testId], ['$set' => ["total" => $total]]);
  }
  private static function allocate(ObjectId $userId, ObjectId $testId, string $type, string $level, bool $activeTest, bool $activeManager = false): void
  {
    Db::$allocations->insertOne([
      "user" => $userId,
      "test" => $testId,
      "type" => $type,
      "level" => $level,
      "activeTest" => $activeTest,
      "activeManager" => $activeManager,
      "active" => false,
      "created" => date("d-m-Y H:i:s")
    ]);
  }

  /** Niveau Junior (Tech) — active immédiatement Factuel & Déclaratif Junior */
  public static function buildForJunior(ObjectId $userId, array $brandJunior, bool $activateJuniorTests = true): void
  {
    $tFac = self::createTest($userId, $brandJunior, self::TYPE_FACTUEL, 'Junior');
    $tDecla = self::createTest($userId, $brandJunior, self::TYPE_DECLARATIF, 'Junior');
    foreach ($brandJunior as $b) {
      self::addFromVehicles($tFac, $b, self::TYPE_FACTUEL, 'Junior');
      self::addFromVehicles($tDecla, $b, self::TYPE_DECLARATIF, 'Junior');
    }
    self::finalize($tFac);
    self::finalize($tDecla);
    self::allocate($userId, $tFac, self::TYPE_FACTUEL, 'Junior', $activateJuniorTests);
    self::allocate($userId, $tDecla, self::TYPE_DECLARATIF, 'Junior', $activateJuniorTests, false);
  }

  /** Niveau Senior (Tech) — active rattrapage Junior, garde Senior inactif */
  public static function buildForSenior(ObjectId $userId, array $brandJunior, array $brandSenior, array $specSenior, bool $activateJuniorCatchup = true): void
  {
    // Junior catch-up
    $tJuF = self::createTest($userId, $brandJunior, self::TYPE_FACTUEL, 'Junior');
    $tJuD = self::createTest($userId, $brandJunior, self::TYPE_DECLARATIF, 'Junior');
    foreach ($brandJunior as $b) {
      self::addFromVehicles($tJuF, $b, self::TYPE_FACTUEL, 'Junior');
      self::addFromVehicles($tJuD, $b, self::TYPE_DECLARATIF, 'Junior');
    }
    self::finalize($tJuF);
    self::finalize($tJuD);
    self::allocate($userId, $tJuF, self::TYPE_FACTUEL, 'Junior', $activateJuniorCatchup);
    self::allocate($userId, $tJuD, self::TYPE_DECLARATIF, 'Junior', $activateJuniorCatchup, false);

    // Senior build (inactive)
    $tSeF = self::createTest($userId, $brandSenior, self::TYPE_FACTUEL, 'Senior');
    $tSeD = self::createTest($userId, $brandSenior, self::TYPE_DECLARATIF, 'Senior');
    foreach ($brandSenior as $b) {
      self::addFromVehicles($tSeF, $b, self::TYPE_FACTUEL, 'Senior');
      self::addFromVehicles($tSeD, $b, self::TYPE_DECLARATIF, 'Senior');
    }
    foreach ($specSenior as $s) {
      self::addFromSpecialities($tSeF, $s, self::SPEC_FACTUELLE, 'Senior');
      self::addFromSpecialities($tSeD, $s, self::SPEC_DECLARATIVE, 'Senior');
    }
    self::finalize($tSeF);
    self::finalize($tSeD);
    self::allocate($userId, $tSeF, self::TYPE_FACTUEL, 'Senior', false);
    self::allocate($userId, $tSeD, self::TYPE_DECLARATIF, 'Senior', false, false);
  }

  /** Niveau Expert (Tech) — active Junior, Senior & Expert restent inactifs (sauf Junior actif) */
  public static function buildForExpert(
    ObjectId $userId,
    array $brandJunior,
    array $brandSenior,
    array $brandExpert,
    array $specSenior,
    array $specExpert
  ): void {
    // Junior (actif)
    $tJuF = self::createTest($userId, $brandJunior, self::TYPE_FACTUEL, 'Junior');
    $tJuD = self::createTest($userId, $brandJunior, self::TYPE_DECLARATIF, 'Junior');
    foreach ($brandJunior as $b) {
      self::addFromVehicles($tJuF, $b, self::TYPE_FACTUEL, 'Junior');
      self::addFromVehicles($tJuD, $b, self::TYPE_DECLARATIF, 'Junior');
    }
    self::finalize($tJuF);
    self::finalize($tJuD);
    self::allocate($userId, $tJuF, self::TYPE_FACTUEL, 'Junior', true);
    self::allocate($userId, $tJuD, self::TYPE_DECLARATIF, 'Junior', true, false);

    // Senior (inactif)
    $tSeF = self::createTest($userId, $brandSenior, self::TYPE_FACTUEL, 'Senior');
    $tSeD = self::createTest($userId, $brandSenior, self::TYPE_DECLARATIF, 'Senior');
    foreach ($brandSenior as $b) {
      self::addFromVehicles($tSeF, $b, self::TYPE_FACTUEL, 'Senior');
      self::addFromVehicles($tSeD, $b, self::TYPE_DECLARATIF, 'Senior');
    }
    foreach ($specSenior as $s) {
      self::addFromSpecialities($tSeF, $s, self::SPEC_FACTUELLE, 'Senior');
      self::addFromSpecialities($tSeD, $s, self::SPEC_DECLARATIVE, 'Senior');
    }
    self::finalize($tSeF);
    self::finalize($tSeD);
    self::allocate($userId, $tSeF, self::TYPE_FACTUEL, 'Senior', false);
    self::allocate($userId, $tSeD, self::TYPE_DECLARATIF, 'Senior', false, false);

    // Expert (inactif)
    $tExF = self::createTest($userId, $brandExpert, self::TYPE_FACTUEL, 'Expert');
    $tExD = self::createTest($userId, $brandExpert, self::TYPE_DECLARATIF, 'Expert');
    foreach ($brandExpert as $b) {
      self::addFromVehicles($tExF, $b, self::TYPE_FACTUEL, 'Expert');
      self::addFromVehicles($tExD, $b, self::TYPE_DECLARATIF, 'Expert');
    }
    foreach ($specExpert as $s) {
      self::addFromSpecialities($tExF, $s, self::SPEC_FACTUELLE, 'Expert');
      self::addFromSpecialities($tExD, $s, self::SPEC_DECLARATIVE, 'Expert');
    }
    self::finalize($tExF);
    self::finalize($tExD);
    self::allocate($userId, $tExF, self::TYPE_FACTUEL, 'Expert', false);
    self::allocate($userId, $tExD, self::TYPE_DECLARATIF, 'Expert', false, false);
  }

  /** Candidat — Factuel uniquement, selon le niveau choisi */
  public static function buildForCandidate(ObjectId $userId, string $level, array $brands, array $specs): void
  {
    $tF = self::createTest($userId, $brands, self::TYPE_FACTUEL, $level);
    foreach ($brands as $b)
      self::addFromVehicles($tF, $b, self::TYPE_FACTUEL, $level);

    // Spécialités factuelles si Senior/Expert
    if ($level === 'Senior' && !empty($specs)) {
      foreach ($specs as $s)
        self::addFromSpecialities($tF, $s, self::SPEC_FACTUELLE, 'Senior');
    }
    if ($level === 'Expert' && !empty($specs)) {
      foreach ($specs as $s)
        self::addFromSpecialities($tF, $s, self::SPEC_FACTUELLE, 'Expert');
    }
    self::finalize($tF);
    self::allocate($userId, $tF, self::TYPE_FACTUEL, $level, true);
  }
}

/* ==========================
|  Render helpers (UI)
|========================== */
function renderOptions(array $items, ?array $selected = null): string
{
  $buf = '';
  foreach ($items as $opt) {
    $sel = ($selected && in_array($opt, $selected)) ? ' selected' : '';
    $label = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
    $buf .= "<option value=\"{$label}\"{$sel}>{$label}</option>";
  }
  return $buf;
}

/* =================================================================
|  >>>>>>>>>  Strategy + Factory  (compatibles PHP 7.3)  <<<<<<
|================================================================= */
interface UserCreationStrategy
{
  public function needsTechnicalFields(): bool;
  public function needsTestCreation(): bool;
  public function needsLevelSelection(): bool;
  public function create(array $payload, ?string $managerId): array;
}
class TechnicianStrategy implements UserCreationStrategy
{
  public function needsTechnicalFields(): bool
  {
    return true;
  }
  public function needsTestCreation(): bool
  {
    return true;
  }
  public function needsLevelSelection(): bool
  {
    return true;
  }
  public function create(array $payload, ?string $managerId): array
  {
    $userId = UserService::createUser($payload, $managerId, "Technicien", true, true);
    UserService::propagateBrands($userId, isset($payload['brandSenior']) ? $payload['brandSenior'] : [], isset($payload['brandExpert']) ? $payload['brandExpert'] : []);
    $level = $payload['level'];
    switch ($level) {
      case "Junior":
        TestPlanBuilder::buildForJunior($userId, $payload['brandJunior'], true);
        break;
      case "Senior":
        TestPlanBuilder::buildForSenior($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['specialitySenior'], true);
        break;
      case "Expert":
        TestPlanBuilder::buildForExpert($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['brandExpert'], $payload['specialitySenior'], $payload['specialityExpert']);
        break;
    }
    if ($managerId) {
      $manager = UserService::getActiveManager($managerId);
      $technician = UserService::getById($userId);
      if ($manager && $technician)
        Mailer::notifyManagerForTechnician($manager, $technician);
    }
    return ['success' => true, 'message' => "Technicien créé avec succès."];
  }
}
class CandidateStrategy implements UserCreationStrategy
{
  public function needsTechnicalFields(): bool
  {
    return true;
  }
  public function needsTestCreation(): bool
  {
    return true;
  }
  public function needsLevelSelection(): bool
  {
    return true;
  }
  public function create(array $payload, ?string $managerId): array
  {
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
      $payload['email'],
      $payload['firstName'],
      $payload['lastName'],
      $payload['username'],
      $payload['visiblePassword'],
      $payload['country']
    );
    return ['success' => true, 'message' => "Candidat créé avec succès."];
  }
}
class ManagerStrategy implements UserCreationStrategy
{
  private $isTestable; // pas de propriétés typées en PHP 7.3
  public function __construct(bool $isTestable = false)
  {
    $this->isTestable = $isTestable;
  }
  public function needsTechnicalFields(): bool
  {
    return (bool) $this->isTestable;
  }
  public function needsTestCreation(): bool
  {
    return (bool) $this->isTestable;
  }
  public function needsLevelSelection(): bool
  {
    return (bool) $this->isTestable;
  }
  public function create(array $payload, ?string $managerId): array
  {
    $userId = UserService::createUser($payload, $managerId, "Manager", $this->isTestable, $this->isTestable);
    if ($this->isTestable && !empty($payload['level'])) {
      UserService::propagateBrands($userId, isset($payload['brandSenior']) ? $payload['brandSenior'] : [], isset($payload['brandExpert']) ? $payload['brandExpert'] : []);
      switch ($payload['level']) {
        case "Junior":
          TestPlanBuilder::buildForJunior($userId, $payload['brandJunior'], true);
          break;
        case "Senior":
          TestPlanBuilder::buildForSenior($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['specialitySenior'], true);
          break;
        case "Expert":
          TestPlanBuilder::buildForExpert($userId, $payload['brandJunior'], $payload['brandSenior'], $payload['brandExpert'], $payload['specialitySenior'], $payload['specialityExpert']);
          break;
      }
    }
    if ($managerId) {
      $manager = UserService::getActiveManager($managerId);
      $user = UserService::getById($userId);
      if ($manager && $user)
        Mailer::notifyManagerForTechnician($manager, $user);
    } else {
      Mailer::notifySelfAccess(
        $payload['email'],
        $payload['lastName'],
        $payload['firstName'],
        $payload['username'],
        $payload['visiblePassword'],
        $payload['country'],
        'Manager'
      );
    }
    return ['success' => true, 'message' => $this->isTestable ? "Manager testable créé." : "Manager créé."];
  }
}
class AdminStrategy implements UserCreationStrategy
{
  public function needsTechnicalFields(): bool
  {
    return false;
  }
  public function needsTestCreation(): bool
  {
    return false;
  }
  public function needsLevelSelection(): bool
  {
    return false;
  }
  public function create(array $payload, ?string $managerId): array
  {
    UserService::createUser($payload, null, "Admin", false, false);
    Mailer::notifySelfAccess($payload['email'], $payload['lastName'], $payload['firstName'], $payload['username'], $payload['visiblePassword'], $payload['country'], 'Admin');
    return ['success' => true, 'message' => "Admin créé."];
  }
}
class HRStrategy implements UserCreationStrategy
{
  public function needsTechnicalFields(): bool
  {
    return false;
  }
  public function needsTestCreation(): bool
  {
    return false;
  }
  public function needsLevelSelection(): bool
  {
    return false;
  }
  public function create(array $payload, ?string $managerId): array
  {
    UserService::createUser($payload, null, "Ressource Humaine", false, false);
    Mailer::notifySelfAccess($payload['email'], $payload['lastName'], $payload['firstName'], $payload['username'], $payload['visiblePassword'], $payload['country'], 'RH');
    return ['success' => true, 'message' => "RH créé."];
  }
}
class DirectorStrategy implements UserCreationStrategy
{
  private $profileName; // pas de propriétés typées en PHP 7.3
  public function __construct(string $profileName)
  {
    $this->profileName = $profileName;
  }
  public function needsTechnicalFields(): bool
  {
    return false;
  }
  public function needsTestCreation(): bool
  {
    return false;
  }
  public function needsLevelSelection(): bool
  {
    return false;
  }
  public function create(array $payload, ?string $managerId): array
  {
    UserService::createUser($payload, null, $this->profileName, false, false);
    Mailer::notifySelfAccess($payload['email'], $payload['lastName'], $payload['firstName'], $payload['username'], $payload['visiblePassword'], $payload['country'], 'Direction');
    return ['success' => true, 'message' => "Compte direction créé."];
  }
}

final class UserStrategyFactory
{
  public static function create(string $profile, bool $isManagerTestable = false): UserCreationStrategy
  {
    // Pas de "match" (PHP 8) —> switch (compatible PHP 7.3)
    switch ($profile) {
      case 'Technicien':
        return new TechnicianStrategy();
      case 'Candidat':
        return new CandidateStrategy();
      case 'Manager':
        return new ManagerStrategy($isManagerTestable);
      case 'Manager - Technicien':
        return new ManagerStrategy(true); // legacy : toujours testable
      case 'Admin':
        return new AdminStrategy();
      case 'Ressource Humaine':
        return new HRStrategy();
      case 'Directeur Pièce et Service':
      case 'Directeur des Opérations':
      case 'Directeur Général':
      case 'Directeur Groupe':
        return new DirectorStrategy($profile);
      default:
        throw new InvalidArgumentException("Profil non supporté: " . $profile);
    }
  }
}

/* ==========================
|  Controller principal
|========================== */
final class UserController
{
  private static function validateInput(array $data): array
  {
    $errors = [];
    // matricule optionnel (par défaut 999999 si non renseigné)
    $required = ['firstName', 'lastName', 'department', 'agency', 'profile'];
    $missing = Validator::required($data, $required);
    if (!empty($missing))
      $errors['general'] = "Champs obligatoires manquants: " . implode(', ', $missing);
    if (!empty($data['email']) && !Validator::email($data['email']))
      $errors['email'] = "Email invalide.";
    return $errors;
  }
  private static function preparePayload(array $input): array
  {
    // matricule optionnel : si absent, on génère un numéro unique basé sur le timestamp
    // (évite les collisions avec d'autres utilisateurs sans matricule)
    $matricule = !empty($input['matricule']) ? $input['matricule'] : (900000 + (time() % 99999));
    // Credentials auto basés sur le prénom/nom
    $username = UsernameFactory::unique($input['firstName'], $input['lastName']);
    $visiblePassword = PasswordFactory::buildVisible($input['firstName']);
    $passwordHash = PasswordFactory::buildHashed($visiblePassword);

    // Dates formatées
    $birthdate = !empty($input['birthdate']) ? date("d-m-Y", strtotime($input['birthdate'])) : "";
    $recrDate = !empty($input['recrutmentDate']) ? date("d-m-Y", strtotime($input['recrutmentDate'])) : "";

    return [
      'username' => $username,
      'matricule' => $matricule,
      'firstName' => $input['firstName'],
      'lastName' => $input['lastName'],
      'email' => isset($input['email']) ? $input['email'] : '',
      'phone' => isset($input['phone']) ? $input['phone'] : '0',
      'gender' => isset($input['gender']) ? $input['gender'] : '',
      'level' => isset($input['level']) ? $input['level'] : '',
      'country' => isset($input['country']) ? $input['country'] : '',
      'birthdate' => $birthdate,
      'recrutmentDate' => $recrDate,
      'certificate' => isset($input['certificate']) ? $input['certificate'] : '',
      'subsidiary' => $input['subsidiary'],
      'agency' => $input['agency'],
      'department' => $input['department'],
      'role' => isset($input['role']) ? $input['role'] : '',
      'password_hash' => $passwordHash,
      'visiblePassword' => $visiblePassword,
      'brandJunior' => isset($input['brandJu']) ? $input['brandJu'] : [],
      'brandSenior' => isset($input['brandSe']) ? $input['brandSe'] : [],
      'brandExpert' => isset($input['brandEx']) ? $input['brandEx'] : [],
      'specialitySenior' => isset($input['specialitySenior']) ? $input['specialitySenior'] : [],
      'specialityExpert' => isset($input['specialityExpert']) ? $input['specialityExpert'] : [],
    ];
  }
  public static function handleRequest(): array
  {
    if (!Input::isPost())
      return ['success' => false, 'message' => 'Méthode non autorisée'];

    $input = [
      'firstName' => Input::post("firstName"),
      'lastName' => Input::post("lastName"),
      'matricule' => Input::post("matricule"),
      'email' => Input::post("email"),
      'phone' => Input::post("phone"),
      'subsidiary' => Input::post("subsidiary"),
      'department' => Input::post("department"),
      'role' => Input::post("role"),
      'profile' => Input::post("profile"),
      'agency' => Input::post("agency"),
      'gender' => Input::post("gender"),
      'country' => Input::post("country"),
      'certificate' => Input::post("certificate"),
      'birthdate' => Input::post("birthdate"),
      'recrutmentDate' => Input::post("recrutmentDate"),
      'level' => Input::post("level"),
      'brandJu' => Input::post("brandJu", []),
      'brandSe' => Input::post("brandSe", []),
      'brandEx' => Input::post("brandEx", []),
      'specialitySenior' => Input::post("specialitySenior", []),
      'specialityExpert' => Input::post("specialityExpert", []),
      'manager' => Input::post("manager"),
      'isManagerTestable' => Input::post("isManagerTestable") === '1'
    ];

    $errors = self::validateInput($input);
    if (!empty($errors))
      return ['success' => false, 'errors' => $errors];

    try {
      $payload = self::preparePayload($input);
      $strategy = UserStrategyFactory::create($input['profile'], $input['isManagerTestable']);
      $result = $strategy->create($payload, $input['manager'] ? $input['manager'] : null);
      if ($result['success']) {
        $result['data'] = $payload; // Pour le récap
      }
      return $result;
    } catch (Throwable $e) {
      return ['success' => false, 'message' => "Erreur: " . $e->getMessage()];
    }
  }
}

/* ==========================
|  AJAX Endpoints (Handled after classes are defined)
|========================== */
if (Input::post('action') === 'get_user_by_matricule') {
  $matricule = Input::post('matricule');
  $user = UserService::getByMatricule($matricule);
  if ($user) {
    $user['id'] = (string) $user['_id'];
    unset($user['_id']);
    echo json_encode(['success' => true, 'user' => $user]);
  } else {
    echo json_encode(['success' => false]);
  }
  exit;
}

if (Input::post('action') === 'get_managers_by_profile') {
  $profile = Input::post('profile');
  // Pas de filtre par filiale — affiche TOUS les managers du profil sélectionné
  $filter = ["profile" => $profile, "active" => true];
  $list = Db::$users->find($filter);
  $output = [];
  foreach ($list as $m) {
    $output[] = [
      'id' => (string) $m['_id'],
      'name' => htmlspecialchars($m['firstName'] . ' ' . $m['lastName'], ENT_QUOTES, 'UTF-8')
    ];
  }
  echo json_encode(['success' => true, 'managers' => $output]);
  exit;
}

if (Input::post('action') === 'get_user_details') {
  $id = Input::post('userId');
  try {
    $user = Db::$users->findOne(["_id" => new ObjectId($id)]);
    if ($user) {
      echo json_encode([
        'success' => true,
        'user' => [
          'subsidiary' => $user['subsidiary'] ?? '',
          'department' => $user['department'] ?? '',
          'agency' => $user['agency'] ?? '',
          'country' => $user['country'] ?? ''
        ]
      ]);
    } else {
      echo json_encode(['success' => false]);
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false]);
  }
  exit;
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
      'needsLevelSelection' => $strategy->needsLevelSelection(),
      'needsTestCreation' => $strategy->needsTestCreation()
    ]);
  } catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
  }
  exit;
}

/* ==========================
|  Traitement Principal
|========================== */
$success_msg = $error_msg = $email_error = $recapData = null;

$profileOptions = iterator_to_array(Db::$profiles->find(['active' => true]));
// filtre "Super Admin" (sans fonctions fléchées)
$profileOptions = array_values(array_filter($profileOptions, function ($p) {
  return (isset($p['name']) ? $p['name'] : '') !== 'Super Admin';
}));

if (Input::isPost() && !isset($_POST['reset_form'])) {
  $result = UserController::handleRequest();
  if ($result['success']) {
    $success_msg = $result['message'];
    $recapData = $result['data'] ?? null;
  } else {
    if (isset($result['errors'])) {
      $error_msg = isset($result['errors']['general']) ? $result['errors']['general'] : '';
      $email_error = isset($result['errors']['email']) ? $result['errors']['email'] : null;
    } else {
      $error_msg = $result['message'];
    }
  }
}

// Conserver les valeurs saisies en cas d'erreur de validation
$old = ($error_msg || $email_error) ? $_POST : [];
function old(string $key, string $default = ''): string {
  global $old;
  return htmlspecialchars(isset($old[$key]) ? (string)$old[$key] : $default, ENT_QUOTES, 'UTF-8');
}
function oldSel(string $key, string $value): string {
  global $old;
  return (isset($old[$key]) && (string)$old[$key] === $value) ? 'selected' : '';
}

/* ==========================
|  View (UI Afrique + dynamique + aperçu credentials)
|========================== */
include_once "partials/header.php";
?>
<title><?php echo $title_addUser ?> | CFAO Mobility Academy</title>

<style>
  input,
  select {
    background-color: #fff !important;
    border: 1px solid #ced4da;
  }

  select {
    color: #495057;
    padding: .375rem .75rem;
    border-radius: .25rem;
  }

  select option {
    background-color: #fff !important;
    color: #495057;
  }

  input::placeholder,
  select::placeholder {
    color: #6c757d;
  }

  .form-select {
    background-color: #fff !important;
    color: #495057;
    border: 1px solid #ced4da;
    border-radius: .25rem;
    padding: .375rem .75rem;
    font-size: 1rem;
    line-height: 1.5;
  }

  .form-select option {
    background-color: #fff !important;
    color: #495057;
  }

  .field-hidden {
    display: none !important;
  }

  .field-required::after {
    content: ' *';
    color: red;
  }

  .auto-generated {
    background-color: #f8f9fa !important;
    font-style: italic;
  }

  /* Styles spécifiques pour le mode glassmorphism */
  .card.glassmorphism {
    background: rgba(255, 255, 255, 0.6) !important;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
  }
</style>

<?php
setPageBackground('bg-welcome-tech');
openBackgroundContainer();
?>

<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
  <div class="container-xxl">

    <div class="row g-3 g-xl-6 mb-3 mb-xl-6">
      <?php renderTitleMain('ki-duotone ki-user-plus fs-2', 3, $title_addUser); ?>
    </div>

    <div class="row justify-content-center">
      <div class="col-xl-8">

        <?php if ($success_msg && $recapData): ?>
            <!-- CARD RECAP (Slide In Effect via CSS) -->
            <div class="card glassmorphism mb-5 animate__animated animate__slideInRight">
              <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                  <span class="card-label fw-bold fs-3 mb-1">Félicitations ! Compte créé avec succès</span>
                  <span class="text-muted mt-1 fw-semibold fs-7">Récapitulatif des informations et envoi du mail</span>
                </h3>
              </div>
              <div class="card-body py-3">
                <div class="d-flex flex-stack mb-5">
                  <div class="d-flex align-items-center me-2">
                    <div class="symbol symbol-50px me-3">
                      <div class="symbol-label bg-light-primary">
                        <i class="ki-duotone ki-user fs-2x text-primary"><span class="path1"></span><span
                            class="path2"></span></i>
                      </div>
                    </div>
                    <div class="d-flex flex-column">
                      <span
                        class="text-gray-800 text-hover-primary fs-4 fw-bold"><?php echo htmlspecialchars($recapData['firstName'] . ' ' . $recapData['lastName']); ?></span>
                      <span class="text-muted fw-semibold d-block fs-7">Matricule:
                        <?php echo htmlspecialchars($recapData['matricule']); ?></span>
                    </div>
                  </div>
                </div>

                <div class="separator separator-dashed my-4"></div>

                <div class="row mb-7">
                  <div class="col-md-6">
                    <label class="fw-semibold fs-7 text-muted">Identifiant de connexion</label>
                    <div class="fw-bold fs-6 text-gray-800"><?php echo htmlspecialchars($recapData['username']); ?></div>
                  </div>
                  <div class="col-md-6">
                    <label class="fw-semibold fs-7 text-muted">Mot de passe provisoire</label>
                    <div class="fw-bold fs-6 text-primary"><?php echo htmlspecialchars($recapData['visiblePassword']); ?>
                    </div>
                  </div>
                </div>

                <div class="row mb-7">
                  <div class="col-md-6">
                    <label class="fw-semibold fs-7 text-muted">Email (Destinataire)</label>
                    <div class="fw-bold fs-6 text-gray-800"><?php echo htmlspecialchars($recapData['email']); ?></div>
                  </div>
                  <div class="col-md-6">
                    <label class="fw-semibold fs-7 text-muted">Structure</label>
                    <div class="fw-bold fs-6 text-gray-800">
                      <?php echo htmlspecialchars($recapData['subsidiary'] . ' (' . $recapData['agency'] . ')'); ?>
                    </div>
                  </div>
                </div>

                <div class="alert alert-dismissible bg-light-success d-flex flex-column flex-sm-row p-5 mb-10">
                  <i class="ki-duotone ki-notification-on fs-2hx text-success me-4 mb-5 mb-sm-0"><span
                      class="path1"></span><span class="path2"></span><span class="path3"></span><span
                      class="path4"></span><span class="path5"></span></i>
                  <div class="d-flex flex-column pe-0 pe-sm-10">
                    <h4 class="fw-bold">Email envoyé</h4>
                    <span>Un email de bienvenue contenant ces identifiants a été envoyé à
                      <strong><?php echo htmlspecialchars($recapData['email']); ?></strong>.</span>
                  </div>
                </div>

                <form method="POST">
                  <button type="submit" name="reset_form" class="btn btn-primary w-100">
                    <i class="ki-duotone ki-plus fs-2"></i> Créer un autre utilisateur
                  </button>
                </form>
              </div>
            </div>
        <?php else: ?>

            <?php if ($error_msg): ?>
                <div class='alert alert-danger alert-dismissible fade show glassmorphism' role='alert'>
                  <center><strong><?php echo htmlspecialchars($error_msg); ?></strong></center>
                  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
            <?php endif; ?>

            <div class="card glassmorphism mb-5 animate__animated animate__fadeIn">
              <div class="card-body p-9">
                <form method="POST" id="userForm">
                  <div class='row fv-row mb-7'>
                    <div class='row g-9 mb-7'>
                      <div class='col-md-6 fv-row'>
                        <label class='required form-label fw-bolder text-dark fs-6'><?php echo $prenoms ?></label>
                        <input class='form-control form-control-solid' name='firstName' id='firstName' value="<?php echo old('firstName'); ?>" required />
                      </div>
                      <div class='col-md-6 fv-row'>
                        <label class='required form-label fw-bolder text-dark fs-6'
                          style="margin-left:35px;"><?php echo $noms ?></label>
                        <input class='form-control form-control-solid' name='lastName' id='lastName'
                          style="margin-left:35px;" value="<?php echo old('lastName'); ?>" required />
                      </div>
                    </div>

                    <!-- Matricule OPTIONNEL (par défaut 999999) + aperçu credentials -->
                    <div class='fv-row mb-7'>
                      <label class='form-label fw-bolder text-dark fs-6'><?php echo $matricule ?></label>
                      <input type='text' class='form-control form-control-solid' name='matricule' id='matricule'
                        value="<?php echo old('matricule'); ?>" placeholder="999999 (par défaut)" />
                      <small class="text-muted d-block mt-1">Le <strong>nom d’utilisateur</strong> et le <strong>mot de
                          passe</strong> seront dérivés de vos saisies (aperçu ci-dessous).</small>
                      <div id="credentialsPreview" class="alert alert-info mt-2 field-hidden"></div>
                    </div>

                    <!-- Profil -->
                    <div class="d-flex flex-column mb-7 fv-row">
                      <label class="form-label fw-bolder text-dark fs-6"><span
                          class="required"><?php echo $profil ?></span></label>
                      <select name="profile" class="form-select form-select-solid fw-bold" id="profileSelect" required>
                        <option value="">-- <?php echo $select_profil ?> --</option>
                        <?php foreach ($profileOptions as $option):
                          $name = htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8'); ?>
                            <option value="<?php echo $name; ?>" <?php echo oldSel('profile', $name); ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                        <option value="Candidat" <?php echo oldSel('profile', 'Candidat'); ?>>Candidat</option>
                      </select>
                    </div>

                    <!-- Choix du Manager (DYNAMIQUE) -->
                    <div class="row mb-7">
                      <div class="col-md-6">
                        <label class="form-label fw-bolder text-dark fs-6">Profil du Manager</label>
                        <select id="managerProfileSelect" class="form-select form-select-solid fw-bold">
                          <option value="">-- Sélectionner Profil --</option>
                          <?php foreach ($profileOptions as $option):
                            $name = htmlspecialchars($option['name'], ENT_QUOTES, 'UTF-8'); ?>
                              <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label fw-bolder text-dark fs-6">Nom du Manager</label>
                        <select name="manager" id="managerNameSelect" class="form-select form-select-solid fw-bold">
                          <option value="">-- Choisir Manager --</option>
                        </select>
                      </div>
                      <small class="text-muted mt-1">En choisissant un manager, la filiale, le pays, l'agence et le
                        département seront auto-remplis.</small>
                    </div>

                    <!-- Manager testable -->
                    <div class="fv-row mb-7 field-hidden" id="managerTestableBlock">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="isManagerTestable" value="1"
                          id="isManagerTestable">
                        <label class="form-check-label" for="isManagerTestable">Manager testable (avec tests
                          techniques)</label>
                      </div>
                    </div>

                    <!-- Niveau (masqué si non nécessaire) -->
                    <div class="d-flex flex-column mb-7 fv-row field-hidden" id="levelBlock">
                      <label class="form-label fw-bolder text-dark fs-6"><span
                          class="field-required"><?php echo $levelTech ?></span></label>
                      <div class="form-check" style="margin-top:10px">
                        <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Junior"
                          id="junior">
                        <label class="form-check-label text-black"><?php echo $junior ?>
                          (<?php echo $maintenance ?>)</label>
                      </div>
                      <div class="form-check" style="margin-top:10px">
                        <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Senior"
                          id="senior">
                        <label class="form-check-label text-black"><?php echo $senior ?> (<?php echo $reparation ?>)</label>
                      </div>
                      <div class="form-check" style="margin-top:10px">
                        <input class="form-check-input" onclick="checkedRa()" type="radio" name="level" value="Expert"
                          id="expert">
                        <label class="form-check-label text-black"><?php echo $expert ?> (<?php echo $diagnostic ?>)</label>
                      </div>
                    </div>

                    <!-- Genre -->
                    <div class='d-flex flex-column mb-7 fv-row'>
                      <label class='required form-label fw-bolder text-dark fs-6'><?php echo $gender ?></label>
                      <select name='gender' class='form-select form-select-solid fw-bold' required>
                        <option value="">-- <?php echo $select_gender ?> --</option>
                        <option value='Feminin' <?php echo oldSel('gender','Feminin'); ?>><?php echo $female ?></option>
                        <option value='Masculin' <?php echo oldSel('gender','Masculin'); ?>><?php echo $male ?></option>
                      </select>
                    </div>
                    <!-- Email -->
                    <div class='fv-row mb-7'>
                      <label class='form-label fw-bolder text-dark fs-6'><?php echo $email ?></label>
                      <input type='email' class='form-control form-control-solid' name='email' value="<?php echo old('email'); ?>" />
                      <?php if ($email_error): ?><span
                            class='text-danger'><?php echo htmlspecialchars($email_error); ?></span><?php endif; ?>
                    </div>
                    <!-- Téléphone -->
                    <div class='fv-row mb-7'>
                      <label class='form-label fw-bolder text-dark fs-6'><?php echo $phoneNumber ?></label>
                      <input type='text' class='form-control form-control-solid' name='phone' id='phone' value="<?php echo old('phone'); ?>" />
                    </div>
                    <!-- Date naissance -->
                    <div class='fv-row mb-15'>
                      <label class='form-label fw-bolder text-dark fs-6'><?php echo $birthdate ?></label>
                      <input type='date' class='form-control form-control-solid' name='birthdate' value="<?php echo old('birthdate'); ?>" />
                    </div>

                    <!-- Pays (Afrique only) -->
                    <div class='d-flex flex-column mb-7 fv-row'>
                      <label class='form-label fw-bolder text-dark fs-6'><span class='required'>Pays</span></label>
                      <select name='country' id='country' class='form-select form-select-solid fw-bold' required>
                        <option value="">-- Sélectionnez un pays --</option>
                        <?php echo renderOptions(AFRICAN_COUNTRIES); ?>
                      </select>
                    </div>

                    <!-- Filiale -->
                    <div class="d-flex flex-column mb-7 fv-row">
                      <label class="form-label fw-bolder text-dark fs-6"><span
                          class="required"><?php echo $filiale ?></span></label>
                      <select name="subsidiary" id="subsidiary" class="form-select form-select-solid fw-bold" required>
                        <option value="">-- <?php echo $select_subsidiary ?> --</option>
                        <option value="CFAO MOBILITY CAMEROON"><?php echo $cami ?></option>
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
                      <label class="form-label fw-bolder text-dark fs-6"><span
                          class="required"><?php echo $agence ?></span></label>
                      <input type="text" class="form-control form-control-solid" name="agency" id="agency" value="<?php echo old('agency'); ?>" required />
                    </div>

                    <!-- Département -->
                    <div class="d-flex flex-column mb-7 fv-row">
                      <label class="form-label fw-bolder text-dark fs-6"><span
                          class="required"><?php echo $department ?></span></label>
                      <select onchange="enableBrand(this)" name="department" class="form-select form-select-solid fw-bold"
                        id="departmentSelect" required>
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
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $junior ?></span></label>
                        <select name="brandJu[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(BRANDS_EQUIPMENT); ?>
                        </select>
                      </div>
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandMotorsJu">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $junior ?></span></label>
                        <select name="brandJu[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(BRANDS_MOTORS); ?>
                        </select>
                      </div>
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEqMoJu">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $junior ?></span></label>
                        <select name="brandJu[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(array_merge(BRANDS_EQUIPMENT, BRANDS_MOTORS)); ?>
                        </select>
                      </div>

                      <!-- SENIOR brands -->
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEquipmentSe">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $senior ?></span></label>
                        <select name="brandSe[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(BRANDS_EQUIPMENT); ?>
                        </select>
                      </div>
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandMotorsSe">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $senior ?></span></label>
                        <select name="brandSe[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(BRANDS_MOTORS); ?>
                        </select>
                      </div>
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEqMoSe">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $senior ?></span></label>
                        <select name="brandSe[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(array_merge(BRANDS_EQUIPMENT, BRANDS_MOTORS)); ?>
                        </select>
                      </div>

                      <!-- EXPERT brands -->
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEquipmentEx">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $expert ?></span></label>
                        <select name="brandEx[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(BRANDS_EQUIPMENT); ?>
                        </select>
                      </div>
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandMotorsEx">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $expert ?></span></label>
                        <select name="brandEx[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(BRANDS_MOTORS); ?>
                        </select>
                      </div>
                      <div class="d-flex flex-column mb-7 fv-row d-none" style="margin-top:10px" id="brandEqMoEx">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $brand ?>
                            <?php echo $expert ?></span></label>
                        <select name="brandEx[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_brand ?>" class="form-select form-select-solid fw-bold">
                          <option value="" disabled>-- <?php echo $select_brand ?> --</option>
                          <?php echo renderOptions(array_merge(BRANDS_EQUIPMENT, BRANDS_MOTORS)); ?>
                        </select>
                      </div>

                      <!-- SPECIALITIES -->
                      <div class="d-flex flex-column mb-7 fv-row d-none" id="metierSe">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $Speciality ?>
                            <?php echo $senior ?></span></label>
                        <select name="specialitySenior[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_speciality ?>" class="form-select form-select-solid fw-bold">
                          <?php echo renderOptions(SPECIALITIES_SENIOR); ?>
                        </select>
                      </div>
                      <div class="d-flex flex-column mb-7 fv-row d-none" id="metierEx">
                        <label class="form-label fw-bolder text-dark fs-6"><span class="required"><?php echo $Speciality ?>
                            <?php echo $expert ?></span></label>
                        <select name="specialityExpert[]" multiple data-control="select2"
                          data-placeholder="<?php echo $select_speciality ?>" class="form-select form-select-solid fw-bold">
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


                    <div class="modal-footer flex-center">
                      <button type="submit" name="submit" class="btn btn-primary">
                        <span class="indicator-label"><?php echo $valider ?></span>
                        <span class="indicator-progress">Patientez... <span
                            class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                      </button>
                    </div>

                </form>
              </div><!-- /card-body -->
            </div><!-- /card -->
        <?php endif; ?> <!-- /Success condition -->
      </div><!-- /col-xl-8 -->
    </div><!-- /row -->
  </div><!-- /container-xxl -->
</div><!-- /post -->

<?php closeBackgroundContainer(); ?>

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
    handleProfileChange: function () {
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
      fetch(url).then(function (r) { return r.json(); }).then(function (config) {
        if (config.error) { console.error('Profil non supporté', config.error); return; }
        ProfileManager.updateFieldsVisibility(config);
      }).catch(function (e) { console.error('Erreur config profil:', e); });
    },
    updateFieldsVisibility: function (config) {
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
    hideAllConditionalFields: function () {
      ['managerTestableBlock', 'levelBlock', 'techOnly', 'certificateBlock'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('field-hidden');
      });
    }
  };

  /* ====== Brand Manager (masque/affiche les groupes de marques selon mapping) ====== */
  var BrandManager = {
    handleDepartmentChange: function () {
      var department = UI_CONFIG.elements.departmentSelect ? UI_CONFIG.elements.departmentSelect.value : '';
      var levelEl = document.querySelector('input[name="level"]:checked');
      var level = levelEl ? levelEl.value : '';
      if (!department || !level) return;

      var allIds = [
        'brandEquipmentJu', 'brandMotorsJu', 'brandEqMoJu',
        'brandEquipmentSe', 'brandMotorsSe', 'brandEqMoSe',
        'brandEquipmentEx', 'brandMotorsEx', 'brandEqMoEx'
      ];
      allIds.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('d-none');
      });

      BrandManager.showRelevantBrandBlocks(department, level);
    },
    showRelevantBrandBlocks: function (department, level) {
      var mapping = {
        'Equipment': {
          'Junior': ['brandEquipmentJu'],
          'Senior': ['brandEquipmentJu', 'brandEquipmentSe'],
          'Expert': ['brandEquipmentJu', 'brandEquipmentSe', 'brandEquipmentEx']
        },
        'Motors': {
          'Junior': ['brandMotorsJu'],
          'Senior': ['brandMotorsJu', 'brandMotorsSe'],
          'Expert': ['brandMotorsJu', 'brandMotorsSe', 'brandMotorsEx']
        },
        'Equipment & Motors': {
          'Junior': ['brandEqMoJu'],
          'Senior': ['brandEqMoJu', 'brandEqMoSe'],
          'Expert': ['brandEqMoJu', 'brandEqMoSe', 'brandEqMoEx']
        }
      };
      var ids = (mapping[department] && mapping[department][level]) ? mapping[department][level] : [];
      ids.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('d-none');
      });

      BrandManager.handleSpecialitiesVisibility(level);
    },
    handleSpecialitiesVisibility: function (level) {
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
    update: function () {
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
      var username = (fn + ' ' + ln).trim();
      var year = new Date().getFullYear();
      var firstClean = fn.split(' ')[0].replace(/[^A-Za-z0-9]/g, '');
      var password = 'Academy.' + firstClean.charAt(0).toUpperCase() + firstClean.slice(1).toLowerCase() + '@' + year;

      if (preview) {
        preview.classList.remove('field-hidden');
        preview.innerHTML = '<small><strong>Aperçu des identifiants :</strong><br>Nom d\'utilisateur : <code>' + username +
          '</code><br>Mot de passe : <code>' + password + '</code></small>';
      }
    }
  };

  /* ====== Auto-fill user data if matricule exists ====== */
  var UserAutoFill = {
    check: function () {
      var ma = UI_CONFIG.elements.matricule ? UI_CONFIG.elements.matricule.value.trim() : '';
      if (!ma || ma.length < 2) return;

      var formData = new FormData();
      formData.append('action', 'get_user_by_matricule');
      formData.append('matricule', ma);

      fetch('createUser.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.user) {
            var u = data.user;
            if (UI_CONFIG.elements.firstName) UI_CONFIG.elements.firstName.value = u.firstName || '';
            if (UI_CONFIG.elements.lastName) UI_CONFIG.elements.lastName.value = u.lastName || '';
            if (document.getElementById('email')) document.getElementById('email').value = u.email || '';
            if (document.getElementById('phone')) document.getElementById('phone').value = u.phone || '';
            if (document.getElementById('subsidiary')) document.getElementById('subsidiary').value = u.subsidiary || '';
            if (document.getElementById('agency')) document.getElementById('agency').value = u.agency || '';
            if (UI_CONFIG.elements.departmentSelect) UI_CONFIG.elements.departmentSelect.value = u.department || '';
            if (UI_CONFIG.elements.profileSelect) {
              UI_CONFIG.elements.profileSelect.value = u.profile || '';
              ProfileManager.handleProfileChange();
            }
            // Trigger UI updates
            CredentialsPreview.update();
            BrandManager.handleDepartmentChange();
          }
        })
        .catch(error => console.error('Error:', error));
    }
  };

  /* ====== Manager Selection & Auto-fill ====== */
  var ManagerSelection = {
    handleProfileChange: function () {
      var profile = document.getElementById('managerProfileSelect').value;
      var nameSelect = document.getElementById('managerNameSelect');
      if (!profile) {
        nameSelect.innerHTML = '<option value="">-- Choisir Manager --</option>';
        return;
      }

      // Feedback visuel : loading
      nameSelect.innerHTML = '<option value="">Chargement...</option>';

      var formData = new FormData();
      formData.append('action', 'get_managers_by_profile');
      formData.append('profile', profile);

      fetch('createUser.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function(response) {
          if (!response.ok) {
            throw new Error('HTTP ' + response.status + ' ' + response.statusText);
          }
          return response.text(); // d'abord en texte pour debug
        })
        .then(function(text) {
          console.log('Réponse brute managers:', text);
          var data;
          try {
            data = JSON.parse(text);
          } catch(e) {
            console.error('Réponse non-JSON:', text.substring(0, 500));
            nameSelect.innerHTML = '<option value="">Erreur serveur (voir console)</option>';
            return;
          }
          var html = '<option value="">-- Choisir Manager --</option>';
          if (data.success && data.managers && data.managers.length > 0) {
            data.managers.forEach(function (m) {
              html += '<option value="' + m.id + '">' + m.name + '</option>';
            });
          } else {
            html = '<option value="">Aucun manager trouvé pour "' + profile + '"</option>';
            console.warn('Aucun manager trouvé. Réponse:', data);
          }
          nameSelect.innerHTML = html;
        })
        .catch(function(err) {
          console.error('Erreur fetch managers:', err);
          nameSelect.innerHTML = '<option value="">Erreur: ' + err.message + '</option>';
        });
    },
    handleNameChange: function () {
      var userId = document.getElementById('managerNameSelect').value;
      if (!userId) return;

      var formData = new FormData();
      formData.append('action', 'get_user_details');
      formData.append('userId', userId);

      fetch('createUser.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
          if (data.success && data.user) {
            var u = data.user;
            if (document.getElementById('subsidiary')) document.getElementById('subsidiary').value = u.subsidiary || '';
            if (document.getElementById('departmentSelect')) document.getElementById('departmentSelect').value = u.department || '';
            if (document.getElementById('agency')) document.getElementById('agency').value = u.agency || '';
            if (document.getElementById('country')) document.getElementById('country').value = u.country || '';

            // Trigger potential UI updates
            BrandManager.handleDepartmentChange();
          }
        });
    }
  };

  /* ====== Init ====== */
  document.addEventListener('DOMContentLoaded', function () {
    // Profils dynamiques
    if (UI_CONFIG.elements.profileSelect) UI_CONFIG.elements.profileSelect.addEventListener('change', ProfileManager.handleProfileChange);

    var mps = document.getElementById('managerProfileSelect');
    if (mps) mps.addEventListener('change', ManagerSelection.handleProfileChange);

    var mns = document.getElementById('managerNameSelect');
    if (mns) mns.addEventListener('change', ManagerSelection.handleNameChange);
    if (UI_CONFIG.elements.managerTestableCheckbox) UI_CONFIG.elements.managerTestableCheckbox.addEventListener('change', ProfileManager.handleProfileChange);

    // Manager dynamique
    var mProf = document.getElementById('managerProfileSelect');
    if (mProf) mProf.addEventListener('change', ManagerSelection.handleProfileChange);
    var mName = document.getElementById('managerNameSelect');
    if (mName) mName.addEventListener('change', ManagerSelection.handleNameChange);

    // Département / niveau
    if (UI_CONFIG.elements.departmentSelect) UI_CONFIG.elements.departmentSelect.addEventListener('change', BrandManager.handleDepartmentChange);
    var lvlRadios = document.querySelectorAll('input[name="level"]');
    for (var i = 0; i < lvlRadios.length; i++) {
      lvlRadios[i].addEventListener('change', function () {
        var department = UI_CONFIG.elements.departmentSelect ? UI_CONFIG.elements.departmentSelect.value : '';
        if (department) BrandManager.showRelevantBrandBlocks(department, this.value);
      });
    }

    // Aperçu credentials (sur saisie)
    ['firstName', 'lastName', 'matricule'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', CredentialsPreview.update);
        el.addEventListener('change', CredentialsPreview.update);
        if (id === 'matricule') {
          el.addEventListener('change', UserAutoFill.check);
        }
      }
    });

    // Fermeture des alertes
    var closes = document.querySelectorAll('.alert .close');
    for (var j = 0; j < closes.length; j++) {
      closes[j].addEventListener('click', function () {
        var alert = this.closest('.alert');
        if (alert) alert.remove();
      });
    }

    // Validation simple
    var form = document.getElementById('userForm');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (!validateForm()) { e.preventDefault(); return false; }
      });
    }

    // Clear personal fields on success but keep structural ones (subsidiary, etc.)
    <?php if ($success_msg): ?>
        ['firstName', 'lastName', 'matricule', 'email', 'phone', 'birthdate', 'recrutmentDate'].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = '';
        });
        CredentialsPreview.update();
    <?php endif; ?>
  });

  /* ====== Validation ====== */
  function validateForm() {
    var requiredFields = document.querySelectorAll('[required]');
    var ok = true;
    for (var i = 0; i < requiredFields.length; i++) {
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
