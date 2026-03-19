<?php
declare(strict_types=1);

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

/* =========================================================================
   CONFIG GLOBALE
   ========================================================================= */
const FROM_NAME          = 'CFAO Mobility Academy';
const FROM_EMAIL         = 'helpdeskcm@cfao.com';
const SUPER_ADMIN_EMAIL  = 'jakaa-ext@cfao.com';

const CC_STATIC = [
    'aybokally@cfao.com',
    'lgonin@cfao.com',
    SUPER_ADMIN_EMAIL, // Super Admin toujours en CC
];

const SIGNATURE_HTML = '<p style="margin-top:28px;font-size:16px;line-height:1.4">
  Cordialement&nbsp;|&nbsp;Best Regards&nbsp;|&nbsp;よろしくお願いしま。
</p>';

/* =========================================================================
   HEADERS
   ========================================================================= */
function buildHeaders(array $extraCc = [], ?string $replyTo = null): string
{
    $cc = array_values(array_unique(array_filter(array_merge(CC_STATIC, $extraCc))));
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . ($replyTo ?: FROM_EMAIL),
        empty($cc) ? '' : 'Cc: ' . implode(', ', $cc),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];
    return implode("\r\n", array_values(array_filter($headers)));
}

/* =========================================================================
   TEMPLATE HTML SIMPLE - EMAIL NORMAL
   ========================================================================= */
function generateEmailTemplate(array $emailData): string
{
    $companyName     = $emailData['companyName']     ?? 'MEDACAP';
    $companyTagline  = $emailData['companyTagline']  ?? 'CFAO Mobility Academy';
    $userName        = $emailData['userName']        ?? 'Cher utilisateur';
    $title           = $emailData['title']           ?? '';
    $message         = $emailData['message']         ?? '';
    $details         = (isset($emailData['details']) && is_array($emailData['details'])) ? $emailData['details'] : [];
    $ctaText         = $emailData['ctaText']         ?? '';
    $ctaLink         = $emailData['ctaLink']         ?? '#';
    $additionalInfo  = $emailData['additionalInfo']  ?? '';
    $supportEmail    = $emailData['supportEmail']    ?? SUPER_ADMIN_EMAIL;
    $currentYear     = date('Y');

    // Tableau des détails (simple)
    $detailsHtml = '';
    if (!empty($details)) {
        $rows = '';
        foreach ($details as $d) {
            $label = isset($d['label']) ? htmlspecialchars((string)$d['label'], ENT_QUOTES, 'UTF-8') : '';
            $value = isset($d['value']) ? htmlspecialchars((string)$d['value'], ENT_QUOTES, 'UTF-8') : '';
            $valueStyle = !empty($d['highlight']) ? 'font-weight:bold;' : '';
            
            $rows .= '<tr>
                <td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;font-weight:600;">'.$label.'</td>
                <td style="padding:8px;border:1px solid #ddd;'.$valueStyle.'">'.$value.'</td>
              </tr>';
        }
        $detailsHtml = '
          <table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:20px 0;border-collapse:collapse;">
            '.$rows.'
          </table>';
    }

    // Lien CTA simple
    $ctaHtml = '';
    if (!empty($ctaText) && !empty($ctaLink)) {
        $ctaHtml = '<p style="margin:20px 0;">
          <a href="'.htmlspecialchars($ctaLink, ENT_QUOTES, 'UTF-8').'" style="color:#0078d4;text-decoration:none;">'.htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8').'</a>
        </p>';
    }

    // Infos additionnelles
    $additionalHtml = !empty($additionalInfo) 
        ? '<p style="margin:15px 0;color:#666;font-size:13px;">'.$additionalInfo.'</p>' 
        : '';

    // Template email normal
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>'.htmlspecialchars($title ?: 'Email', ENT_QUOTES, 'UTF-8').'</title>
</head>
<body style="margin:0;padding:0;font-family:Calibri,Arial,sans-serif;font-size:14px;line-height:1.6;color:#000;">
    <div style="padding:20px;">
        <p style="margin:0 0 15px 0;">Bonjour '.htmlspecialchars($userName, ENT_QUOTES, 'UTF-8').',</p>
        
        <div style="margin:15px 0;">'.$message.'</div>
        
        '.$detailsHtml.'
        
        '.$ctaHtml.'
        
        '.$additionalHtml.'
        
        <p style="margin:20px 0 10px 0;">Cordialement,<br>L\'équipe '.htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8').'</p>
        
        <p style="margin:20px 0 5px 0;font-size:12px;color:#666;">
            Besoin d\'aide ? Email : '.htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8').'
        </p>
        <p style="margin:5px 0;font-size:11px;color:#999;">
            © '.$currentYear.' '.htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8').' - '.htmlspecialchars($companyTagline, ENT_QUOTES, 'UTF-8').'
        </p>
    </div>
