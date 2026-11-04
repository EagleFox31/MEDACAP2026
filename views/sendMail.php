<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/medacap_config.php';
use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

/* =========================================================================
   CONFIG GLOBALE
   ========================================================================= */
const FROM_NAME          = 'CFAO Mobility Academy';
const FROM_EMAIL         = 'helpdeskcm@cfao.com';
const SUPER_ADMIN_EMAIL  = 'jakaa-ext@cfao.com';
const TEAM_NAME          = 'CFAO Academy';

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
function buildHeaders(array $extraCc = [], ?string $replyTo = null, bool $includeStaticCc = true): string
{
    $cc = $includeStaticCc
        ? array_values(array_unique(array_filter(array_merge(CC_STATIC, $extraCc))))
        : array_values(array_unique(array_filter($extraCc)));
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
    $teamName        = $emailData['teamName']        ?? TEAM_NAME;
    $userName        = $emailData['userName']        ?? 'Cher utilisateur';
    $title           = $emailData['title']           ?? '';
    $message         = $emailData['message']         ?? '';
    $details         = (isset($emailData['details']) && is_array($emailData['details'])) ? $emailData['details'] : [];
    $ctaText         = $emailData['ctaText']         ?? '';
    $ctaLink         = $emailData['ctaLink']         ?? '#';
    $ctaText2        = $emailData['ctaText2']        ?? '';
    $ctaLink2        = $emailData['ctaLink2']        ?? '#';
    $additionalInfo  = $emailData['additionalInfo']  ?? '';
    $supportEmail    = $emailData['supportEmail']    ?? SUPER_ADMIN_EMAIL;
    $width           = (int) ($emailData['width']    ?? 600); // largeur max de la carte (px)
    $currentYear     = date('Y');
    $isEn            = isset($emailData['lang']) && $emailData['lang'] === 'en';
    $hello           = $isEn ? 'Hello' : 'Bonjour';
    $regards         = $isEn ? 'Best regards,' : 'Cordialement,';
    $teamLabel       = $isEn ? 'The ' . $teamName . ' team' : 'L\'équipe ' . $teamName;
    $helpLabel       = $isEn ? 'Need help? Support:' : 'Besoin d\'aide ? Support :';

    // Tableau des détails (premium)
    $detailsHtml = '';
    if (!empty($details)) {
        $rows = '';
        foreach ($details as $d) {
            $label = isset($d['label']) ? htmlspecialchars((string)$d['label'], ENT_QUOTES, 'UTF-8') : '';
            $value = isset($d['value']) ? htmlspecialchars((string)$d['value'], ENT_QUOTES, 'UTF-8') : '';
            $valueStyle = !empty($d['highlight']) ? 'font-weight:700;color:#111827;' : 'color:#374151;';
            
            $rows .= '<tr>
                <td style="padding:14px 16px;border:1px solid #E5E7EB;background-color:#F9FAFB;font-weight:600;color:#4B5563;font-size:13px;width:30%;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'.$label.'</td>
                <td style="padding:14px 16px;border:1px solid #E5E7EB;font-size:14px;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;'.$valueStyle.'">'.$value.'</td>
              </tr>';
        }
        $detailsHtml = '
          <table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:24px 0;border-collapse:collapse;box-shadow:0 1px 2px rgba(0,0,0,0.02);border-radius:8px;overflow:hidden;">
            '.$rows.'
          </table>';
    }

    // Lien CTA (Bulletproof Outlook-friendly auto-expanding button)
    $ctaHtml = '';
    if (!empty($ctaText) && !empty($ctaLink)) {
        $btn1 = '<td align="center" valign="middle" style="background-color:#D70006;border-radius:8px;padding:14px 36px;box-shadow:0 4px 6px -1px rgba(215,0,6,0.15);">
                        <a href="'.htmlspecialchars($ctaLink, ENT_QUOTES, 'UTF-8').'" style="color:#ffffff;text-decoration:none;font-weight:700;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:15px;display:block;line-height:1.4;letter-spacing:0.3px;">
                            '.htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8').'
                        </a>
                    </td>';
        $btn2 = '';
        if (!empty($ctaText2)) {
            $btn2 = '<td width="16"></td>
                    <td align="center" valign="middle" style="background-color:#0b3c6e;border-radius:8px;padding:14px 36px;box-shadow:0 4px 6px -1px rgba(11,60,110,0.15);">
                        <a href="'.htmlspecialchars($ctaLink2, ENT_QUOTES, 'UTF-8').'" style="color:#ffffff;text-decoration:none;font-weight:700;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:15px;display:block;line-height:1.4;letter-spacing:0.3px;">
                            '.htmlspecialchars($ctaText2, ENT_QUOTES, 'UTF-8').'
                        </a>
                    </td>';
        }
        $ctaHtml = '
        <div style="margin:32px 0;text-align:center;">
            <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;border-collapse:separate;">
                <tr>'.$btn1.$btn2.'</tr>
            </table>
        </div>';
    }

    // Infos additionnelles
    $additionalHtml = !empty($additionalInfo) 
        ? '<p style="margin:24px 0 0 0;color:#6B7280;font-size:13px;line-height:1.6;font-style:italic;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'.$additionalInfo.'</p>' 
        : '';

    // Template email premium
    $html = '<!DOCTYPE html>
