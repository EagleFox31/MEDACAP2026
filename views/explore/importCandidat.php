<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/**
 * Import candidats (stage) — minimal .xlsx (firstName,lastName)
 * - Rattache chaque candidat à un RH (obligatoire)
 * - Envoi d’un email récap au RH avec les accès
 * - Contexte : MEDACAP / CFAO
 */

session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";
include_once "../sendMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* ----------------------------------------------------------
 * Constantes métier
 * -------------------------------------------------------- */
const FIXED_SUBSIDIARY = "CFAO MOBILITY CAMEROON";
const FIXED_DEPARTMENT = "Equipment & Motors";
const FIXED_COUNTRY    = "Cameroun";
const CAMPAIGN_ROLE    = "Recrutement Technicien Campagne FNE-CFAO 2025";

/* ----------------------------------------------------------
 * Helpers
 * -------------------------------------------------------- */
function ascii_clean($s) {
    $o = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    return $o !== false ? $o : $s;
}
function only_alnum($s) {
    return preg_replace('/[^A-Za-z0-9]/', '', $s);
}
function build_username_base($first, $last) {
    $fn = strtolower(only_alnum(ascii_clean($first)));
    $ln = strtolower(only_alnum(ascii_clean($last)));
    if ($ln !== '') return $ln;
    if ($fn !== '') return $fn;
    return 'candidat';
}
function ensure_unique_username($usersCol, $base) {
    // on ajoute un suffixe numérique pour garantir l'unicité
    $candidate = $base;
    $i = 1;
    while ($usersCol->findOne(['username' => $candidate, 'active' => true])) {
        $candidate = $base . '-' . $i;
        $i++;
    }
    return $candidate;
}
function build_visible_password($lastName) {
    // Nom normalisé + 4 chiffres pseudo-aléatoires + '@' + 4 aléatoires
    $ln = ucfirst(strtolower(preg_replace('/\s+/', '', ascii_clean($lastName))));
    $d4 = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $rnd = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 4);
    return '0000';
}
function medacap_link_for_country($country) {
    return medacapAppBaseUrl($country);
}

/* ----------------------------------------------------------
 * DB
 * -------------------------------------------------------- */
$client = new Client(medacapMongoUri());
$db     = $client->academy;
$users  = $db->users;

/* ----------------------------------------------------------
 * Télécharger un modèle Excel ? (GET ?template=1)
 * -------------------------------------------------------- */
