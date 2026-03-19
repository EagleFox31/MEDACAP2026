<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");

    // Connecting in database
    $academy = $conn->academy;

    // Connecting in collections
    $users = $academy->users;
    $results = $academy->results;

    $user       = $_GET["user"]       ?? '';
    $level      = $_GET["level"]      ?? '';
    $numberTest = $_GET["numberTest"] ?? 0;

    $technician = null;
    if (!empty($user)) {
        $technician = $users->findOne([
            '$and' => [
                [
                    "_id" => new MongoDB\BSON\ObjectId($user),
                    "active" => true,
                ],
            ],
        ]);
    }

    function safeAvgPct($fac, $decla, $declScore) {
        if (!$fac   || ($fac->total   ?? 0) == 0) return 0;
        if (!$decla || ($decla->total ?? 0) == 0) return 0;
        return ceil((($fac->score * 100 / $fac->total) + ($declScore * 100 / $decla->total)) / 2);
    }

    $managerStr = ($technician && !empty($technician->manager)) ? (string)$technician->manager : '000000000000000000000000';
    $userObjId  = !empty($user) ? new MongoDB\BSON\ObjectId($user) : new MongoDB\BSON\ObjectId('000000000000000000000000');

    $brand = [];
    if ($level == "Junior") {
        $brand = $technician['brandJunior'] ?? [];
    }
    if ($level == "Senior") {
        $brand = $technician['brandSenior'] ?? [];
    }
    if ($level == "Expert") {
        $brand = $technician['brandExpert'] ?? [];
    }
    for (
        $i = 0;
        $i < count($brand);
        $i++
    ) {
        if ($brand[$i] == 'KING LONG') {
            $kingLong = 'KING LONG';
        }
        if ($brand[$i] == 'FUSO') {
            $fuso = 'FUSO';
        }
        if ($brand[$i] == 'HINO') {
            $hino = 'HINO';
        }
        if ($brand[$i] == 'MERCEDES TRUCK') {
            $mercedesTruck = 'MERCEDES TRUCK';
        }
        if ($brand[$i] == 'RENAULT TRUCK') {
            $renaultTruck = 'RENAULT TRUCK';
        }
        if ($brand[$i] == 'SINOTRUK') {
            $sinotruk = 'SINOTRUK';
        }
        if ($brand[$i] == 'TOYOTA BT') {
            $toyotaBt = 'TOYOTA BT';
        }
        if ($brand[$i] == 'TOYOTA FORKLIFT') {
            $toyotaForflift = 'TOYOTA FORKLIFT';
        }
        if ($brand[$i] == 'JCB') {
            $jcb = 'JCB';
        }
        if ($brand[$i] == 'LOVOL') {
            $lovol = 'LOVOL';
        }
        if ($brand[$i] == 'CITROEN') {
            $citroen = 'CITROEN';
        }
        if ($brand[$i] == 'MERCEDES') {
            $mercedes = 'MERCEDES';
        }
        if ($brand[$i] == 'PEUGEOT') {
            $peugeot = 'PEUGEOT';
        }
        if ($brand[$i] == 'SUZUKI') {
            $suzuki = 'SUZUKI';
        }
        if ($brand[$i] == 'TOYOTA') {
            $toyota = 'TOYOTA';
        }
    }
    $transmissionFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Arbre de Transmission",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $transmissionDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Arbre de Transmission",
            ],
            ["type" => "Declaratif"],
            ["active" => true],
        ],
    ]);
    $transmissionMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Arbre de Transmission",
            ],
            ["active" => true],
        ],
    ]);
    $assistanceConduiteFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Assistance à la Conduite",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $assistanceConduiteDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Assistance à la Conduite",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $assistanceConduiteMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Assistance à la Conduite",
            ],
            ["active" => true],
        ],
    ]);
    $transfertFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Boite de Transfert"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $transfertDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Boite de Transfert"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $transfertMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Boite de Transfert"],
            ["active" => true],
        ],
    ]);
    $boiteFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Boite de Vitesse"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $boiteDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Boite de Vitesse"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $boiteMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Boite de Vitesse"],
            ["active" => true],
        ],
    ]);
    $boiteManFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse Mécanique",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $boiteManDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse Mécanique",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $boiteManMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse Mécanique",
            ],
            ["active" => true],
        ],
    ]);
    $boiteAutoFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse Automatique",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $boiteAutoDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse Automatique",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $boiteAutoMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse Automatique",
            ],
            ["active" => true],
        ],
    ]);
    $boiteVaCoFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse à Variation Continue",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $boiteVaCoDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse à Variation Continue",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $boiteVaCoMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Boite de Vitesse à Variation Continue",
            ],
            ["active" => true],
        ],
    ]);
    $climatisationFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Climatisation"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $climatisationDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Climatisation"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $climatisationMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Climatisation"],
            ["active" => true],
        ],
    ]);
    $demiFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Demi Arbre de Roue"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $demiDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Demi Arbre de Roue"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $demiMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Demi Arbre de Roue"],
            ["active" => true],
        ],
    ]);
    $directionFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Direction"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $directionDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Direction"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $directionMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Direction"],
            ["active" => true],
        ],
    ]);
    $electriciteFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Electricité et Electronique",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $electriciteDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Electricité et Electronique",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $electriciteMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Electricité et Electronique",
            ],
            ["active" => true],
        ],
    ]);
    $freiFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Freinage"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $freiDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Freinage"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $freiMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Freinage"],
            ["active" => true],
        ],
    ]);
    $freinageElecFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Electromagnétique",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $freinageElecDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Electromagnétique",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $freinageElecMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Electromagnétique",
            ],
            ["active" => true],
        ],
    ]);
    $freinageFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Hydraulique",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $freinageDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Hydraulique",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $freinageMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Hydraulique",
            ],
            ["active" => true],
        ],
    ]);
    $freinFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Pneumatique",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $freinDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Pneumatique",
            ],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $freinMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Freinage Pneumatique",
            ],
            ["active" => true],
        ],
    ]);
    $hydrauliqueFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Hydraulique"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $hydrauliqueDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Hydraulique"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $hydrauliqueMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Hydraulique"],
            ["active" => true],
        ],
    ]);
    $moteurDieselFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Diesel"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $moteurDieselDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Diesel"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $moteurDieselMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Diesel"],
            ["active" => true],
        ],
    ]);
    $moteurElecFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Electrique"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $moteurElecDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Electrique"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $moteurElecMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Electrique"],
            ["active" => true],
        ],
    ]);
    $moteurEssenceFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Essence"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $moteurEssenceDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Essence"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $moteurEssenceMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Essence"],
            ["active" => true],
        ],
    ]);
    $moteurThermiqueFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Thermique"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $moteurThermiqueDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Thermique"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $moteurThermiqueMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Moteur Thermique"],
            ["active" => true],
        ],
    ]);
    $multiplexageFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Réseaux de Communication"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $multiplexageDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Réseaux de Communication"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $multiplexageMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Réseaux de Communication"],
            ["active" => true],
        ],
    ]);
    $pneuFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Pneumatique"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $pneuDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Pneumatique"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $pneuMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Pneumatique"],
            ["active" => true],
        ],
    ]);
    $pontFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Pont"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $pontDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Pont"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $pontMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Pont"],
            ["active" => true],
        ],
    ]);
    $reducteurFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Reducteur"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $reducteurDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Reducteur"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $reducteurMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Reducteur"],
            ["active" => true],
        ],
    ]);
    $suspensionFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $suspensionDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $suspensionMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension"],
            ["active" => true],
        ],
    ]);
    $suspensionLameFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension à Lame"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $suspensionLameDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension à Lame"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $suspensionLameMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension à Lame"],
            ["active" => true],
        ],
    ]);
    $suspensionRessortFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension Ressort"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $suspensionRessortDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension Ressort"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $suspensionRessortMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Suspension Ressort"],
            ["active" => true],
        ],
    ]);
    $suspensionPneumatiqueFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Suspension Pneumatique",
            ],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $suspensionPneumatiqueDecla = $results->findOne(
        [
            '$and' => [
                [
                    "user" => $userObjId,
                ],
                ["level" => $level],
                ["numberTest" => +$numberTest],
                [
                    "speciality" =>
                        "Suspension Pneumatique",
                ],
                ["type" => "Declaratif"],
                ["typeR" => "Technicien"],
            ["active" => true],
            ],
        ]
    );
    $suspensionPneumatiqueMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            [
                "speciality" =>
                    "Suspension Pneumatique",
            ],
            ["active" => true],
        ],
    ]);
    $transversaleFac = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Transversale"],
            ["type" => "Factuel"],
            ["active" => true],
        ],
    ]);
    $transversaleDecla = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Transversale"],
            ["type" => "Declaratif"],
            ["typeR" => "Technicien"],
            ["active" => true],
        ],
    ]);
    $transversaleMa = $results->findOne([
        '$and' => [
            [
                "user" => $userObjId,
            ],
            [
                "manager" => new MongoDB\BSON\ObjectId(
                    $managerStr
                ),
            ],
            ["level" => $level],
            ["numberTest" => +$numberTest],
            ["speciality" => "Transversale"],
            ["active" => true],
        ],
    ]);
    $resultFac = $results->findOne([
        '$and' => [
            ["user" => $userObjId],
            ["type" => "Factuel"],
            ["typeR" => "Technicien"],
            ["numberTest" => +$numberTest],
            ["level" => $level],
            ["active" => true],
        ],
    ]);
    $resultDecla = $results->findOne([
        '$and' => [
            ["user" => $userObjId],
            ["type" => "Declaratif"],
            ["typeR" => "Techniciens"],
            ["numberTest" => +$numberTest],
            ["level" => $level],
            ["active" => true],
        ],
    ]);
    $resultMa = $results->findOne([
        '$and' => [
            ["user" => $userObjId],
            ["manager" => new MongoDB\BSON\ObjectId($managerStr)],
            ["typeR" => "Managers"],
            ["numberTest" => +$numberTest],
            ["level" => $level],
            ["active" => true],
        ],
    ]);
    $resultTechMa = $results->findOne([
        '$and' => [
            ["user" => $userObjId],
            ["manager" => new MongoDB\BSON\ObjectId($managerStr)],
            ["typeR" => "Technicien - Manager"],
            ["numberTest" => +$numberTest],
            ["level" => $level],
            ["active" => true],
        ],
    ]);
    $transmissionTotalFac = $transmissionFac->total ?? 0;
    $assistanceConduiteTotalFac = $assistanceConduiteFac->total ?? 0;
    $transfertTotalFac = $transfertFac->total ?? 0;
    $boiteTotalFac = $boiteFac->total ?? 0;
    $boiteManTotalFac = $boiteManFac->total ?? 0;
    $boiteAutoTotalFac = $boiteAutoFac->total ?? 0;
    $boiteVaCoTotalFac = $boiteVaCoFac->total ?? 0;
    $climatisationTotalFac = $climatisationFac->total ?? 0;
    $demiTotalFac = $demiFac->total ?? 0;
    $directionTotalFac = $directionFac->total ?? 0;
    $electriciteTotalFac = $electriciteFac->total ?? 0;
    $freiTotalFac = $freiFac->total ?? 0;
    $freinageElecTotalFac = $freinageElecFac->total ?? 0;
    $freinageTotalFac = $freinageFac->total ?? 0;
    $freinTotalFac = $freinFac->total ?? 0;
    $hydrauliqueTotalFac = $hydrauliqueFac->total ?? 0;
    $moteurDieselTotalFac = $moteurDieselFac->total ?? 0;
    $moteurEssenceTotalFac = $moteurEssenceFac->total ?? 0;
    $moteurElecTotalFac = $moteurElecFac->total ?? 0;
    $moteurThermiqueTotalFac = $moteurThermiqueFac->total ?? 0;
    $multiplexageTotalFac = $multiplexageFac->total ?? 0;
    $pneuTotalFac = $pneuFac->total ?? 0;
    $pontTotalFac = $pontFac->total ?? 0;
    $reducteurTotalFac = $reducteurFac->total ?? 0;
    $suspensionTotalFac = $suspensionFac->total ?? 0;
    $suspensionLameTotalFac = $suspensionLameFac->total ?? 0;
    $suspensionRessortTotalFac = $suspensionRessortFac->total ?? 0;
    $suspensionPneumatiqueTotalFac = $suspensionPneumatiqueFac->total ?? 0;
    $transversaleTotalFac = $transversaleFac->total ?? 0;
    
    $transmissionTotalDecla = $transmissionDecla->total ?? 0;
    $assistanceConduiteTotalDecla = $assistanceConduiteDecla->total ?? 0;
    $transfertTotalDecla = $transfertDecla->total ?? 0;
    $boiteTotalDecla = $boiteDecla->total ?? 0;
    $boiteManTotalDecla = $boiteManDecla->total ?? 0;
    $boiteAutoTotalDecla = $boiteAutoDecla->total ?? 0;
    $boiteVaCoTotalDecla = $boiteVaCoDecla->total ?? 0;
    $climatisationTotalDecla = $climatisationDecla->total ?? 0;
    $demiTotalDecla = $demiDecla->total ?? 0;
    $directionTotalDecla = $directionDecla->total ?? 0;
    $electriciteTotalDecla = $electriciteDecla->total ?? 0;
    $freiTotalDecla = $freiDecla->total ?? 0;
    $freinageElecTotalDecla = $freinageElecDecla->total ?? 0;
    $freinageTotalDecla = $freinageDecla->total ?? 0;
    $freinTotalDecla = $freinDecla->total ?? 0;
    $hydrauliqueTotalDecla = $hydrauliqueDecla->total ?? 0;
    $moteurDieselTotalDecla = $moteurDieselDecla->total ?? 0;
    $moteurEssenceTotalDecla = $moteurEssenceDecla->total ?? 0;
    $moteurElecTotalDecla = $moteurElecDecla->total ?? 0;
    $moteurThermiqueTotalDecla = $moteurThermiqueDecla->total ?? 0;
    $multiplexageTotalDecla = $multiplexageDecla->total ?? 0;
    $pneuTotalDecla = $pneuDecla->total ?? 0;
    $pontTotalDecla = $pontDecla->total ?? 0;
    $reducteurTotalDecla = $reducteurDecla->total ?? 0;
    $suspensionTotalDecla = $suspensionDecla->total ?? 0;
    $suspensionLameTotalDecla = $suspensionLameDecla->total ?? 0;
    $suspensionRessortTotalDecla = $suspensionRessortDecla->total ?? 0;
    $suspensionPneumatiqueTotalDecla = $suspensionPneumatiqueDecla->total ?? 0;
    $transversaleTotalDecla = $transversaleDecla->total ?? 0;

    $transmissionScoreFac = $transmissionFac->score ?? 0;
    $assistanceConduiteScoreFac = $assistanceConduiteFac->score ?? 0;
    $transfertScoreFac = $transfertFac->score ?? 0;
    $boiteScoreFac = $boiteFac->score ?? 0;
    $boiteManScoreFac = $boiteManFac->score ?? 0;
    $boiteAutoScoreFac = $boiteAutoFac->score ?? 0;
    $boiteVaCoScoreFac = $boiteVaCoFac->score ?? 0;
    $climatisationScoreFac = $climatisationFac->score ?? 0;
    $demiScoreFac = $demiFac->score ?? 0;
    $directionScoreFac = $directionFac->score ?? 0;
    $electriciteScoreFac = $electriciteFac->score ?? 0;
    $freiScoreFac = $freiFac->score ?? 0;
    $freinageElecScoreFac = $freinageElecFac->score ?? 0;
    $freinageScoreFac = $freinageFac->score ?? 0;
    $freinScoreFac = $freinFac->score ?? 0;
    $hydrauliqueScoreFac = $hydrauliqueFac->score ?? 0;
    $moteurDieselScoreFac = $moteurDieselFac->score ?? 0;
    $moteurEssenceScoreFac = $moteurEssenceFac->score ?? 0;
    $moteurElecScoreFac = $moteurElecFac->score ?? 0;
    $moteurThermiqueScoreFac = $moteurThermiqueFac->score ?? 0;
    $multiplexageScoreFac = $multiplexageFac->score ?? 0;
    $pneuScoreFac = $pneuFac->score ?? 0;
    $pontScoreFac = $pontFac->score ?? 0;
    $reducteurScoreFac = $reducteurFac->score ?? 0;
    $suspensionScoreFac = $suspensionFac->score ?? 0;
    $suspensionLameScoreFac = $suspensionLameFac->score ?? 0;
    $suspensionRessortScoreFac = $suspensionRessortFac->score ?? 0;
    $suspensionPneumatiqueScoreFac = $suspensionPneumatiqueFac->score ?? 0;
    $transversaleScoreFac = $transversaleFac->score ?? 0;
    
    $transmissionScoreDecla = $transmissionDecla->score ?? 0;
    $assistanceConduiteScoreDecla = $assistanceConduiteDecla->score ?? 0;
    $transfertScoreDecla = $transfertDecla->score ?? 0;
    $boiteScoreDecla = $boiteDecla->score ?? 0;
    $boiteManScoreDecla = $boiteManDecla->score ?? 0;
    $boiteAutoScoreDecla = $boiteAutoDecla->score ?? 0;
    $boiteVaCoScoreDecla = $boiteVaCoDecla->score ?? 0;
    $climatisationScoreDecla = $climatisationDecla->score ?? 0;
    $demiScoreDecla = $demiDecla->score ?? 0;
    $directionScoreDecla = $directionDecla->score ?? 0;
    $electriciteScoreDecla = $electriciteDecla->score ?? 0;
    $freiScoreDecla = $freiDecla->score ?? 0;
    $freinageElecScoreDecla = $freinageElecDecla->score ?? 0;
    $freinageScoreDecla = $freinageDecla->score ?? 0;
    $freinScoreDecla = $freinDecla->score ?? 0;
    $hydrauliqueScoreDecla = $hydrauliqueDecla->score ?? 0;
    $moteurDieselScoreDecla = $moteurDieselDecla->score ?? 0;
    $moteurEssenceScoreDecla = $moteurEssenceDecla->score ?? 0;
    $moteurElecScoreDecla = $moteurElecDecla->score ?? 0;
    $moteurThermiqueScoreDecla = $moteurThermiqueDecla->score ?? 0;
    $multiplexageScoreDecla = $multiplexageDecla->score ?? 0;
    $pneuScoreDecla = $pneuDecla->score ?? 0;
    $pontScoreDecla = $pontDecla->score ?? 0;
    $reducteurScoreDecla = $reducteurDecla->score ?? 0;
    $suspensionScoreDecla = $suspensionDecla->score ?? 0;
    $suspensionLameScoreDecla = $suspensionLameDecla->score ?? 0;
    $suspensionRessortScoreDecla = $suspensionRessortDecla->score ?? 0;
    $suspensionPneumatiqueScoreDecla = $suspensionPneumatiqueDecla->score ?? 0;
    $transversaleScoreDecla = $transversaleDecla->score ?? 0;
    
    $transmissionScoreMa = $transmissionMa->score ?? 0;
    $assistanceConduiteScoreMa = $assistanceConduiteMa->score ?? 0;
    $transfertScoreMa = $transfertMa->score ?? 0;
    $boiteScoreMa = $boiteMa->score ?? 0;
    $boiteManScoreMa = $boiteManMa->score ?? 0;
    $boiteAutoScoreMa = $boiteAutoMa->score ?? 0;
    $boiteVaCoScoreMa = $boiteVaCoMa->score ?? 0;
    $climatisationScoreMa = $climatisationMa->score ?? 0;
    $demiScoreMa = $demiMa->score ?? 0;
    $directionScoreMa = $directionMa->score ?? 0;
    $electriciteScoreMa = $electriciteMa->score ?? 0;
    $freiScoreMa = $freiMa->score ?? 0;
    $freinageElecScoreMa = $freinageElecMa->score ?? 0;
    $freinageScoreMa = $freinageMa->score ?? 0;
    $freinScoreMa = $freinMa->score ?? 0;
    $hydrauliqueScoreMa = $hydrauliqueMa->score ?? 0;
    $moteurDieselScoreMa = $moteurDieselMa->score ?? 0;
    $moteurEssenceScoreMa = $moteurEssenceMa->score ?? 0;
    $moteurElecScoreMa = $moteurElecMa->score ?? 0;
    $moteurThermiqueScoreMa = $moteurThermiqueMa->score ?? 0;
    $multiplexageScoreMa = $multiplexageMa->score ?? 0;
    $pneuScoreMa = $pneuMa->score ?? 0;
    $pontScoreMa = $pontMa->score ?? 0;
    $reducteurScoreMa = $reducteurMa->score ?? 0;
    $suspensionScoreMa = $suspensionMa->score ?? 0;
    $suspensionLameScoreMa = $suspensionLameMa->score ?? 0;
    $suspensionRessortScoreMa = $suspensionRessortMa->score ?? 0;
    $suspensionPneumatiqueScoreMa = $suspensionPneumatiqueMa->score ?? 0;
    $transversaleScoreMa = $transversaleMa->score ?? 0;

    $percentageFac    = ($resultFac['total']    ?? 0) > 0 ? (($resultFac['score']    ?? 0) * 100) / $resultFac['total']    : 0;
    $percentageTechMa = ($resultTechMa['total'] ?? 0) > 0 ? (($resultTechMa['score'] ?? 0) * 100) / $resultTechMa['total'] : 0;

    $scoreTransmission = 0;
    if (isset($transmissionDecla)) {
        for ($i = 0; $i < count($transmissionDecla["answers"]); ++$i) {
            if (
                $transmissionDecla["answers"][$i] == "Oui" &&
                $transmissionMa["answers"][$i] == "Oui"
            ) {
                ++$scoreTransmission;
            }
        }
    }
    $scoreTransfert = 0;
    if (isset($transfertDecla)) {
        for ($i = 0; $i < count($transfertDecla["answers"]); ++$i) {
            if (
                $transfertDecla["answers"][$i] == "Oui" &&
                $transfertMa["answers"][$i] == "Oui"
            ) {
                ++$scoreTransfert;
            }
        }
    }
    $scoreAssistance= 0;
    if (isset($assistanceConduiteDecla)) {
        for ($i = 0; $i < count($assistanceConduiteDecla["answers"]); ++$i) {
            if (
                $assistanceConduiteDecla["answers"][$i] == "Oui" &&
                $assistanceConduiteMa["answers"][$i] == "Oui"
            ) {
                ++$scoreAssistance;
            }
        }
    }
    $scoreBoite = 0;
    if (isset($boiteDecla)) {
        for ($i = 0; $i < count($boiteDecla["answers"]); ++$i) {
            if (
                $boiteDecla["answers"][$i] == "Oui" &&
                $boiteMa["answers"][$i] == "Oui"
            ) {
                ++$scoreBoite;
            }
        }
    }
    $scoreBoiteMan = 0;
    if (isset($boiteManDecla)) {
        for ($i = 0; $i < count($boiteManDecla["answers"]); ++$i) {
            if (
                $boiteManDecla["answers"][$i] == "Oui" &&
                $boiteManMa["answers"][$i] == "Oui"
            ) {
                ++$scoreBoiteMan;
            }
        }
    }
    $scoreBoiteAuto = 0;
    if (isset($boiteAutoDecla)) {
        for ($i = 0; $i < count($boiteAutoDecla["answers"]); ++$i) {
            if (
                $boiteAutoDecla["answers"][$i] == "Oui" &&
                $boiteAutoMa["answers"][$i] == "Oui"
            ) {
                ++$scoreBoiteAuto;
            }
        }
    }
    $scoreBoiteVaCo = 0;
    if (isset($boiteVaCoDecla)) {
        for ($i = 0; $i < count($boiteVaCoDecla["answers"]); ++$i) {
            if (
                $boiteVaCoDecla["answers"][$i] == "Oui" &&
                $boiteVaCoMa["answers"][$i] == "Oui"
            ) {
                ++$scoreBoiteVaCo;
            }
        }
    }
    $scoreClim = 0;
    if (isset($climatisationDecla)) {
        for ($i = 0; $i < count($climatisationDecla["answers"]); ++$i) {
            if (
                $climatisationDecla["answers"][$i] == "Oui" &&
                $climatisationMa["answers"][$i] == "Oui"
            ) {
                ++$scoreClim;
            }
        }
    }
    $scoreDemi = 0;
    if (isset($demiDecla)) {
        for ($i = 0; $i < count($demiDecla["answers"]); ++$i) {
            if (
                $demiDecla["answers"][$i] == "Oui" &&
                $demiMa["answers"][$i] == "Oui"
            ) {
                ++$scoreDemi;
            }
        }
    }
    $scoreDirection = 0;
    if (isset($directionDecla)) {
        for ($i = 0; $i < count($directionDecla["answers"]); ++$i) {
            if (
                $directionDecla["answers"][$i] == "Oui" &&
                $directionMa["answers"][$i] == "Oui"
            ) {
                ++$scoreDirection;
            }
        }
    }
    $scoreElectricite = 0;
    if (isset($electriciteDecla)) {
        for ($i = 0; $i < count($electriciteDecla["answers"]); ++$i) {
            if (
                $electriciteDecla["answers"][$i] == "Oui" &&
                $electriciteMa["answers"][$i] == "Oui"
            ) {
                ++$scoreElectricite;
            }
        }
    }
    $scoreFrein = 0;
    if (isset($freiDecla)) {
        for ($i = 0; $i < count($freiDecla["answers"]); ++$i) {
            if (
                $freiDecla["answers"][$i] == "Oui" &&
                $freiMa["answers"][$i] == "Oui"
            ) {
                ++$scoreFrein;
            }
        }
    }
    $scoreFreinElec = 0;
    if (isset($freinageElecDecla)) {
        for ($i = 0; $i < count($freinageElecDecla["answers"]); ++$i) {
            if (
                $freinageElecDecla["answers"][$i] == "Oui" &&
                $freinageElecMa["answers"][$i] == "Oui"
            ) {
                ++$scoreFreinElec;
            }
        }
    }
    $scoreFreinHydro = 0;
    if (isset($freinageDecla)) {
        for ($i = 0; $i < count($freinageDecla["answers"]); ++$i) {
            if (
                $freinageDecla["answers"][$i] == "Oui" &&
                $freinageMa["answers"][$i] == "Oui"
            ) {
                ++$scoreFreinHydro;
            }
        }
    }
    $scoreFreinPneu = 0;
    if (isset($freinDecla)) {
        for ($i = 0; $i < count($freinDecla["answers"]); ++$i) {
            if (
                $freinDecla["answers"][$i] == "Oui" &&
                $freinMa["answers"][$i] == "Oui"
            ) {
                ++$scoreFreinPneu;
            }
        }
    }
    $scoreHydro = 0;
    if (isset($hydrauliqueDecla)) {
        for ($i = 0; $i < count($hydrauliqueDecla["answers"]); ++$i) {
            if (
                $hydrauliqueDecla["answers"][$i] == "Oui" &&
                $hydrauliqueMa["answers"][$i] == "Oui"
            ) {
                ++$scoreHydro;
            }
        }
    }
    $scoreMoteurDiesel = 0;
    if (isset($moteurDieselDecla)) {
        for ($i = 0; $i < count($moteurDieselDecla["answers"]); ++$i) {
            if (
                $moteurDieselDecla["answers"][$i] == "Oui" &&
                $moteurDieselMa["answers"][$i] == "Oui"
            ) {
                ++$scoreMoteurDiesel;
            }
        }
    }
    $scoreMoteurElec = 0;
    if (isset($moteurElecDecla)) {
        for ($i = 0; $i < count($moteurElecDecla["answers"]); ++$i) {
            if (
                $moteurElecDecla["answers"][$i] == "Oui" &&
                $moteurElecMa["answers"][$i] == "Oui"
            ) {
                ++$scoreMoteurElec;
            }
        }
    }
    $scoreMoteurEssence = 0;
    if (isset($moteurEssenceDecla)) {
        for ($i = 0; $i < count($moteurEssenceDecla["answers"]); ++$i) {
            if (
                $moteurEssenceDecla["answers"][$i] == "Oui" &&
                $moteurEssenceMa["answers"][$i] == "Oui"
            ) {
                ++$scoreMoteurEssence;
            }
        }
    }
    $scoreMoteurThermique = 0;
    if (isset($moteurThermiqueDecla)) {
        for ($i = 0; $i < count($moteurThermiqueDecla["answers"]); ++$i) {
            if (
                $moteurThermiqueDecla["answers"][$i] == "Oui" &&
                $moteurThermiqueMa["answers"][$i] == "Oui"
            ) {
                ++$scoreMoteurThermique;
            }
        }
    }
    $scoreMultiplexage = 0;
    if (isset($multiplexageDecla)) {
        for ($i = 0; $i < count($multiplexageDecla["answers"]); ++$i) {
            if (
                $multiplexageDecla["answers"][$i] == "Oui" &&
                $multiplexageMa["answers"][$i] == "Oui"
            ) {
                ++$scoreMultiplexage;
            }
        }
    }
    $scorePneu = 0;
    if (isset($pneuDecla)) {
        for ($i = 0; $i < count($pneuDecla["answers"]); ++$i) {
            if (
                $pneuDecla["answers"][$i] == "Oui" &&
                $pneuMa["answers"][$i] == "Oui"
            ) {
                ++$scorePneu;
            }
        }
    }
    $scorePont = 0;
    if (isset($pontDecla)) {
        for ($i = 0; $i < count($pontDecla["answers"]); ++$i) {
            if (
                $pontDecla["answers"][$i] == "Oui" &&
                $pontMa["answers"][$i] == "Oui"
            ) {
                ++$scorePont;
            }
        }
    }
    $scoreRed = 0;
    if (isset($reducteurDecla)) {
        for ($i = 0; $i < count($reducteurDecla["answers"]); ++$i) {
            if (
                $reducteurDecla["answers"][$i] == "Oui" &&
                $reducteurMa["answers"][$i] == "Oui"
            ) {
                ++$scoreRed;
            }
        }
    }
    $scoreSuspension = 0;
    if (isset($suspensionDecla)) {
        for ($i = 0; $i < count($suspensionDecla["answers"]); ++$i) {
            if (
                $suspensionDecla["answers"][$i] == "Oui" &&
                $suspensionMa["answers"][$i] == "Oui"
            ) {
                ++$scoreSuspension;
            }
        }
    }
    $scoreSuspensionLame = 0;
    if (isset($suspensionLameDecla)) {
        for ($i = 0; $i < count($suspensionLameDecla["answers"]); ++$i) {
            if (
                $suspensionLameDecla["answers"][$i] == "Oui" &&
                $suspensionLameMa["answers"][$i] == "Oui"
            ) {
                ++$scoreSuspensionLame;
            }
        }
    }
    $scoreSuspensionRessort = 0;
    if (isset($suspensionRessortDecla)) {
        for ($i = 0; $i < count($suspensionRessortDecla["answers"]); ++$i) {
            if (
                $suspensionRessortDecla["answers"][$i] == "Oui" &&
                $suspensionRessortMa["answers"][$i] == "Oui"
            ) {
                ++$scoreSuspensionRessort;
            }
        }
    }
    $scoreSuspensionPneu = 0;
    if (isset($suspensionPneumatiqueDecla)) {
        for ($i = 0; $i < count($suspensionPneumatiqueDecla["answers"]); ++$i) {
            if (
                $suspensionPneumatiqueDecla["answers"][$i] == "Oui" &&
                $suspensionPneumatiqueMa["answers"][$i] == "Oui"
            ) {
                ++$scoreSuspensionPneu;
            }
        }
    }
    $scoreTransversale = 0;
    if (isset($transversaleDecla)) {
        for ($i = 0; $i < count($transversaleDecla["answers"]); ++$i) {
            if (
                $transversaleDecla["answers"][$i] == "Oui" &&
                $transversaleMa["answers"][$i] == "Oui"
            ) {
                ++$scoreTransversale;
            }
        }
    }
    
    if (isset($toyota) == 'TOYOTA') {
        $toyotaFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
        $toyotaDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
    
        $toyotaScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
        
        $toyotaScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
        $toyotaScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
        $toyotaScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
    }
    if (isset($suzuki) == 'SUZUKI') {
        $suzukiFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $transversaleTotalFac;
        $suzukiDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $transversaleTotalDecla;
        
        $suzukiScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreTransversale;
        
        $suzukiScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $transversaleScoreFac;
        $suzukiScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $transversaleScoreDecla;
        $suzukiScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurElecScoreMa + $moteurDieselScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $transversaleScoreMa;
    }
    if (isset($mercedes) == 'MERCEDES') {
        $mercedesFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
        $mercedesDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla+ $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
        
        $mercedesScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
        
        $mercedesScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
        $mercedesScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
        $mercedesScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
    }
    if (isset($peugeot) == 'PEUGEOT') {
        $peugeotFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
        $peugeotDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;

        $peugeotScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
        
        $peugeotScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
        $peugeotScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
        $peugeotScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
    }
    if (isset($citroen) == 'CITROEN') {
        $citroenFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $boiteAutoTotalFac + $boiteVaCoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionRessortTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
        $citroenDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $boiteAutoTotalDecla + $boiteVaCoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionRessortTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;

        $citroenScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreBoiteAuto + $scoreBoiteVaCo + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreSuspensionPneu + $scoreTransversale;
        
        $citroenScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $boiteAutoScoreFac + $boiteVaCoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionRessortScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
        $citroenScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $boiteAutoScoreDecla + $boiteVaCoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionRessortScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
        $citroenScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $boiteAutoScoreMa + $boiteVaCoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionRessortScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
    }
    if (isset($kingLong) == 'KING LONG') {
        $kingLongFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
        $kingLongDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
        
        $kingLongScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionRessort + $scoreTransversale;
        
        $kingLongScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
        $kingLongScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
        $kingLongScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
    }
    if (isset($fuso) == 'FUSO') {
        $fusoFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
        $fusoDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
        
        $fusoScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
        
        $fusoScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
        $fusoScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
        $fusoScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
    }
    if (isset($hino) == 'HINO') {
        $hinoFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $boiteTotalFac + $boiteManTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
        $hinoDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
        
        $hinoScore = $scoreTransmission + $scoreAssistance + $scoreBoite + $scoreBoiteMan + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
        
        $hinoScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $boiteScoreFac + $boiteManScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
        $hinoScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
        $hinoScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $boiteScoreMa + $boiteManScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
    }
    if (isset($renaultTruck) == 'RENAULT TRUCK') {
        $renaultTruckFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
        $renaultTruckDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
        
        $renaultTruckScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionPneu + $scoreTransversale;
        
        $renaultTruckScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
        $renaultTruckScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
        $renaultTruckScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
    }
    if (isset($mercedesTruck) == 'MERCEDES TRUCK') {
        $mercedesTruckFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $suspensionPneumatiqueTotalFac + $transversaleTotalFac;
        $mercedesTruckDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $suspensionPneumatiqueTotalDecla + $transversaleTotalDecla;
        
        $mercedesTruckScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreSuspensionPneu + $scoreTransversale;
        
        $mercedesTruckScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $suspensionPneumatiqueScoreFac + $transversaleScoreFac;
        $mercedesTruckScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $suspensionPneumatiqueScoreDecla + $transversaleScoreDecla;
        $mercedesTruckScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $suspensionPneumatiqueScoreMa + $transversaleScoreMa;
    }
    if (isset($sinotruk) == 'SINOTRUK') {
        $sinotrukFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $suspensionTotalFac + $suspensionLameTotalFac + $transversaleTotalFac;
        $sinotrukDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $suspensionTotalDecla + $suspensionLameTotalDecla + $transversaleTotalDecla;
        
        $sinotrukScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinPneu + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreSuspension + $scoreSuspensionLame + $scoreTransversale;
        
        $sinotrukScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $suspensionScoreFac + $suspensionLameScoreFac + $transversaleScoreFac;
        $sinotrukScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $suspensionScoreDecla + $suspensionLameScoreDecla + $transversaleScoreDecla;
        $sinotrukScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $suspensionScoreMa + $suspensionLameScoreMa + $transversaleScoreMa;
    }
    if (isset($jcb) == 'JCB') {
        $jcbFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $transversaleTotalFac;
        $jcbDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $transversaleTotalDecla;
        
        $jcbScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreTransversale;
        
        $jcbScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $transversaleScoreFac;
        $jcbScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $transversaleScoreDecla;
        $jcbScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $transversaleScoreMa;
    }
    if (isset($lovol) == 'LOVOL') {
        $lovolFac = $transmissionTotalFac + $assistanceConduiteTotalFac + $transfertTotalFac + $boiteTotalFac + $boiteManTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $transversaleTotalFac;
        $lovolDecla = $transmissionTotalDecla + $assistanceConduiteTotalDecla + $transfertTotalDecla + $boiteTotalDecla + $boiteManTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $transversaleTotalDecla;
        
        $lovolScore = $scoreTransmission + $scoreAssistance + $scoreTransfert + $scoreBoite + $scoreBoiteMan + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreTransversale;
        
        $lovolScoreFac = $transmissionScoreFac + $assistanceConduiteScoreFac + $transfertScoreFac + $boiteScoreFac + $boiteManScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $transversaleScoreFac;
        $lovolScoreDecla = $transmissionScoreDecla + $assistanceConduiteScoreDecla + $transfertScoreDecla + $boiteScoreDecla + $boiteManScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $transversaleScoreDecla;
        $lovolScoreMa = $transmissionScoreMa + $assistanceConduiteScoreMa + $transfertScoreMa + $boiteScoreMa + $boiteManScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $transversaleScoreMa;
    }
    if (isset($toyotaBt) == 'TOYOTA BT') {
        $toyotaBtFac = $assistanceConduiteTotalFac + $boiteTotalFac + $climatisationTotalFac + $directionTotalFac + $electriciteTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurElecTotalFac + $multiplexageTotalFac + $pneuTotalFac + $reducteurTotalFac + $transversaleTotalFac;
        $toyotaBtDecla = $assistanceConduiteTotalDecla + $boiteTotalDecla + $climatisationTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurElecTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $reducteurTotalDecla + $transversaleTotalDecla;
        
        $toyotaBtScore = $scoreAssistance + $scoreBoite + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFreinElec + $scoreFreinHydro + $scoreMoteurElec + $scoreMultiplexage + $scorePneu + $scoreRed + $scoreTransversale;
        
        $toyotaBtScoreFac = $assistanceConduiteScoreFac + $boiteScoreFac + $climatisationScoreFac + $directionScoreFac + $electriciteScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurElecScoreFac + $multiplexageScoreFac + $pneuScoreFac + $reducteurScoreFac + $transversaleScoreFac;
        $toyotaBtScoreDecla = $assistanceConduiteScoreDecla + $boiteScoreDecla + $climatisationScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurElecScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $reducteurScoreDecla + $transversaleScoreDecla;
        $toyotaBtScoreMa = $assistanceConduiteScoreMa + $boiteScoreMa + $climatisationScoreMa + $directionScoreMa + $electriciteScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurElecScoreMa + $multiplexageScoreMa + $pneuScoreMa + $reducteurScoreMa + $transversaleScoreMa;
    }
    if (isset($toyotaForflift) == 'TOYOTA FORKLIFT') {
        $toyotaForfliftFac = $assistanceConduiteTotalFac + $boiteTotalFac + $boiteAutoTotalFac + $climatisationTotalFac + $demiTotalFac + $directionTotalFac + $electriciteTotalFac + $freiTotalFac + $freinageElecTotalFac + $freinageTotalFac +  $hydrauliqueTotalFac + $moteurDieselTotalFac + $moteurElecTotalFac + $moteurEssenceTotalFac + $moteurThermiqueTotalFac + $multiplexageTotalFac + $pneuTotalFac + $pontTotalFac + $reducteurTotalFac + $transversaleTotalFac;
        $toyotaForfliftDecla = $assistanceConduiteTotalDecla + $boiteTotalDecla + $boiteAutoTotalDecla + $climatisationTotalDecla + $demiTotalDecla + $directionTotalDecla + $electriciteTotalDecla + $freiTotalDecla + $freinageElecTotalDecla + $freinageTotalDecla +  $hydrauliqueTotalDecla + $moteurDieselTotalDecla + $moteurElecTotalDecla + $moteurEssenceTotalDecla + $moteurThermiqueTotalDecla + $multiplexageTotalDecla + $pneuTotalDecla + $pontTotalDecla + $reducteurTotalDecla + $transversaleTotalDecla;
        
        $toyotaForfliftScore = $scoreAssistance + $scoreBoite + $scoreBoiteAuto + $scoreClim + $scoreDemi + $scoreDirection + $scoreElectricite + $scoreHydro + $scoreFrein + $scoreFreinElec + $scoreFreinHydro + $scoreMoteurDiesel + $scoreMoteurElec + $scoreMoteurEssence + $scoreMoteurThermique + $scoreMultiplexage + $scorePneu + $scorePont + $scoreRed + $scoreTransversale;
        
        $toyotaForfliftScoreFac = $assistanceConduiteScoreFac + $boiteScoreFac + $boiteAutoScoreFac + $climatisationScoreFac + $demiScoreFac + $directionScoreFac + $electriciteScoreFac + $freiScoreFac + $freinageElecScoreFac + $freinageScoreFac +  $hydrauliqueScoreFac + $moteurDieselScoreFac + $moteurElecScoreFac + $moteurEssenceScoreFac + $moteurThermiqueScoreFac + $multiplexageScoreFac + $pneuScoreFac + $pontScoreFac + $reducteurScoreFac + $transversaleScoreFac;
        $toyotaForfliftScoreDecla = $assistanceConduiteScoreDecla + $boiteScoreDecla + $boiteAutoScoreDecla + $climatisationScoreDecla + $demiScoreDecla + $directionScoreDecla + $electriciteScoreDecla + $freiScoreDecla + $freinageElecScoreDecla + $freinageScoreDecla +  $hydrauliqueScoreDecla + $moteurDieselScoreDecla + $moteurElecScoreDecla + $moteurEssenceScoreDecla + $moteurThermiqueScoreDecla + $multiplexageScoreDecla + $pneuScoreDecla + $pontScoreDecla + $reducteurScoreDecla + $transversaleScoreDecla;
        $toyotaForfliftScoreMa = $assistanceConduiteScoreMa + $boiteScoreMa + $boiteAutoScoreMa + $climatisationScoreMa + $demiScoreMa + $directionScoreMa + $electriciteScoreMa + $freiScoreMa + $freinageElecScoreMa + $freinageScoreMa +  $hydrauliqueScoreMa + $moteurDieselScoreMa + $moteurElecScoreMa + $moteurEssenceScoreMa + $moteurThermiqueScoreMa + $multiplexageScoreMa + $pneuScoreMa + $pontScoreMa + $reducteurScoreMa + $transversaleScoreMa;
    }
    include_once "../language.php";
    ?>