</body>
</html>';
    
    return $html;
}

function sendTemplatedMedacapEmail(string $to, string $subject, array $emailData, array $cc = [], ?string $replyTo = null): bool
{
    // Défauts MEDACAP
    if (!isset($emailData['companyName']))    $emailData['companyName']    = 'MEDACAP';
    if (!isset($emailData['companyTagline'])) $emailData['companyTagline'] = 'CFAO Mobility Academy';
    if (!isset($emailData['supportEmail']))   $emailData['supportEmail']   = SUPER_ADMIN_EMAIL;

    $html = generateEmailTemplate($emailData);
    return mail($to, $subject, $html, buildHeaders($cc, $replyTo));
}

/* =========================================================================
   CONTENU POUR CHAQUE RÔLE
   ========================================================================= */
function buildVibrantOnboardingContent(string $role, string $fullName, string $portalLabel = 'Accéder à MEDACAP'): array
{
    $role = strtolower($role);
    switch ($role) {
        case 'admin':
        case 'administrateur de filiale':
            return [
                'subject' => 'MEDACAP – Accès Administrateur de filiale',
                'title'   => 'Administrateur de filiale',
                'message' => "Bienvenue dans MEDACAP.<br><br>
                    Votre rôle d'Administrateur de filiale vous permet de :<br>
                    • Piloter les tests et suivre leur exécution<br>
                    • Servir de relais entre votre filiale et le Super Admin<br>
                    • Accompagner vos dirigeants avec des données claires<br><br>
                    Votre compte est maintenant actif.",
                'ctaText' => 'Accéder au tableau de bord',
            ];
        
        case 'directeur':
        case 'directeur pièce et service':
        case 'directeur des opérations':
        case 'directeur général':
        case 'directeur groupe':
            return [
                'subject' => 'MEDACAP – Accès Direction',
                'title'   => 'Accès Direction',
                'message' => "Votre compte Directeur est actif sur MEDACAP.<br><br>
                    Vous disposez d'un tableau de bord pour :<br>
                    • Consulter les résultats des tests<br>
                    • Identifier les forces et axes de progression de vos équipes<br>
                    • Aligner compétences et objectifs business",
                'ctaText' => 'Accéder au tableau de bord',
            ];
        
        case 'ressource humaine':
        case 'rh':
            return [
                'subject' => 'MEDACAP – Accès Ressources Humaines',
                'title'   => 'Accès Ressources Humaines',
                'message' => "Votre compte Ressources Humaines est activé sur MEDACAP.<br><br>
                    Vous pouvez dès maintenant :<br>
                    • Monitorer les résultats des tests<br>
                    • Évaluer les niveaux de compétences<br>
                    • Alimenter vos plans de carrière et recrutements",
                'ctaText' => 'Accéder à la plateforme',
            ];
        
        case 'manager':
            return [
                'subject' => 'MEDACAP – Accès Manager',
                'title'   => 'Accès Manager',
                'message' => "Votre compte Manager est activé sur MEDACAP.<br><br>
                    Vous pouvez désormais :<br>
                    • Suivre vos équipes en temps réel<br>
                    • Consulter les résultats des tests<br>
                    • Accompagner le développement des compétences",
                'ctaText' => 'Accéder à mes équipes',
            ];
        
        case 'technicien':
            return [
                'subject' => 'MEDACAP – Accès Technicien',
                'title'   => 'Accès Technicien',
                'message' => "Votre compte technicien est activé.<br><br>
                    Vous pouvez dès maintenant :<br>
                    • Passer vos QCM<br>
                    • Valider vos compétences<br>
                    • Progresser dans votre parcours",
                'ctaText' => 'Démarrer mes tests',
            ];
        
        case 'candidat':
            return [
                'subject' => 'MEDACAP – Accès Candidat',
                'title'   => 'Accès Candidat',
                'message' => "Bienvenue dans le processus MEDACAP.<br><br>
                    Votre compte candidat est activé et vous donne accès aux tests de recrutement.<br><br>
                    Vous pouvez maintenant démontrer vos compétences.",
                'ctaText' => 'Accéder aux tests',
            ];
        
        case 'super admin':
            return [
                'subject' => 'MEDACAP – Accès Super Admin',
                'title'   => 'Accès Super Admin',
                'message' => "Votre compte Super Admin est opérationnel.<br><br>
                    Vous avez une vision complète de toutes les filiales et collaborateurs :<br>
                    • Supervision en temps réel<br>
                    • Gestion globale du dispositif<br>
                    • Accès à toutes les données",
                'ctaText' => 'Accéder à MEDACAP',
            ];
        
        default:
            return [
                'subject' => 'MEDACAP – Votre compte est activé',
                'title'   => 'Bienvenue',
                'message' => "Votre compte MEDACAP est prêt à l'usage.<br><br>
                    Connectez-vous pour commencer.",
                'ctaText' => $portalLabel,
            ];
    }
}