<html lang="'.($isEn ? 'en' : 'fr').'">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>'.htmlspecialchars($title ?: 'Email', ENT_QUOTES, 'UTF-8').'</title>
</head>
<body style="margin:0;padding:0;background-color:#F4F6F8;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#1F2937;-webkit-font-smoothing:antialiased;">
    <table cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#F4F6F8;padding:40px 20px;">
        <tr>
            <td align="center" valign="top">
                <!--[if mso]><table cellpadding="0" cellspacing="0" border="0" width="'.$width.'" align="center"><tr><td><![endif]-->
                <table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:'.$width.'px;background-color:#FFFFFF;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05);text-align:left;">
                    <!-- Top accent bar -->
                    <tr>
                        <td style="height:6px;background-color:#D70006;line-height:6px;font-size:6px;">&nbsp;</td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding:40px 32px;">
                            <!-- Header Logo / Title -->
                            <table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-bottom:28px;">
                                <tr>
                                    <td>
                                        <div style="font-size:12px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'.htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8').'</div>
                                        <div style="font-size:20px;font-weight:800;color:#111827;letter-spacing:-0.5px;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Greeting -->
                            <p style="margin:0 0 16px 0;font-size:15px;font-weight:600;color:#111827;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'.$hello.' '.htmlspecialchars($userName, ENT_QUOTES, 'UTF-8').',</p>
                            
                            <!-- Message -->
                            <div style="margin:16px 0;font-size:15px;color:#374151;line-height:1.7;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'.$message.'</div>
                            
                            <!-- Details Table -->
                            '.$detailsHtml.'
                            
                            <!-- Bulletproof Button -->
                            '.$ctaHtml.'
                            
                            <!-- Additional Info -->
                            '.$additionalHtml.'
                            
                            <!-- Closing Signature -->
                            <p style="margin:32px 0 0 0;padding-top:20px;border-top:1px solid #E5E7EB;font-size:14px;color:#4B5563;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
                                '.$regards.'<br>
                                <strong style="color:#111827;">'.htmlspecialchars($teamLabel, ENT_QUOTES, 'UTF-8').'</strong>
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 32px;background-color:#F9FAFB;border-top:1px solid #E5E7EB;text-align:center;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
                            <p style="margin:0 0 8px 0;font-size:12px;font-weight:500;color:#6B7280;">
                                '.$helpLabel.' <a href="mailto:'.htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8').'" style="color:#D70006;text-decoration:none;font-weight:600;">'.htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8').'</a>
                            </p>
                            <p style="margin:0;font-size:11px;color:#9CA3AF;line-height:1.4;">
                                © '.$currentYear.' '.htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8').' - '.htmlspecialchars($companyTagline, ENT_QUOTES, 'UTF-8').'
                            </p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>';

    return $html;
}

function sendTemplatedMedacapEmail(string $to, string $subject, array $emailData, array $cc = [], ?string $replyTo = null, bool $includeStaticCc = true): bool
{
    // Défauts MEDACAP
    if (!isset($emailData['companyName']))    $emailData['companyName']    = 'MEDACAP';
    if (!isset($emailData['companyTagline'])) $emailData['companyTagline'] = 'CFAO Mobility Academy';
    if (!isset($emailData['teamName']))       $emailData['teamName']       = TEAM_NAME;
    if (!isset($emailData['supportEmail']))   $emailData['supportEmail']   = SUPER_ADMIN_EMAIL;

    $html = generateEmailTemplate($emailData);
    return mail($to, $subject, $html, buildHeaders($cc, $replyTo, $includeStaticCc));
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

    // Mot de passe en clair : seulement les copies demandées par l'appelant
    return sendTemplatedMedacapEmail($to, $subject, $emailData, $extraCc, null, $visiblePwd === '');
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
    require_once __DIR__ . '/../core/subsidiary_aliases.php';
    $cursor = $users->find([
        'profile'    => ['$in' => ['Ressource Humaine', 'Directeur Pièce et Service']],
        'subsidiary' => medacapResolveSubsidiaryMongoFilter($subsidiary),
    ]);
    return array_values(array_unique(array_filter(array_column(iterator_to_array($cursor), 'email'))));
}

