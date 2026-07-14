<?php
/**
 * Aperçu HTML du mail récap import candidats.
 * Ouvrir : http://localhost/MEDACAP/public/previews/import-candidats-mail.html
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../views/sendMail.php';

$rows = [
    ['lastName' => 'NGOUA', 'firstName' => 'Roland', 'username' => 'ngoua', 'password' => '0000'],
    ['lastName' => 'AMINGOUE', 'firstName' => 'Paulin', 'username' => 'amingoue', 'password' => '0000'],
    ['lastName' => 'MEKOO MEKOO', 'firstName' => 'Daniel', 'username' => 'mekoomekoo', 'password' => '0000'],
    ['lastName' => 'LAMBERT', 'firstName' => 'Eto', 'username' => 'lambert', 'password' => '0000'],
];

$link = 'http://10.68.0.7/medacap/';
$campaign = 'Recrutement Mécanicien Poids Lourds 2026';
$total = count($rows);
$tableHtml = buildCandidateImportTableHtml($rows);
$campaignEsc = htmlspecialchars($campaign, ENT_QUOTES, 'UTF-8');
$secondPara = 'Les ' . $total . ' candidats peuvent dès à présent se connecter et passer leurs épreuves. Voici les identifiants à transmettre à chacun :';

$message = '<p style="margin:0 0 12px 0;">Les accès sur la plateforme MEDACAP ont été créés pour les candidats de la campagne de <strong>'
    . $campaignEsc . '</strong>.</p>'
    . '<p style="margin:0 0 4px 0;">' . $secondPara . '</p>'
    . $tableHtml
    . buildCandidateImportDemoHtml(fetchDemoAccountsForImportMail());

$html = generateEmailTemplate([
    'userName'       => 'Paulin',
    'title'          => 'Comptes des candidats de la campagne',
    'message'        => $message,
    'ctaText'        => 'Accéder à la plateforme',
    'ctaLink'        => $link,
    'teamName'       => TEAM_NAME,
    'additionalInfo' => 'Communiquez à chaque candidat son identifiant, son mot de passe et le lien de connexion. Le compte démo sert uniquement à tester le parcours avant envoi aux candidats.',
]);

$outDir = __DIR__ . '/../public/previews';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$outFile = $outDir . '/import-candidats-mail.html';
file_put_contents($outFile, $html);

echo "Preview: {$outFile}\n";
echo "URL: http://localhost/MEDACAP/public/previews/import-candidats-mail.html\n";