/* =========================================================================
   ENVOI ONBOARDING
   ========================================================================= */
function sendBrandedAccountEmail(array $params): bool
{
    $to          = $params['to'];
    $role        = $params['role'];
    $firstName   = $params['firstName'] ?? '';
    $lastName    = $params['lastName']  ?? '';
    $username    = $params['username']  ?? '';
    $visiblePwd  = $params['visiblePwd']?? '';
    $portalLink  = $params['portalLink']?? '#';
    $country     = $params['country']   ?? '';
    $subsidiary  = $params['subsidiary']?? '';
    $supportMail = $params['supportEmail'] ?? FROM_EMAIL;
    $extraCc     = $params['cc'] ?? [];

    $fullName = trim($firstName.' '.$lastName);
    $content  = buildVibrantOnboardingContent($role, $fullName);
    $subject  = $content['subject'];

    $details = [];
    if ($username)   $details[] = ['label'=>'Identifiant', 'value'=>$username,  'highlight'=>true];
    if ($visiblePwd) $details[] = ['label'=>'Mot de passe', 'value'=>$visiblePwd, 'highlight'=>true];
    if ($role)       $details[] = ['label'=>'Rôle',         'value'=>$role];
    if ($subsidiary) $details[] = ['label'=>'Filiale',      'value'=>$subsidiary];
    if ($country)    $details[] = ['label'=>'Pays',         'value'=>$country];
    $details[] = ['label'=>'Support', 'value'=>SUPER_ADMIN_EMAIL];

    $emailData = [
        'userName'       => $fullName ?: 'Utilisateur',
        'title'          => $content['title'],
        'message'        => $content['message'],
        'details'        => $details,
        'ctaText'        => $content['ctaText'],
        'ctaLink'        => $portalLink,
        'additionalInfo' => "Ces accès sont personnels et confidentiels. Merci de changer votre mot de passe lors de votre première connexion.",
        'supportEmail'   => $supportMail,
    ];

    return sendTemplatedMedacapEmail($to, $subject, $emailData, $extraCc);
}