/**
 * Nom d'adresse pour le template (qui préfixe déjà "Bonjour ").
 * Homme → "M. NOM", femme → "Mme NOM". Pas d'échappement HTML (fait par le template).
 */
function buildAdminGreetingName(array $adminDocs): string
{
    $nb = count($adminDocs);
    if ($nb === 0) {
        return 'Admin';
    }

    $isEnglish = false;
    if (!empty($adminDocs[0]['language']) && strtolower((string) $adminDocs[0]['language']) === 'en') {
        $isEnglish = true;
    } elseif (!empty($adminDocs[0]['subsidiary']) && $adminDocs[0]['subsidiary'] === 'CFAO MOTORS RWANDA') {
        $isEnglish = true;
    }

    if ($nb > 1) {
        return $isEnglish ? 'everyone' : 'à tous';
    }

    $admin = $adminDocs[0];
    $gender = strtolower((string) ($admin['gender'] ?? ''));
    $lastName = trim((string) ($admin['lastName'] ?? ''));
    if ($lastName === '') {
        return trim((string) ($admin['firstName'] ?? 'Admin'));
    }

    if ($isEnglish) {
        $prefix = ($gender === 'feminin') ? 'Ms.' : 'Mr.';
        return $prefix . ' ' . $lastName;
    }
    $prefix = ($gender === 'feminin') ? 'Mme' : 'M.';
    return $prefix . ' ' . $lastName;
}

function buildAdminSalutation(array $adminDocs): string
{
    $nb = count($adminDocs);
    if ($nb === 0) {
        return 'Bonjour,';
    }

    $isEnglish = false;
    if (!empty($adminDocs[0]['language']) && strtolower((string) $adminDocs[0]['language']) === 'en') {
        $isEnglish = true;
    } elseif (!empty($adminDocs[0]['subsidiary']) && $adminDocs[0]['subsidiary'] === 'CFAO MOTORS RWANDA') {
        $isEnglish = true;
    }

    $name = buildAdminGreetingName($adminDocs);
    if ($isEnglish) {
        return 'Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',';
    }
    return 'Bonjour ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',';
}

/* =========================================================================
   ENVOIS SIMPLES
   ========================================================================= */
