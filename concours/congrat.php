<?php
session_start();
require_once __DIR__ . '/core/bootstrap.php';

if (!isset($_SESSION["concours_profile"])) {
    header("Location: index.php");
    exit();
}

$firstName = $_SESSION["concours_firstName"] ?? '';
$lastName = $_SESSION["concours_lastName"] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
$initial = mb_substr($firstName, 0, 1) ?: 'T';
$lang = $_SESSION['concours_lang'] ?? ($_COOKIE['concours_lang'] ?? 'fr');

if ($lang === 'en') {
    $txtTitle = "Exam Completed";
    $txtCongrat = "Congratulations!";
    $txtMsg = "You have successfully submitted your answers for the Suzuki Technical Contest.";
    $txtBtn = "Log out";
} else {
    $txtTitle = "Épreuve Terminée";
    $txtCongrat = "Félicitations !";
    $txtMsg = "Vous avez soumis vos réponses avec succès pour le Concours Technique Suzuki.";
    $txtBtn = "Se déconnecter";
}

// Optionally, mark connection as finished
$connections = $databaseConcours->connections;
$userId = new MongoDB\BSON\ObjectId($_SESSION["concours_id"]);
$connections->updateOne(
    ["user" => $userId, "active" => true],
    ['$set' => ["status" => "Completed", "end" => date("d-m-Y H:i:s")]]
);

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $txtTitle; ?> · Concours Suzuki — CFAO Academy</title>
  <link rel="icon" type="image/png" href="../public/images/logo-cfao.png" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    body.theme-light { background-color: #f5f7fa; }
    .app-bg { display: none; }
    .topbar.exam-header {
        position: fixed; top: 0; left: 0; width: 100%;
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        z-index: 1000;
        padding: 0 2rem;
        height: 70px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .exam-main {
        margin-top: 100px;
        max-width: 700px;
        margin-left: auto; margin-right: auto;
        padding: 0 1rem;
    }
    .congrat-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e1e4e8;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        padding: 4rem 2rem;
        text-align: center;
    }
    .success-icon {
        width: 80px; height: 80px;
        background: #e8f5e9; color: #2e7d32;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 2rem auto;
    }
    .success-icon svg { width: 40px; height: 40px; }
  </style>
</head>
<body class="theme-light">
  <div class="page" style="padding:0;">
    <header class="topbar exam-header">
      <div class="brand">
        <div class="brand__logos" style="margin-bottom: 0;">
          <img class="brand__logo" src="assets/img/cfao-mobility.png" alt="CFAO Mobility" style="height: 30px;" />
          <img class="brand__logo" src="assets/img/suzuki.svg" alt="Suzuki" style="height: 24px;" />
        </div>
      </div>
      <div class="user-chip" style="background: transparent; padding: 0;">
        <div class="user-chip__avatar" title="<?php echo htmlspecialchars($fullName); ?>"><?php echo htmlspecialchars($initial); ?></div>
      </div>
    </header>
    
    <main class="exam-main">
        <div class="congrat-card">
            <div class="success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <h2 style="font-size: 2rem; color: #1a2332; margin-bottom: 1rem;"><?php echo $txtCongrat; ?></h2>
            <p style="font-size: 1.2rem; color: #495057; margin-bottom: 3rem;"><?php echo $txtMsg; ?></p>
            <a href="logout.php" class="btn btn--primary btn--lg"><?php echo $txtBtn; ?></a>
        </div>
    </main>
  </div>
</body>
</html>