<?php include_once "partials/header.php"; ?>
<title><?php echo $result_tech ?? ''; ?> | CFAO Mobility Academy</title>

<!--begin::Body-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content" data-select2-id="select2-data-kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bold my-1" style="font-size: 30px;">
                    <?php echo $result_by_brand ?>
                    <?php echo htmlspecialchars(($technician->firstName ?? '') . ' ' . ($technician->lastName ?? '')); ?>
                </h1>
                <!--end::Title-->
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <!--begin::Post-->
    <div class="post d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <!--begin::Card title-->
                <div class="card-title">
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-customer-table-toolbar="base">
                        <!--begin::Export-->
                        <button type="button" id="excel" class="btn btn-light-primary me-3" data-bs-toggle="modal"
                            data-bs-target="#kt_customers_export_modal">
                            <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span
                                    class="path2"></span></i> <?php echo $excel ?>
                        </button>
                        <!--end::Export-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive">
                        <table aria-describedby=""
                            class="table align-middle table-bordered table-row-dashed gy-5 dataTable no-footer"
                            id="kt_customers_table">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bold text-uppercase gs-0">
                                    <th class="min-w-125px sorting bg-primary text-white text-center table-light"
                                        tabindex="0" aria-controls="kt_customers_table" colspan="17"
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; font-size: 20px; ">
                                        <?php echo $result_mesure ?></th>
                                <tr></tr>
                                <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" rowspan="3"
                                    aria-label="Email: activate to sort column ascending">
                                    <?php echo $groupe_fonctionnel ?></th>
                                <!-- <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending">
                                    Bâteaux</th> -->
                                <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending">
                                    <?php echo $bus ?></th>
                                <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="5"
                                    aria-label="Email: activate to sort column ascending">
                                    <?php echo $camions ?></th>
                                <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                    aria-label="Email: activate to sort column ascending">
                                    <?php echo $chariots ?></th>
                                <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="2"
                                    aria-label="Email: activate to sort column ascending">
                                    <?php echo $engins ?></th>
                                <!-- <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending">
                                    Moto</th> -->
                                <th class="min-w-10px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="5"
                                    aria-label="Email: activate to sort column ascending">
                                    <?php echo $vl ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" rowspan="3"
                                    aria-label="Email: activate to sort column ascending">
                                    <?php echo $global ?></th>
                                    <tr></tr>
                                <!-- <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    Yamaha</th> -->
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $kingLong ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $fuso ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $hino ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.366px;">
                                    <?php echo $mercedesTruck ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $renaultTruck ?></th>
                                <th class="min-w-135px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $sinotruk ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $toyotaBt ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $toyotaForklift ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $jcb ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $lovol ?></th>
                                <!-- <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    Yamaha</th> -->
                                <!-- <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    BYD</th> -->
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.366px;">
                                    <?php echo $citroen ?></th>
                                <th class="min-w-135px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $mercedes ?></th>
                                <!-- <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    Mitsubishi</th> -->
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $peugeot ?></th>
                                <th class="min-w-135px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.366px;">
                                    <?php echo $suzuki ?></th>
                                <th class="min-w-135px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table" colspan="1"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.366px;">
                                    <?php echo $toyota ?></th>
                                    <tr></tr>
                                <!-- <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    Connaissances</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Technicien</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Manager</th> -->
                                <!-- <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    Connaissances</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Technicien</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Manager</th> -->
                                <!-- <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    Connaissances</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Technicien</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Manager</th> -->
                                <!-- <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    Connaissances</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Technicien</th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    Tâches Professionnelles du Manager</th> -->
                                <!-- <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 175.266px;">
                                    <?php echo $global ?></th>
                                <th class="min-w-125px sorting  bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                    tabindex="0" aria-controls="kt_customers_table"
                                    aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                    <?php echo $global ?></th> -->
                            </thead>
                            <tbody class="fw-semibold text-gray-600" id="table">
                                <?php if (
                                    $transmissionFac &&
                                    $transmissionDecla &&
                                    $transmissionMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $arbre ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($jcbFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                     </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $transmissionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>  
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transmissionFac, $transmissionDecla, $$scoreTransmission)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $assistanceConduiteFac &&
                                    $assistanceConduiteDecla &&
                                    $assistanceConduiteMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $assistanceConduite ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $assistanceConduiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($assistanceConduiteFac, $assistanceConduiteDecla, $$scoreAssistance)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $transfertFac &&
                                    $transfertDecla &&
                                    $transfertMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $transfert ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($mercedesTruckFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($jcbFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $transfertFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?> 
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transfertFac, $transfertDecla, $$scoreTransfert)
                                    ?>%
                                    </td> 
                                </tr>
                                <?php } ?>
                                <?php if (
                                    $boiteFac &&
                                    $boiteDecla &&
                                    $boiteMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $boite_vitesse ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $boiteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteFac, $boiteDecla, $$scoreBoite)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $boiteAutoFac &&
                                    $boiteAutoDecla &&
                                    $boiteAutoMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $boite_vitesse_auto ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $boiteAutoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteAutoFac, $boiteAutoDecla, $$scoreBoiteAuto)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($citroenFac) && $boiteAutoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteAutoFac, $boiteAutoDecla, $$scoreBoiteAuto)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $boiteAutoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteAutoFac, $boiteAutoDecla, $$scoreBoiteAuto)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $boiteAutoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteAutoFac, $boiteAutoDecla, $$scoreBoiteAuto)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $boiteAutoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteAutoFac, $boiteAutoDecla, $$scoreBoiteAuto)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $boiteAutoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteAutoFac, $boiteAutoDecla, $$scoreBoiteAuto)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteAutoFac, $boiteAutoDecla, $$scoreBoiteAuto)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $boiteManFac &&
                                    $boiteManDecla &&
                                    $boiteManMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $boite_vitesse_meca ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($jcbFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $boiteManFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?> 
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteManFac, $boiteManDecla, $$scoreBoiteMan)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $boiteVaCoFac &&
                                    $boiteVaCoDecla &&
                                    $boiteVaCoMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $boite_vitesse_VC ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($citroenFac) && $boiteVaCoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteVaCoFac, $boiteVaCoDecla, $$scoreBoiteVaCo)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $boiteVaCoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteVaCoFac, $boiteVaCoDecla, $$scoreBoiteVaCo)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $boiteVaCoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteVaCoFac, $boiteVaCoDecla, $$scoreBoiteVaCo)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $boiteVaCoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteVaCoFac, $boiteVaCoDecla, $$scoreBoiteVaCo)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $boiteVaCoFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteVaCoFac, $boiteVaCoDecla, $$scoreBoiteVaCo)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?> 
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($boiteVaCoFac, $boiteVaCoDecla, $$scoreBoiteVaCo)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $climatisationFac &&
                                    $climatisationDecla &&
                                    $climatisationMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $clim ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($mercedesTruckFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $climatisationFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($climatisationFac, $climatisationDecla, $$scoreClim)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $demiFac &&
                                    $demiDecla &&
                                    $demiMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $demi ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $demiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($demiFac, $demiDecla, $$scoreDemi)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $directionFac &&
                                    $directionDecla &&
                                    $directionMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $direction ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $directionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?> 
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($directionFac, $directionDecla, $$scoreDirection)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $electriciteFac &&
                                    $electriciteDecla &&
                                    $electriciteMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $electricite ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $electriciteFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($electriciteFac, $electriciteDecla, $$scoreElectricite)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $freiFac &&
                                    $freiDecla &&
                                    $freiMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $freinage ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $freiFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freiFac, $freiDecla, $$scoreFrein)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $freinageElecFac &&
                                    $freinageElecDecla &&
                                    $freinageElecMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $freinageElec ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaBtFac) && $freinageElecFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageElecFac, $freinageElecDecla, $$scoreFreinElec)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $freinageElecFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageElecFac, $freinageElecDecla, $$scoreFreinElec)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td> 
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageElecFac, $freinageElecDecla, $$scoreFreinElec)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $freinageFac &&
                                    $freinageDecla &&
                                    $freinageMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $freinageHydro ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaBtFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $freinageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>  
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinageFac, $freinageDecla, $$scoreFreinHydro)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $freinFac &&
                                    $freinDecla &&
                                    $freinMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $freinagePneu ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $freinFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinFac, $freinDecla, $$scoreFreinPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $freinFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinFac, $freinDecla, $$scoreFreinPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $freinFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinFac, $freinDecla, $$scoreFreinPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $freinFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinFac, $freinDecla, $$scoreFreinPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $freinFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinFac, $freinDecla, $$scoreFreinPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $freinFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinFac, $freinDecla, $$scoreFreinPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($freinFac, $freinDecla, $$scoreFreinPneu)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $hydrauliqueFac &&
                                    $hydrauliqueDecla &&
                                    $hydrauliqueMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $hydraulique ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($mercedesTruckFac) && $hydrauliqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $hydrauliqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $hydrauliqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $hydrauliqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $hydrauliqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $hydrauliqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $hydrauliqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($hydrauliqueFac, $hydrauliqueDecla, $$scoreHydro)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $moteurDieselFac &&
                                    $moteurDieselDecla &&
                                    $moteurDieselMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $moteurDiesel ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaFac) && $moteurDieselFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>  
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurDieselFac, $moteurDieselDecla, $$scoreMoteurDiesel)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $moteurElecFac &&
                                    $moteurElecDecla &&
                                    $moteurElecMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $moteurElectrique ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaBtFac) && $moteurElecFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurElecFac, $moteurElecDecla, $$scoreMoteurElec)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $moteurElecFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurElecFac, $moteurElecDecla, $$scoreMoteurElec)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurElecFac, $moteurElecDecla, $$scoreMoteurElec)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $moteurEssenceFac &&
                                    $moteurEssenceDecla &&
                                    $moteurEssenceMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $moteurEssence ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $moteurEssenceFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurEssenceFac, $moteurEssenceDecla, $$scoreMoteurEssence)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $moteurThermiqueFac &&
                                    $moteurThermiqueDecla &&
                                    $moteurThermiqueMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $moteurThermique ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $moteurThermiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($moteurThermiqueFac, $moteurThermiqueDecla, $$scoreMoteurThermique)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $multiplexageFac &&
                                    $multiplexageDecla &&
                                    $multiplexageMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $multiplexage ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $multiplexageFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?> 
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($multiplexageFac, $multiplexageDecla, $$scoreMultiplexage)
                                    ?>%
                                    </td> 
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $pneuFac &&
                                    $pneuDecla &&
                                    $pneuMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $pneu ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $pneuFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pneuFac, $pneuDecla, $$scorePneu)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $pontFac &&
                                    $pontDecla &&
                                    $pontMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $pont ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $pontFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($pontFac, $pontDecla, $$scorePont)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $reducteurFac &&
                                    $reducteurDecla &&
                                    $reducteurMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $reducteur ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $reducteurFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($reducteurFac, $reducteurDecla, $$scoreRed)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $suspensionFac &&
                                    $suspensionDecla &&
                                    $suspensionMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $suspension ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($citroenFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $suspensionFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionFac, $suspensionDecla, $$scoreSuspension)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $suspensionLameFac &&
                                    $suspensionLameDecla &&
                                    $suspensionLameMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $suspensionLame ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($citroenFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $suspensionLameFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionLameFac, $suspensionLameDecla, $$scoreSuspensionLame)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $suspensionRessortFac &&
                                    $suspensionRessortDecla &&
                                    $suspensionRessortMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $suspensionRessort ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($citroenFac) && $suspensionRessortFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionRessortFac, $suspensionRessortDecla, $$scoreSuspensionRessort)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $suspensionRessortFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionRessortFac, $suspensionRessortDecla, $$scoreSuspensionRessort)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $suspensionRessortFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionRessortFac, $suspensionRessortDecla, $$scoreSuspensionRessort)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $suspensionRessortFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionRessortFac, $suspensionRessortDecla, $$scoreSuspensionRessort)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $suspensionRessortFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionRessortFac, $suspensionRessortDecla, $$scoreSuspensionRessort)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionRessortFac, $suspensionRessortDecla, $$scoreSuspensionRessort)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $suspensionPneumatiqueFac &&
                                    $suspensionPneumatiqueDecla &&
                                    $suspensionPneumatiqueMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $suspensionPneu ?>
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($mercedesTruckFac) && $suspensionPneumatiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionPneumatiqueFac, $suspensionPneumatiqueDecla, $$scoreSuspensionPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $suspensionPneumatiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionPneumatiqueFac, $suspensionPneumatiqueDecla, $$scoreSuspensionPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($citroenFac) && $suspensionPneumatiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionPneumatiqueFac, $suspensionPneumatiqueDecla, $$scoreSuspensionPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $suspensionPneumatiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionPneumatiqueFac, $suspensionPneumatiqueDecla, $$scoreSuspensionPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $suspensionPneumatiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionPneumatiqueFac, $suspensionPneumatiqueDecla, $$scoreSuspensionPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php if (
                                        isset($toyotaFac) && $suspensionPneumatiqueFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionPneumatiqueFac, $suspensionPneumatiqueDecla, $$scoreSuspensionPneu)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($suspensionPneumatiqueFac, $suspensionPneumatiqueDecla, $$scoreSuspensionPneu)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <?php if (
                                    $transversaleFac &&
                                    $transversaleDecla &&
                                    $transversaleMa
                                ) { ?>
                                <tr class="odd" style="">
                                    <td class="min-w-125px sorting text-white text-center table-light text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table" rowspan=`${i}`
                                        aria-label="Email: activate to sort column ascending"
                                        style="width: 155.266px; ">
                                        <?php echo $transversale ?>
                                    </td>
                                    <?php if (
                                        isset($kingLongFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($fusoFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($hinoFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesTruckFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($renaultTruckFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($sinotrukFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaBtFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaForfliftFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($jcbFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($lovolFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($citroenFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($mercedesFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($peugeotFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($suzukiFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <?php if (
                                        isset($toyotaFac) && $transversaleFac
                                    ) { ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                    <?php } else { ?>
                                    <td class="text-center">
                                        -
                                    </td>
                                    <?php } ?>
                                    <td class="text-center">
                                    <?php echo
                                    safeAvgPct($transversaleFac, $transversaleDecla, $$scoreTransversale)
                                    ?>%
                                    </td>
                                </tr>
                                <?php } ?>
                                <!--end::Menu-->
                                <tr>
                                    <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo $result ?></th>
                                    <?php if (
                                        isset($kingLongFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($kingLongScoreFac * 100) / $kingLongFac + ($kingLongScore * 100) / $kingLongDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($fusoFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($fusoScoreFac * 100) / $fusoFac + ($fusoScore * 100) / $fusoDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($hinoFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($hinoScoreFac * 100) / $hinoFac + ($hinoScore * 100) / $hinoDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($mercedesTruckFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($mercedesTruckScoreFac * 100) / $mercedesTruckFac + ($mercedesTruckScore * 100) / $mercedesTruckDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($renaultTruckFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($renaultTruckScoreFac * 100) / $renaultTruckFac + ($renaultTruckScore * 100) / $renaultTruckDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($sinoTrukFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($sinotrukFac * 100) / $sinotrukFac + ($sinotrukScore * 100) / $sinotrukDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($toyotaBtFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($toyotaBtScoreFac * 100) / $toyotaBtFac + ($toyotaBtScore * 100) / $toyotaBtDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($toyotaForfliftFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($toyotaForfliftScoreFac * 100) / $toyotaForfliftFac + ($toyotaForfliftScore * 100) / $toyotaForfliftDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($jcbFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($jcbScoreFac * 100) / $jcbFac + ($jcbScore * 100) / $jcbDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($lovolFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($lovolScoreFac * 100) / $lovolFac + ($lovolScore * 100) / $lovolDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($citroenFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($citroenScoreFac * 100) / $citroenFac + ($citroenScore * 100) / $citroenDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($mercedesFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($mercedesScoreFac * 100) / $mercedesFac + ($mercedesScore * 100) / $mercedesDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($peugeotFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($peugeotScoreFac * 100) / $peugeotFac + ($peugeotScore * 100) / $peugeotDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($suzukiFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($suzukiScoreFac * 100) / $suzukiFac + ($suzukiScore * 100) / $suzukiDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <?php if (
                                        isset($toyotaFac)
                                    ) { ?>
                                        <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                            tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                            aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                            <?php echo
                                            ceil((($toyotaScoreFac * 100) / $toyotaFac + ($toyotaScore * 100) / $toyotaDecla) / 2)
                                            ?>%
                                        </th>
                                    <?php } else { ?>
                                    <th class="min-w-125px sorting bg-primary text-black text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        -
                                    </th>
                                    <?php } ?> 
                                    <th class="min-w-125px sorting bg-primary text-white text-center table-light fw-bold text-uppercase gs-0"
                                        tabindex="0" colspan="1" aria-controls="kt_customers_table"
                                        aria-label="Email: activate to sort column ascending" style="width: 155.266px;">
                                        <?php echo
                                        safeAvgPct($resultFac, $resultTechMa, $resultTechMa->score ?? 0)
                                        ?>%
                                    </th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<?php include_once "partials/footer.php"; ?>
<?php
} ?>