if (isset($_GET['template'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['firstName','lastName'], ['Awa','Diop'], ['Jean','Ndola']], NULL, 'A1');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_candidats.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

/* ----------------------------------------------------------
 * Traitement d'import
 * -------------------------------------------------------- */
$result = null; $errors = []; $createdRows = [];

if (isset($_POST["submit"])) {
    try {
        // RH ciblé (obligatoire)
        $rhId = $_POST['rh_id'] ?? '';
        if (!$rhId || !preg_match('/^[a-f0-9]{24}$/i', $rhId)) {
            throw new RuntimeException("Veuillez sélectionner un Ressource Humaine valide.");
        }
        $rh = $users->findOne(['_id' => new ObjectId($rhId), 'active' => true, 'profile' => 'Ressource Humaine']);
        if (!$rh) throw new RuntimeException("Le Ressource Humaine sélectionné est introuvable ou inactif.");

        if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Fichier .xlsx manquant ou invalide.");
        }

        $filePath = $_FILES['excel']['tmp_name'];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $data = $spreadsheet->getActiveSheet()->toArray();

        if (!$data || count($data) < 2) {
            throw new RuntimeException("Le fichier doit contenir des en-têtes et au moins 1 ligne de données.");
        }

        // Vérif en-têtes
        $header = array_map('trim', array_map('strtolower', $data[0]));
        $idx = array_flip($header);
        if (!isset($idx['firstname']) && !isset($idx['lastname'])) {
            throw new RuntimeException("Au moins une colonne firstName ou lastName requise.");
        }
        unset($data[0]); // remove header

        $created = 0; $skipped = 0;

        foreach ($data as $rowNum => $row) {
            $firstName = isset($idx['firstname']) ? trim((string)($row[$idx['firstname']] ?? '')) : '';
            $lastName  = isset($idx['lastname']) ? trim((string)($row[$idx['lastname']] ?? '')) : '';

            if ($firstName === '' && $lastName === '') {
                continue;
            }

            if ($lastName === '' && $firstName !== '') {
                $lastName = $firstName;
                $firstName = '';
            }

            // Unicité "douce" : même prénom+nom+profil+subsidiary => on ignore
            $match = [
                'lastName'   => strtoupper($lastName),
                'profile'    => 'Candidat',
                'subsidiary' => FIXED_SUBSIDIARY,
                'active'     => true
            ];
            if ($firstName !== '') {
                $match['firstName'] = ucfirst($firstName);
            }
            $exists = $users->findOne($match);

            if ($exists) {
                $skipped++; 
                $errors[] = "Ligne ".($rowNum+1).": " . trim($firstName . ' ' . $lastName) . " existe déjà — ignorée.";
                continue;
            }

            // Username / mot de passe
            $base      = build_username_base($firstName, $lastName);
//            $username  = ensure_unique_username($users, $base);
            $visible   = build_visible_password($lastName);
            $hashed    = sha1($visible);

            // Document "Candidat"
            $doc = [
                "users"       => [],
                "matricule"    => '999999',
                "username"    => $base,
                "firstName"   => ucfirst($firstName),
                "lastName"    => strtoupper($lastName),
                "email"       => null, // pas demandé
                "phone"       => null,
                "gender"      => null,
                "level"       => "",   // pas de niveau imposé pour stage
                "country"     => FIXED_COUNTRY,
                "profile"     => "Candidat",
                "birthdate"   => null,
                "recrutmentDate" => null,
                "certificate" => null,
                "subsidiary"  => FIXED_SUBSIDIARY,
                "agency"      => null,
                "department"  => FIXED_DEPARTMENT,
                // compat: role (string) + roles (array tag campagne)
                "role"        => CAMPAIGN_ROLE,
                "roles"       => [CAMPAIGN_ROLE],
                "password"    => $hashed,
                "visiblePassword" => $visible,
                // lien RH
                "hr"          => new ObjectId($rhId),
                "test"        => false,
                "active"      => true,
                "status"      => "New",
                "created"     => date("d-m-Y H:i:s"),
                "updated"     => date("d-m-Y H:i:s"),
            ];

            $ins = $users->insertOne($doc);
            $created++;

            $createdRows[] = [
                'firstName' => $doc['firstName'],
                'lastName'  => $doc['lastName'],
                'username'  => $doc['username'],
                'password'  => $doc['visiblePassword'],
            ];
        }

        // Envoi d'un email récap au RH
        if ($created > 0 && !empty($rh['email'])) {
            sendCandidateImportSummaryMail(
                (string)$rh['email'],
                (string)($rh['firstName'] ?? ''),
                'campagne de recrutement technicien 2025 (stage)',
                $created,
                $createdRows,
                FIXED_COUNTRY
            );
        }

        $result = [
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $result = ['created'=>0, 'skipped'=>0, 'errors'=>$errors];
    }
}

include_once "partials/header.php";
?>
<style>
input { background-color:#fff !important; border-style:solid; }
</style>

<title>Importer des candidats | CFAO Mobility Academy</title>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
  <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div class="container-xxl">
      <div class="container mt-5 w-50 text-center">
        <img src="../../public/images/logo.png" alt="logo" height="170" style="display:block;max-width:75%;height:auto;margin-left:25px;">
        <h1 class="my-3 text-center">Importer des candidats (stage)</h1>

        <?php if ($result): ?>
          <?php if (($result['created'] ?? 0) > 0): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <center><strong><?php
                echo $result['created']; ?> candidat(s) créé(s), <?php echo $result['skipped']; ?> ignoré(s).
              </strong></center>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>
          <?php if (!empty($result['errors'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert" style="text-align:left">
              <strong>Remarques :</strong>
              <ul style="margin:8px 0 0 16px;">
                <?php foreach ($result['errors'] as $err): ?>
                  <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
              </ul>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="mb-3">
          <a href="?template=1" class="btn btn-light-primary btn-sm">Télécharger le modèle (.xlsx)</a>
        </div>

        <form enctype="multipart/form-data" method="POST"><br>

          <!-- Sélection du RH -->
          <div class="fv-row mb-7 text-left">
            <label class="required form-label fw-bolder text-dark fs-6">Rattacher à un Ressource Humaine</label>
            <select name="rh_id" class="form-control form-control-solid" required>
              <option value="">— Sélectionner —</option>
              <?php
              // Liste RH actifs
              $cursor = $users->find(['active'=>true, 'profile'=>'Ressource Humaine'], ['sort'=>['lastName'=>1,'firstName'=>1]]);
              foreach ($cursor as $u) {
                  $id = (string)$u->_id;
                  $label = strtoupper((string)($u->lastName ?? '')) . ' ' . ucfirst((string)($u->firstName ?? ''));
                  $mail = (string)($u->email ?? '');
                  echo '<option value="'.htmlspecialchars($id).'">'.htmlspecialchars($label).($mail?' — '.$mail:'').'</option>';
              }
              ?>
            </select>
            <small class="form-text text-muted">Le récapitulatif des comptes créés sera envoyé à ce RH.</small>
          </div>

          <!-- Fichier Excel -->
          <div class="fv-row mb-7">
            <label class="required form-label fw-bolder text-dark fs-6">Importer via Excel (.xlsx)</label>
            <div class="input-group">
              <input type="file" class="form-control form-control-solid" name="excel" accept=".xlsx" required />
              <div class="input-group-append">
                <span class="input-group-text" style="height: 10px !important; padding: 15px;">.xlsx</span>
              </div>
            </div>
            <small class="form-text text-muted">Colonnes requises : <code>firstName,lastName</code></small>
          </div>

          <div class="modal-footer flex-center">
            <button type="submit" name="submit" class="btn btn-primary">
              <span class="indicator-label">Valider</span>
              <span class="indicator-progress">Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const closeButtons = document.querySelectorAll('.alert .close');
  closeButtons.forEach(function(button) {
    button.addEventListener('click', function() {
      const alert = this.closest('.alert');
      alert.remove();
    });
  });
});
</script>

<?php include_once "partials/footer.php"; ?>