/* =========================================================================
   HELPERS MONGO + SALUTATIONS
   ========================================================================= */
function getAdminsDocs($collab, Client $mongo): array
{
    if (is_object($collab)) {
        $collab = json_decode(json_encode($collab), true);
    }
    $users  = $mongo->academy->users;

    $admins = iterator_to_array($users->find([
        'profile'    => 'Admin',
        'subsidiary' => $collab['subsidiary'] ?? '',
    ]));

    if (count($admins) > 1 && !empty($collab['department'])) {
        $admins = array_filter($admins, function ($u) use ($collab) {
            return strcasecmp($u['department'] ?? '', $collab['department']) === 0;
        });
    }
    if (count($admins) > 1 && !empty($collab['agency'])) {
        $admins = array_filter($admins, function ($u) use ($collab) {
            return strcasecmp($u['agency'] ?? '', $collab['agency']) === 0;
        });
    }
    return array_values($admins);
}

function getRhAndDpsEmails(string $subsidiary, Client $mongo): array
{
    $users  = $mongo->academy->users;
    $cursor = $users->find([
        'profile'    => ['$in' => ['Ressource Humaine', 'Directeur Pièce et Service']],
        'subsidiary' => $subsidiary,
    ]);
    return array_values(array_unique(array_filter(array_column(iterator_to_array($cursor), 'email'))));
}

function buildAdminSalutation(array $adminDocs): string
{
    $nb = count($adminDocs);
    if ($nb === 0) return 'Bonjour,';

    $isEnglish = false;
    if (!empty($adminDocs[0]['language']) && strtolower((string)$adminDocs[0]['language']) === 'en') {
        $isEnglish = true;
    } elseif (!empty($adminDocs[0]['subsidiary']) && $adminDocs[0]['subsidiary'] === 'CFAO MOTORS RWANDA') {
        $isEnglish = true;
    }

    if ($nb > 1) return $isEnglish ? 'Hello everyone,' : 'Bonjour à tous,';

    $admin  = $adminDocs[0];
    $gender = strtolower((string)($admin['gender'] ?? ''));

    if ($isEnglish) {
        $prefix = ($gender === 'feminin') ? 'Ms.' : (($gender === 'masculin') ? 'Mr.' : 'Mr.');
        return 'Hello ' . $prefix . ' ' . htmlspecialchars((string)$admin['lastName'], ENT_QUOTES, 'UTF-8') . ',';
    }
    $prefix = ($gender === 'feminin') ? 'Mme' : (($gender === 'masculin') ? 'M.' : 'M.');
    return 'Bonjour ' . $prefix . ' ' . htmlspecialchars((string)$admin['lastName'], ENT_QUOTES, 'UTF-8') . ',';
}

/* =========================================================================
   ENVOIS SIMPLES
   ========================================================================= */
function sendMailRegisterUser(string $to, string $subject, string $message, array $cc = []): bool
{
    $emailData = [
        'userName' => 'Équipe MEDACAP',
        'title' => 'Enregistrement confirmé',
        'message' => $message,
        'ctaText' => 'Voir le profil',
        'ctaLink' => medacapLinkForCountry(''),
    ];

    $html = generateEmailTemplate($emailData);
    return mail($to, $subject, $html, buildHeaders($cc));
}

function sendMailRegisterTraining(string $to, string $subject, string $message, string $trainerEmail): bool
{
    $emailData = [
        'userName' => 'Équipe Formation',
        'title' => 'Formation enregistrée',
        'message' => $message,
        'ctaText' => 'Voir les détails',
        'ctaLink' => medacapLinkForCountry(''),
    ];

    $html = generateEmailTemplate($emailData);
    return mail($to, $subject, $html, buildHeaders([$trainerEmail, 'ceyinga-ext@cfao.com']));
}