function sendMailRegisterUser(string $to, string $subject, string $message, array $cc = [], string $recipientName = ''): bool
{
    $emailData = [
        'userName' => $recipientName !== '' ? $recipientName : 'Cher utilisateur',
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
        'userName' => buildAdminGreetingName($adminDocs),
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
function buildCandidateImportTableHtml(array $rows): string
{
    if (empty($rows)) {
        return '';
    }

    $tbody = '';
    $i = 0;
    foreach ($rows as $r) {
        $bg = ($i % 2 === 0) ? '#FFFFFF' : '#F9FAFB';
        $num = $i + 1;
        $ln  = htmlspecialchars((string)($r['lastName'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fn  = htmlspecialchars((string)($r['firstName'] ?? ''), ENT_QUOTES, 'UTF-8');
        $un  = htmlspecialchars((string)($r['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $pwd = htmlspecialchars((string)($r['password'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tbody .= '<tr style="background-color:'.$bg.';">
            <td style="padding:12px 10px;border:1px solid #E5E7EB;font-size:13px;font-weight:600;color:#6B7280;text-align:center;width:36px;">'.$num.'</td>
            <td style="padding:12px 14px;border:1px solid #E5E7EB;font-size:13px;color:#374151;">'.$ln.'</td>
            <td style="padding:12px 14px;border:1px solid #E5E7EB;font-size:13px;color:#374151;">'.$fn.'</td>
            <td style="padding:12px 14px;border:1px solid #E5E7EB;font-size:13px;font-weight:600;color:#111827;">'.$un.'</td>
            <td style="padding:12px 14px;border:1px solid #E5E7EB;font-size:13px;font-weight:700;color:#1e3a8a;font-family:ui-monospace,Consolas,monospace;">'.$pwd.'</td>
        </tr>';
        $i++;
    }

    return '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:20px 0;border-collapse:collapse;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <thead>
            <tr style="background-color:#1e3a8a;">
                <th style="padding:12px 10px;border:1px solid #1e3a8a;text-align:center;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;width:36px;">N°</th>
                <th style="padding:12px 14px;border:1px solid #1e3a8a;text-align:left;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Nom</th>
                <th style="padding:12px 14px;border:1px solid #1e3a8a;text-align:left;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Prénom</th>
                <th style="padding:12px 14px;border:1px solid #1e3a8a;text-align:left;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Identifiant</th>
                <th style="padding:12px 14px;border:1px solid #1e3a8a;text-align:left;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;">Mot de passe</th>
            </tr>
        </thead>
        <tbody>'.$tbody.'</tbody>
    </table>';
}

/** @return list<array{username:string,password:string}> */
function fetchDemoAccountsForImportMail(?array $usernames = null): array
{
    try {
        if (!class_exists(Client::class)) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }

        $uri = medacapMongoUri();
        $users = (new Client($uri))->academy->users;

        $filter = ['active' => true];
        if ($usernames !== null) {
            $usernames = array_values(array_filter(array_map('strval', $usernames)));
            if (empty($usernames)) {
                return [];
            }
            $filter['username'] = ['$in' => $usernames];
        } else {
            $filter['username'] = new Regex('demo', 'i');
        }

        $cursor = $users->find(
            $filter,
            [
                'projection' => ['username' => 1, 'visiblePassword' => 1],
                'sort' => ['username' => 1],
                'limit' => 5,
            ]
        );

        $demos = [];
        foreach ($cursor as $user) {
            $username = trim((string)($user['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $demos[] = [
                'username' => $username,
                'password' => (string)($user['visiblePassword'] ?? '0000'),
            ];
        }

        return $demos;
    } catch (\Throwable $e) {
        return [];
    }
}

function buildCandidateImportDemoHtml(array $demoAccounts): string
{
    if (empty($demoAccounts)) {
        return '';
    }

    $rows = '';
    foreach ($demoAccounts as $demo) {
        $username = htmlspecialchars((string)$demo['username'], ENT_QUOTES, 'UTF-8');
        $password = htmlspecialchars((string)$demo['password'], ENT_QUOTES, 'UTF-8');
        $rows .= '<tr>
            <td style="padding:10px 12px;border:1px solid #E5E7EB;font-size:13px;font-weight:600;color:#111827;">'.$username.'</td>
            <td style="padding:10px 12px;border:1px solid #E5E7EB;font-size:13px;font-weight:700;color:#1e3a8a;font-family:ui-monospace,Consolas,monospace;">'.$password.'</td>
        </tr>';
    }

    return '<div style="margin:24px 0 0;padding:18px 20px;background:#f8fafc;border:1px solid #dbeafe;border-radius:10px;">
        <p style="margin:0 0 8px 0;font-size:15px;font-weight:700;color:#1e3a8a;">Compte démo pour tester le parcours candidat</p>
        <p style="margin:0 0 14px 0;font-size:13px;color:#475569;line-height:1.6;">
            Avant de transmettre les accès aux candidats, vous pouvez vous connecter avec le compte démo ci-dessous pour vérifier l\'épreuve et le déroulement du test.
        </p>
        <table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:420px;border-collapse:collapse;background:#ffffff;border-radius:8px;overflow:hidden;">
            <thead>
                <tr style="background-color:#eff6ff;">
                    <th style="padding:10px 12px;border:1px solid #dbeafe;text-align:left;font-size:11px;font-weight:700;color:#1e3a8a;text-transform:uppercase;">Identifiant démo</th>
                    <th style="padding:10px 12px;border:1px solid #dbeafe;text-align:left;font-size:11px;font-weight:700;color:#1e3a8a;text-transform:uppercase;">Mot de passe</th>
                </tr>
            </thead>
            <tbody>'.$rows.'</tbody>
        </table>
        <p style="margin:12px 0 0 0;font-size:12px;color:#64748b;font-style:italic;">'.(count($demoAccounts) > 1 ? 'Ces comptes sont réservés' : 'Ce compte est réservé').' aux tests internes (RH / support), pas à la diffusion aux candidats.</p>
    </div>';
}

function sendCandidateImportSummaryMail(
    string $to,
    string $rhFirstName,
    string $campaignName,
    int $total,
    array $rows,
    ?string $country = null,
    bool $noCc = false,
    array $demoAccounts = []
): bool {
    if ($to === '' || $total <= 0 || empty($rows)) {
        return false;
    }

    $link = medacapLinkForCountry($country ?? '');
    $campaignEsc = htmlspecialchars($campaignName, ENT_QUOTES, 'UTF-8');
    $tableHtml = buildCandidateImportTableHtml($rows);

    if ($total > 1) {
        $subject = 'Comptes des Candidats de la Campagne de ' . $campaignName;
        $secondPara = 'Les ' . $total . ' candidats peuvent dès à présent se connecter et passer leurs épreuves. Voici les identifiants à transmettre à chacun :';
        $title = 'Comptes des candidats de la campagne';
    } else {
        $subject = 'Compte du Candidat de la Campagne de ' . $campaignName;
        $secondPara = 'Le candidat peut dès à présent se connecter et passer ses épreuves. Voici son identifiant de connexion :';
        $title = 'Compte candidat de la campagne';
    }

    $message = '<p style="margin:0 0 12px 0;">Les accès sur la plateforme MEDACAP ont été créés pour les candidats de la campagne de <strong>'.$campaignEsc.'</strong>.</p>'
        . '<p style="margin:0 0 4px 0;">'.$secondPara.'</p>'
        . $tableHtml
        . buildCandidateImportDemoHtml($demoAccounts);

    if ($noCc) {
        $subject = '[TEST] ' . $subject;
    }

    $emailData = [
        'userName'        => $rhFirstName !== '' ? $rhFirstName : 'Responsable RH',
        'title'           => $title,
        'message'         => $message,
        'ctaText'         => 'Accéder à la plateforme',
        'ctaLink'         => $link,
        'teamName'        => TEAM_NAME,
        'additionalInfo'  => 'Communiquez à chaque candidat son identifiant, son mot de passe et le lien de connexion. Le compte démo sert uniquement à tester le parcours avant envoi aux candidats.',
    ];

    return sendTemplatedMedacapEmail($to, $subject, $emailData, [], null, !$noCc);
}

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
   SIGNALEMENT QUESTION QUIZ (image manquante)
   ========================================================================= */
function sendMailQuizQuestionReport(
    string $candidateName,
    string $paperLabel,
    string $questionRef,
    string $questionLabel,
    string $reportedAt,
    string $questionId = ''
): bool {
    $refPart = $questionRef !== '' ? $questionRef : 'question';
    $subject = '[MEDACAP] Signalement image — ' . $refPart;

    $emailData = [
        'userName' => 'Équipe CFAO Academy',
        'title'    => 'Signalement — image manquante ou non affichée',
        'message'  => 'Un candidat a signalé une question pendant un test (illustration absente ou non chargée).',
        'details'  => [
            ['label' => 'Candidat', 'value' => $candidateName, 'highlight' => true],
            ['label' => 'Épreuve', 'value' => $paperLabel],
            ['label' => 'Référence', 'value' => $questionRef !== '' ? $questionRef : '—'],
            ['label' => 'Question', 'value' => $questionLabel, 'highlight' => true],
            ['label' => 'ID question', 'value' => $questionId !== '' ? $questionId : '—'],
            ['label' => 'Date et heure du signalement', 'value' => $reportedAt, 'highlight' => true],
        ],
        'additionalInfo' => 'Message automatique envoyé depuis le quiz candidat MEDACAP.',
    ];

    return sendTemplatedMedacapEmail(
        'jakaa-ext@cfao.com',
        $subject,
        $emailData,
        ['imbowou-ext@cfao.com'],
        null,
        false
    );
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
    return medacapAppBaseUrl($country);
}

function medacapReportRecruitmentUrl(string $paperIdStr): string
{
    return rtrim(medacapLinkForCountry('Cameroun'), '/') . '/views/measure/rapportRecrutement.php?paper=' . rawurlencode($paperIdStr);
}

function sendMedacapEmailWithAttachment(
    string $to,
    string $subject,
    string $htmlBody,
    string $filePath,
    string $fileName,
    array $extraCc = [],
    bool $includeStaticCc = true
): bool {
    if ($to === '' || !is_readable($filePath)) {
        return false;
    }

    $cc = $includeStaticCc
        ? array_values(array_unique(array_filter(array_merge(CC_STATIC, $extraCc))))
        : array_values(array_unique(array_filter($extraCc)));

    $boundary = '=_Medacap_' . md5((string) microtime(true));
    $encoded = chunk_split(base64_encode((string) file_get_contents($filePath)));
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName) ?: 'rapport.xlsx';

    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        empty($cc) ? '' : 'Cc: ' . implode(', ', $cc),
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name="' . $safeName . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= 'Content-Disposition: attachment; filename="' . $safeName . "\"\r\n\r\n";
    $body .= $encoded . "\r\n";
    $body .= "--{$boundary}--";

    return mail($to, $subject, $body, implode("\r\n", array_values(array_filter($headers))));
}

function sendPaperCompletionThresholdMail(
    Client $mongo,
    string $paperLabel,
    string $paperIdStr,
    array $stats,
    string $subsidiary = 'CFAO MOBILITY CAMEROON'
): bool {
    $rhEmails = getRhAndDpsEmails($subsidiary, $mongo);
    $toList = array_values(array_unique(array_filter(array_merge($rhEmails, [SUPER_ADMIN_EMAIL]))));
    if ($toList === []) {
        $toList = [SUPER_ADMIN_EMAIL];
    }

    $thresholdPct = (float) ($stats['threshold_pct'] ?? 60);
    $subject = 'MEDACAP — ' . round($thresholdPct) . '% composé : ' . $paperLabel;

    $emailData = [
        'title' => 'Seuil de complétion atteint',
        'message' => sprintf(
            'L\'épreuve <strong>%s</strong> a atteint le seuil de %.0f%% de candidats composés.',
            htmlspecialchars($paperLabel, ENT_QUOTES, 'UTF-8'),
            $thresholdPct
        ),
        'details' => [
            ['label' => 'Inscrits', 'value' => (string) ($stats['enrolled'] ?? 0)],
            ['label' => 'Composés', 'value' => (string) ($stats['composed'] ?? 0)],
            ['label' => 'Complétion', 'value' => ($stats['completion_pct'] ?? 0) . '%', 'highlight' => true],
        ],
        'ctaText' => 'Consulter le rapport',
        'ctaLink' => medacapReportRecruitmentUrl($paperIdStr),
    ];

    return sendTemplatedMedacapEmail(implode(', ', $toList), $subject, $emailData);
}

function sendPaperManualReportToRh(
    Client $mongo,
    string $paperLabel,
    string $paperIdStr,
    array $stats,
    string $excelPath,
    string $subsidiary = 'CFAO MOBILITY CAMEROON'
): bool {
    $rhEmails = getRhAndDpsEmails($subsidiary, $mongo);
    $toList = array_values(array_unique(array_filter($rhEmails)));
    if ($toList === []) {
        $toList = [SUPER_ADMIN_EMAIL];
    }

    $subject = 'MEDACAP — Rapport recrutement : ' . $paperLabel;
    $emailData = [
        'title' => 'Rapport recrutement',
        'message' => sprintf(
            'Veuillez trouver ci-joint le rapport Excel de l\'épreuve <strong>%s</strong>.',
            htmlspecialchars($paperLabel, ENT_QUOTES, 'UTF-8')
        ),
        'details' => [
            ['label' => 'Inscrits', 'value' => (string) ($stats['enrolled'] ?? 0)],
            ['label' => 'Composés', 'value' => (string) ($stats['composed'] ?? 0)],
            ['label' => 'Complétion', 'value' => ($stats['completion_pct'] ?? 0) . '%'],
        ],
        'ctaText' => 'Ouvrir le rapport en ligne',
        'ctaLink' => medacapReportRecruitmentUrl($paperIdStr),
    ];

    $html = generateEmailTemplate($emailData);
    $fileName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $paperLabel) . '_rapport.xlsx';

    return sendMedacapEmailWithAttachment(
        implode(', ', $toList),
        $subject,
        $html,
        $excelPath,
        $fileName
    );
}

/**
 * Code à 6 chiffres affiché "123 456", lisible sur téléphone.
 */
function medacapLoginCodeBlockHtml(string $code, int $minutes, string $lang = 'fr'): string
{
    $isEn = $lang === 'en';
    $display = htmlspecialchars(substr($code, 0, 3) . ' ' . substr($code, 3), ENT_QUOTES, 'UTF-8');
    // Au-delà de 2 h, en heures : "72 heures" plutôt que "4320 minutes"
    $hours = ($minutes >= 120 && $minutes % 60 === 0) ? intdiv($minutes, 60) : 0;
    $validity = $isEn
        ? 'Valid for ' . ($hours ? $hours . ' hours' : $minutes . ' minutes') . ', single use.'
        : 'Valable ' . ($hours ? $hours . ' heures' : $minutes . ' minutes') . ', une seule fois.';
    return '<div style="margin:16px 0;padding:16px;background:#F4F6F8;border-radius:8px;text-align:center;">'
        . '<div style="font-family:\'Courier New\',monospace;font-size:32px;font-weight:700;letter-spacing:8px;color:#1F2937;">' . $display . '</div>'
        . '<div style="margin-top:6px;font-size:13px;color:#6B7280;">' . $validity . '</div>'
        . '</div>';
}

/**
 * Connexion sans mot de passe : code (à taper sur le PC) puis lien. Aucune copie.
 * Le code n'est jamais dans l'objet (écran verrouillé du téléphone).
 */
function sendLoginLinkEmail(string $to, string $greetingName, string $link, string $lang = 'fr', string $code = '', int $linkMinutes = 30, int $codeMinutes = 10): bool
{
    $isEn = $lang === 'en';
    if ($code === '') {
        $title = $isEn ? 'Your sign-in link' : 'Votre lien de connexion';
        $message = $isEn
            ? '<p>Click the button to open your MEDACAP session. This link is valid for ' . $linkMinutes . ' minutes and can only be used once.</p>'
            : '<p>Cliquez sur le bouton pour ouvrir votre session MEDACAP. Ce lien est valable ' . $linkMinutes . ' minutes et ne peut servir qu\'une fois.</p>';
    } else {
        $title = $isEn ? 'Your sign-in code' : 'Votre code de connexion';
        $message = ($isEn
                ? '<p>On the computer where you asked to sign in, type this code:</p>'
                : '<p>Sur l\'ordinateur où vous avez demandé la connexion, tapez ce code :</p>')
            . medacapLoginCodeBlockHtml($code, $codeMinutes, $lang)
            . ($isEn
                ? '<p>If you are reading this on a CFAO network computer, you can also click the button (valid for ' . $linkMinutes . ' minutes). Once one is used, the other stops working.</p><p>Didn\'t ask? Ignore this email.</p>'
                : '<p>Si vous lisez ce mail sur un poste du réseau CFAO, vous pouvez aussi cliquer sur le bouton (valable ' . $linkMinutes . ' minutes). Une fois l\'un utilisé, l\'autre ne fonctionne plus.</p><p>Vous n\'avez rien demandé ? Ignorez ce mail.</p>');
    }
    $emailData = array(
        'lang' => $isEn ? 'en' : 'fr',
        'userName' => $greetingName,
        'title' => $title,
        'message' => $message,
        'ctaText' => $isEn ? 'Open my session' : 'Ouvrir ma session',
        'ctaLink' => $link,
    );
    $html = quoted_printable_encode(generateEmailTemplate($emailData));
    $headers = buildHeaders(array(), null, false);
    $headers .= "\r\nContent-Transfer-Encoding: quoted-printable";
    $subject = '=?UTF-8?B?' . base64_encode('MEDACAP - ' . $title) . '?=';
    return mail($to, $subject, $html, $headers);
}

/**
 * Prévient le support quand une demande de lien de connexion n'aboutit pas.
 * Au plus une alerte par adresse toutes les 30 minutes, et 20 par heure au total.
 *
 * @param MongoDB\Database $academy
 * @param string $motif    mdp (mot de passe oublié) | mail (connexion par mail)
 * @param string $cause    none | shared | send_failed
 * @param array  $accounts Comptes actifs trouvés pour l'adresse
 * @param string $lang     Langue de la page au moment de la demande (fr | en)
 */
function sendLoginSupportAlert($academy, string $email, string $motif, string $cause, array $accounts, string $lang = 'fr'): bool
{
    try {
        $alerts = $academy->login_support_alerts;
        $now = time();
        $sinceAddress = new MongoDB\BSON\UTCDateTime(($now - 30 * 60) * 1000);
        $sinceGlobal = new MongoDB\BSON\UTCDateTime(($now - 3600) * 1000);
        if ($alerts->count(array('email' => $email, 'created' => array('$gte' => $sinceAddress))) > 0
            || $alerts->count(array('created' => array('$gte' => $sinceGlobal))) >= 20) {
            return false;
        }
        $alerts->insertOne(array(
            'email' => $email,
            'motif' => $motif,
            'cause' => $cause,
            'created' => new MongoDB\BSON\UTCDateTime(),
        ));

        $createLink = '';
        $names = array();
        foreach ($accounts as $acc) {
            $names[] = (string) $acc['username'] . ' (' . (isset($acc['profile']) ? (string) $acc['profile'] : '?') . ')';
        }
        if ($cause === 'shared') {
            $problem = 'Plusieurs comptes actifs partagent cette adresse : ' . implode(', ', $names);
        } elseif ($cause === 'send_failed') {
            $problem = 'Compte trouvé (' . implode(', ', $names) . '), mais l\'envoi du lien a échoué';
        } else {
            $inactive = $academy->users->count(array(
                'email' => new Regex('^' . preg_quote($email, '/') . '$', 'i'),
            ));
            $problem = $inactive > 0
                ? 'Aucun compte actif ; ' . $inactive . ' compte(s) inactif(s) avec cette adresse'
                : 'Aucun compte avec cette adresse';
            // Compte inactif : le réactiver plutôt qu'en créer un second
            if ($inactive === 0) {
                require_once __DIR__ . '/../core/login_links.php';
                $createLink = medacapLoginLinkBase(array()) . '/views/explore/createUser.php?email=' . rawurlencode($email);
            }
        }

        $emailData = array(
            'userName' => 'Support MEDACAP',
            'title' => 'Demande de connexion sans suite',
            'message' => '<p>Une demande de lien de connexion n\'a pas abouti. Répondre à ce mail écrit directement à l\'adresse saisie.</p>',
            'details' => array(
                array('label' => 'Adresse saisie', 'value' => $email, 'highlight' => true),
                array('label' => 'Demande', 'value' => $motif === 'mdp' ? 'Mot de passe oublié' : 'Connexion par mail'),
                array('label' => 'Problème', 'value' => $problem),
                array('label' => 'Langue', 'value' => $lang === 'en' ? 'Anglais' : 'Français'),
                array('label' => 'Date', 'value' => date('d/m/Y H:i')),
                array('label' => 'IP', 'value' => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : ''),
                array('label' => 'Navigateur', 'value' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 200) : ''),
            ),
        );
        if ($createLink !== '') {
            $emailData['ctaText'] = 'Créer le compte';
            $emailData['ctaLink'] = $createLink;
            $emailData['additionalInfo'] = 'Le formulaire s\'ouvre avec l\'adresse déjà saisie. Laissez cochée la case « code et lien de connexion directe » : la personne pourra se connecter dès réception du mail.';
        }
        $html = quoted_printable_encode(generateEmailTemplate($emailData));
        $headers = buildHeaders(array(), $email, false);
        $headers .= "\r\nContent-Transfer-Encoding: quoted-printable";
        $subject = '=?UTF-8?B?' . base64_encode('MEDACAP - Connexion sans suite : ' . $email) . '?=';
        return mail(SUPER_ADMIN_EMAIL, $subject, $html, $headers);
    } catch (Throwable $e) {
        error_log('sendLoginSupportAlert: ' . get_class($e) . ' ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return false;
    }
}

/**
 * Code de vérification de l'adresse ajoutée à la connexion (views/addEmail.php). Aucune copie.
 */
function sendEmailVerifyCodeEmail(string $to, string $greetingName, string $code, string $lang = 'fr', int $codeMinutes = 10): bool
{
    $isEn = $lang === 'en';
    $title = $isEn ? 'Confirm your email address' : 'Confirmez votre adresse mail';
    $message = ($isEn
            ? '<p>To link this address to your MEDACAP account, type this code on the page that asked for it:</p>'
            : '<p>Pour associer cette adresse à votre compte MEDACAP, tapez ce code sur la page qui vous l\'a demandé :</p>')
        . medacapLoginCodeBlockHtml($code, $codeMinutes, $lang)
        . ($isEn
            ? '<p>You can then sign in with this address, without a password. Didn\'t ask? Ignore this email: nothing will change.</p>'
            : '<p>Vous pourrez ensuite vous connecter avec cette adresse, sans mot de passe. Vous n\'avez rien demandé ? Ignorez ce mail : rien ne sera modifié.</p>');
    $emailData = array(
        'lang' => $isEn ? 'en' : 'fr',
        'userName' => $greetingName,
        'title' => $title,
        'message' => $message,
    );
    $html = quoted_printable_encode(generateEmailTemplate($emailData));
    $headers = buildHeaders(array(), null, false);
    $headers .= "\r\nContent-Transfer-Encoding: quoted-printable";
    $subject = '=?UTF-8?B?' . base64_encode('MEDACAP - ' . $title) . '?=';
    return mail($to, $subject, $html, $headers);
}