function sendMailSelectDone(string $subject, string $message): bool
{
    $emailData = [
        'userName' => 'Super Admin',
        'title' => 'Sélection terminée',
        'message' => $message,
        'ctaText' => 'Voir les résultats',
        'ctaLink' => medacapLinkForCountry(''),
    ];

    $html = generateEmailTemplate($emailData);
    return mail(SUPER_ADMIN_EMAIL, $subject, $html, buildHeaders());
}

function sendMailQcmFinalized(
    array  $adminDocs,
    string $subject,
    string $bodyWithoutSignature,
    string $managerEmail,
    array  $rhEmails = [],
    array  $dpsEmails = []
): bool {
    if (empty($adminDocs)) return false;

    $emailData = [
        'userName' => $adminDocs[0]['firstName'] ?? 'Admin',
        'title' => 'Évaluation terminée',
        'message' => $bodyWithoutSignature,
        'ctaText' => 'Voir les résultats',
        'ctaLink' => medacapLinkForCountry($adminDocs[0]['country'] ?? ''),
    ];

    $html = generateEmailTemplate($emailData);
    $to = implode(', ', array_filter(array_column($adminDocs, 'email')));
    $cc = array_values(array_unique(array_filter(array_merge([$managerEmail], $rhEmails, $dpsEmails))));
    
    return mail($to, $subject, $html, buildHeaders($cc));
}

/* =========================================================================
   ENVOIS CANDIDATS
   ========================================================================= */
function sendCandidateCreationMail(array $adminDocs, array $candidate, string $visiblePassword, array $managerEmails = []): bool
{
    if (empty($adminDocs)) return false;

    $subject = 'MEDACAP — Nouveau candidat inscrit';
    
    $emailData = [
        'userName' => $adminDocs[0]['firstName'] ?? 'Admin',
        'title' => 'Nouveau candidat',
        'message' => "Un nouveau candidat vient de rejoindre le processus MEDACAP.",
        'details' => [
            ['label' => 'Candidat', 'value' => $candidate['firstName'] . ' ' . $candidate['lastName'], 'highlight' => true],
            ['label' => 'Email', 'value' => $candidate['email']],
            ['label' => 'Identifiant', 'value' => $candidate['username']],
            ['label' => 'Mot de passe', 'value' => $visiblePassword, 'highlight' => true]
        ],
        'ctaText' => 'Suivre ce candidat',
        'ctaLink' => medacapLinkForCountry($adminDocs[0]['country'] ?? ''),
    ];

    $html = generateEmailTemplate($emailData);
    $to = implode(', ', array_filter(array_column($adminDocs, 'email')));
    return mail($to, $subject, $html, buildHeaders($managerEmails));
}

function sendCandidateFinishedMail(array $adminDocs, array $candidate, array $managerEmails = []): bool
{
    if (empty($adminDocs)) return false;

    $subject = 'MEDACAP — Tests candidat terminés';
    
    $emailData = [
        'userName' => $adminDocs[0]['firstName'] ?? 'Admin',
        'title' => 'Tests terminés',
        'message' => "Un candidat a terminé ses tests d'évaluation.",
        'details' => [
            ['label' => 'Candidat', 'value' => $candidate['firstName'] . ' ' . $candidate['lastName'], 'highlight' => true],
            ['label' => 'Email', 'value' => $candidate['email']],
            ['label' => 'Statut', 'value' => 'Tests terminés', 'highlight' => true]
        ],
        'ctaText' => 'Voir les résultats',
        'ctaLink' => medacapLinkForCountry($adminDocs[0]['country'] ?? ''),
    ];

    $html = generateEmailTemplate($emailData);
    $to = implode(', ', array_filter(array_column($adminDocs, 'email')));
    return mail($to, $subject, $html, buildHeaders($managerEmails));
}

/* =========================================================================
   RAPPELS DE TESTS
   ========================================================================= */
function sendTestScheduledMail(array $adminDocs, array $technicianDocs, array $testEvent): bool
{
    if (empty($adminDocs)) return false;

    $managerEmails = [];
    foreach ($technicianDocs as $t) {
        if (!empty($t['manager_email'])) $managerEmails[] = (string)$t['manager_email'];
    }
    $managerEmails = array_values(array_unique(array_filter($managerEmails)));

    $isEnglish = !empty($adminDocs[0]['language']) && strtolower((string)$adminDocs[0]['language']) === 'en';

    $subject = $isEnglish 
        ? 'MEDACAP — Skills Assessment Scheduled: ' . $testEvent['name']
        : 'MEDACAP — Évaluation programmée : ' . $testEvent['name'];

    $message = $isEnglish
        ? "A skills assessment has been scheduled."
        : "Une évaluation des compétences a été programmée.";

    $details = [
        ['label' => $isEnglish ? 'Test Name' : 'Nom du test', 'value' => $testEvent['name'], 'highlight' => true],
        ['label' => $isEnglish ? 'Description' : 'Description', 'value' => $testEvent['description'] ?? ''],
        ['label' => $isEnglish ? 'Location' : 'Lieu', 'value' => $testEvent['location'] ?? ''],
        ['label' => $isEnglish ? 'Start Date' : 'Date de début', 'value' => $testEvent['startDate'] . ' ' . $testEvent['startTime']],
        ['label' => $isEnglish ? 'End Date' : 'Date de fin', 'value' => $testEvent['endDate'] . ' ' . $testEvent['endTime']],
        ['label' => $isEnglish ? 'Technicians' : 'Techniciens', 'value' => count($technicianDocs) . ' participants']
    ];

    $emailData = [
        'userName' => $adminDocs[0]['firstName'] ?? 'Admin',
        'title' => $isEnglish ? 'New Test Scheduled' : 'Nouveau test planifié',
        'message' => $message,
        'details' => $details,
        'ctaText' => $isEnglish ? 'View Details' : 'Voir les détails',
        'ctaLink' => medacapLinkForCountry($adminDocs[0]['country'] ?? ''),
    ];

    $html = generateEmailTemplate($emailData);
    $to = implode(', ', array_filter(array_column($adminDocs, 'email')));
    return mail($to, $subject, $html, buildHeaders($managerEmails));
}

function sendTestDayBeforeMail(array $adminDocs, array $technicianDocs, array $testEvent): bool
{
    if (empty($adminDocs)) return false;

    $managerEmails = [];
    foreach ($technicianDocs as $t) {
        if (!empty($t['manager_email'])) $managerEmails[] = (string)$t['manager_email'];
    }
    $managerEmails = array_values(array_unique(array_filter($managerEmails)));

    $isEnglish = (!empty($adminDocs[0]['subsidiary']) && $adminDocs[0]['subsidiary'] === 'CFAO MOTORS RWANDA');

    $subject = $isEnglish 
        ? 'MEDACAP — Reminder: Test Tomorrow'
        : 'MEDACAP — Rappel : Test demain';

    $message = $isEnglish
        ? "Reminder: A skills assessment is scheduled for <strong>tomorrow</strong>."
        : "Rappel : Une évaluation est prévue <strong>demain</strong>.";

    $details = [
        ['label' => $isEnglish ? 'Test Name' : 'Nom du test', 'value' => $testEvent['name'], 'highlight' => true],
        ['label' => $isEnglish ? 'Location' : 'Lieu', 'value' => $testEvent['location'] ?? ''],
        ['label' => $isEnglish ? 'Date' : 'Date', 'value' => $testEvent['startDate']],
        ['label' => $isEnglish ? 'Time' : 'Heure', 'value' => $testEvent['startTime'], 'highlight' => true],
    ];

    $emailData = [
        'userName' => $adminDocs[0]['firstName'] ?? 'Admin',
        'title' => $isEnglish ? 'Test Tomorrow' : 'Test demain',
        'message' => $message,
        'details' => $details,
        'ctaText' => $isEnglish ? 'View Details' : 'Voir les détails',
        'ctaLink' => medacapLinkForCountry($adminDocs[0]['country'] ?? ''),
    ];

    $html = generateEmailTemplate($emailData);
    $to = implode(', ', array_filter(array_column($adminDocs, 'email')));
    return mail($to, $subject, $html, buildHeaders($managerEmails));
}

function sendTestDayOfMail(array $adminDocs, array $technicianDocs, array $testEvent): bool
{
    if (empty($adminDocs)) return false;

    $managerEmails = [];
    foreach ($technicianDocs as $t) {
        if (!empty($t['manager_email'])) $managerEmails[] = (string)$t['manager_email'];
    }
    $managerEmails = array_values(array_unique(array_filter($managerEmails)));

    $isEnglish = !empty($adminDocs[0]['language']) && strtolower((string)$adminDocs[0]['language']) === 'en';

    if ($isEnglish) {
        $subject = 'MEDACAP — TODAY: Skills Assessment in Progress!';
        $message = "TODAY is the day! Your technicians are ready to demonstrate their expertise and elevate their skills.<br><br>
            Status: Test execution in progress<br>
            Mission: Ensure flawless completion<br>
            Result: Valuable competency insights<br><br>
            This is where potential becomes performance. Every test completed is a step toward excellence.";
        $ctaText = 'Monitor Progress';
        $additionalInfo = "Action required: Ensure the test is properly executed and completed today for optimal talent development.";
    } else {
        $subject = 'MEDACAP — AUJOURD\'HUI : Évaluation en cours !';
        $message = "AUJOURD'HUI, c'est le grand jour ! Vos techniciens sont prêts à démontrer leur expertise et élever leurs compétences.<br><br>
            Statut : Exécution du test en cours<br>
            Mission : Assurer une réalisation parfaite<br>
            Résultat : Insights précieux sur les compétences<br><br>
            C'est là que le potentiel devient performance. Chaque test réalisé est un pas vers l'excellence.";
        $ctaText = 'Suivre le progrès';
        $additionalInfo = "Action requise : Assurez-vous que le test soit correctement exécuté et terminé aujourd'hui pour un développement optimal des talents.";
    }

    $details = [
        ['label' => $isEnglish ? 'Test Name' : 'Nom du test', 'value' => $testEvent['name'], 'highlight' => true],
        ['label' => $isEnglish ? 'Location' : 'Lieu', 'value' => $testEvent['location'] ?? ($isEnglish ? 'TBD' : 'À définir')],
        ['label' => $isEnglish ? 'Time' : 'Heure', 'value' => $testEvent['startTime'], 'highlight' => true],
        ['label' => $isEnglish ? 'Participants' : 'Participants', 'value' => count($technicianDocs) . ' techniciens'],
        ['label' => $isEnglish ? 'Status' : 'Statut', 'value' => $isEnglish ? 'To be completed today' : 'À réaliser aujourd\'hui', 'highlight' => true]
    ];

    $emailData = [
        'userName' => $adminDocs[0]['firstName'] ?? 'Admin',
        'title' => $isEnglish ? 'Test Day: Action Required' : 'Jour J : Action requise',
        'message' => $message,
        'details' => $details,
        'ctaText' => $ctaText,
        'ctaLink' => medacapLinkForCountry($adminDocs[0]['country'] ?? ''),
        'additionalInfo' => $additionalInfo,
    ];

    $html = generateEmailTemplate($emailData);
    $to = implode(', ', array_filter(array_column($adminDocs, 'email')));
    return mail($to, $subject, $html, buildHeaders($managerEmails));
}

function sendTestDelayedMail(array $adminDocs, array $technicianDocs, array $testEvent): bool
{
    if (empty($adminDocs)) return false;

    $managerEmails = [];
    foreach ($technicianDocs as $t) {
        if (!empty($t['manager_email'])) $managerEmails[] = (string)$t['manager_email'];
    }
    $managerEmails = array_values(array_unique(array_filter($managerEmails)));

    $isEnglish = !empty($adminDocs[0]['language']) && strtolower((string)$adminDocs[0]['language']) === 'en';

    if ($isEnglish) {
        $subject = 'MEDACAP — URGENT: Overdue Skills Assessment';
        $message = "We need your immediate attention! A critical skills assessment scheduled for completion yesterday is still pending.<br><br>
            Status: Assessment overdue<br>
            Priority: Complete as soon as possible<br>
            Impact: Delays affect development tracking<br><br>
            Quick action is needed to maintain the momentum of your team's skill development journey.";
        $ctaText = 'Complete Now';
        $additionalInfo = "Urgent action required: Please verify the test status and ensure completion today to maintain accurate competency tracking.";
    } else {
        $subject = 'MEDACAP — URGENT : Évaluation en retard';
        $message = "Votre attention immédiate est requise ! Une évaluation critique de compétences prévue pour hier n'est toujours pas finalisée.<br><br>
            Statut : Évaluation en retard<br>
            Priorité : Finaliser dès que possible<br>
            Impact : Les retards affectent le suivi des développements<br><br>
            Une action rapide est nécessaire pour maintenir l'élan du parcours de développement des compétences de votre équipe.";
        $ctaText = 'Finaliser maintenant';
        $additionalInfo = "Action urgente requise : Merci de vérifier le statut du test et d'assurer sa finalisation aujourd'hui pour maintenir un suivi précis des compétences.";
    }

    $details = [
        ['label' => $isEnglish ? 'Test Name' : 'Nom du test', 'value' => $testEvent['name'], 'highlight' => true],
        ['label' => $isEnglish ? 'Scheduled Date' : 'Date prévue', 'value' => $testEvent['startDate']],
        ['label' => $isEnglish ? 'Scheduled Time' : 'Heure prévue', 'value' => $testEvent['startTime']],
        ['label' => $isEnglish ? 'Location' : 'Lieu', 'value' => $testEvent['location'] ?? ($isEnglish ? 'TBD' : 'À définir')],
        ['label' => $isEnglish ? 'Participants' : 'Participants', 'value' => count($technicianDocs) . ' techniciens'],
        ['label' => $isEnglish ? 'Status' : 'Statut', 'value' => $isEnglish ? 'OVERDUE - Action required' : 'EN RETARD - Action requise', 'highlight' => true]
    ];

    $emailData = [
        'userName' => $adminDocs[0]['firstName'] ?? 'Admin',
        'title' => $isEnglish ? 'Overdue Test Alert' : 'Alerte test en retard',
        'message' => $message,
        'details' => $details,
        'ctaText' => $ctaText,
        'ctaLink' => medacapLinkForCountry($adminDocs[0]['country'] ?? ''),
        'additionalInfo' => $additionalInfo,
    ];

    $html = generateEmailTemplate($emailData);
    $to = implode(', ', array_filter(array_column($adminDocs, 'email')));
    return mail($to, $subject, $html, buildHeaders($managerEmails));
}

/* =========================================================================
   OUTILS
   ========================================================================= */
function getTechnicianDocs(array $technicianIds, Client $mongo): array
{
    $users = $mongo->academy->users;
    $out   = [];

    foreach ($technicianIds as $id) {
        try {
            $doc = $users->findOne([
                '_id'    => new ObjectId((string)$id),
                'active' => true,
            ]);
            if ($doc) $out[] = $doc;
        } catch (\Throwable $e) {
            // id invalide → ignore
        }
    }
    return $out;
}

/* =========================================================================
   (Optionnel) Helper lien plateforme selon pays
   ========================================================================= */
function medacapLinkForCountry(?string $country): string
{
    return ($country === 'Cameroun' || $country === 'Cameroon')
        ? 'http://10.68.0.7/medacap/'
        : 'http://129.0.64.34/medacap';
}