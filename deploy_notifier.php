<?php
require_once __DIR__ . "/vendor/autoload.php";

use MongoDB\Client;
date_default_timezone_set('Africa/Douala');

$to      = "jakaa-ext@cfao.com, imbowou-ext@cfao.com";
$version = $_GET['v'] ?? $_POST['v'] ?? 'Inconnue';
$filesRaw = $_POST['files'] ?? '';
$filesArray = !empty($filesRaw) ? explode('|', $filesRaw) : [];

$subject = "[ASSET] Deploiement MEDACAP effectue";

$date = date('d/m/Y H:i:s');
$env  = "Production (10.68.0.7)";

// 1. Log MongoDB
try {
    $conn        = new Client("mongodb://localhost:27017");
    $deployments = $conn->academy->deployments;
    $deployments->insertOne([
        "date"    => new MongoDB\BSON\UTCDateTime(),
        "version" => $version,
        "env"     => $env,
        "author"  => "Analyste Developpeur Local",
        "status"  => "Success"
    ]);
    $dbStatus = "Enregistr&eacute; en base de donn&eacute;es";
} catch (Exception $e) {
    $dbStatus = "Erreur BD : " . htmlspecialchars($e->getMessage());
}

// 2. Headers
$headers = implode("\r\n", [
    'From: CFAO Mobility Academy <helpdeskcm@cfao.com>',
    'Reply-To: helpdeskcm@cfao.com',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
]);

// 3. Template — concat PHP, entites HTML, bordure via table wrapper
$year   = date('Y');
$v      = htmlspecialchars($version);
$envEsc = htmlspecialchars($env);

$message =
'<!DOCTYPE html>
<html lang="fr" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <!--[if mso]>
  <noscript><xml>
    <o:OfficeDocumentSettings>
      <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
  </xml></noscript>
  <![endif]-->
  <style>body, table, td, a { font-family: \'Segoe UI\', Arial, sans-serif; }</style>
</head>
<body style="margin:0;padding:0;background-color:#ECEEF1;">

<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
  D&eacute;ploiement MEDACAP v' . $v . ' r&eacute;ussi le ' . $date . ' &mdash; ' . $envEsc . '
</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ECEEF1">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <!--
        BORDURE OUTLOOK-SAFE :
        Table externe bgcolor="#D0D3D8" + padding="1" sur la table interne
        = bordure visible de 1px dans Outlook comme dans tous les clients.
      -->
      <table width="602" cellpadding="0" cellspacing="0" border="0"
             bgcolor="#D0D3D8" style="max-width:602px;background-color:#D0D3D8;">
        <tr>
          <td style="padding:1px;">

            <table width="600" cellpadding="0" cellspacing="0" border="0"
                   bgcolor="#FFFFFF" style="max-width:600px;background-color:#ffffff;">

              <!-- LIGNE OR HAUT (hors de tout VML) -->
              <tr>
                <td bgcolor="#C9A84C" height="5" width="600"
                    style="font-size:0;line-height:5px;mso-line-height-rule:exactly;
                           background-color:#C9A84C;">
                  <!--[if mso]>&nbsp;<![endif]-->
                </td>
              </tr>

              <!-- HEADER ROUGE + VML gradient -->
              <tr>
                <td bgcolor="#C8102E" style="padding:0;background-color:#C8102E;">

                  <!--[if mso]>
                  <v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false"
                          style="width:600px;">
                    <v:fill type="gradient" color="#C8102E" color2="#8B0A1E" angle="135"/>
                    <v:textbox style="mso-fit-shape-to-text:true;" inset="0,0,0,0">
                  <table width="600" cellpadding="0" cellspacing="0" border="0">
                  <![endif]-->

                  <table width="100%" cellpadding="0" cellspacing="0" border="0"
                         style="background:linear-gradient(135deg,#C8102E 0%,#8B0A1E 100%);">
                    <tr>
                      <td style="padding:32px 44px 30px;">

                        <!-- Logo + Badge -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                          <tr>
                            <td style="vertical-align:middle;">
                              <table cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                  <td style="padding-bottom:2px;">
                                    <span style="font-size:10px;font-weight:700;letter-spacing:2px;
                                                 color:#FAC7CC;text-transform:uppercase;
                                                 font-family:\'Segoe UI\',Arial,sans-serif;">
                                      CFAO Mobility
                                    </span>
                                  </td>
                                </tr>
                                <tr>
                                  <td>
                                    <span style="font-size:24px;font-weight:700;color:#ffffff;
                                                 letter-spacing:0.5px;line-height:1.2;
                                                 font-family:\'Segoe UI\',Arial,sans-serif;">
                                      Academy
                                    </span>
                                  </td>
                                </tr>
                              </table>
                            </td>
                            <td align="right" style="vertical-align:middle;">
                              <table cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                  <td bgcolor="#FFFFFF" style="padding:7px 16px;background-color:#ffffff;border-radius:2px;">
                                    <span style="font-size:10px;font-weight:700;letter-spacing:1px;
                                                 text-transform:uppercase;color:#C8102E;white-space:nowrap;
                                                 font-family:\'Segoe UI\',Arial,sans-serif;">
                                      &#10003;&nbsp; DEPLOIEMENT REUSSI
                                    </span>
                                  </td>
                                </tr>
                              </table>
                            </td>
                          </tr>
                        </table>

                        <!-- Trait dore -->
                        <table width="40" cellpadding="0" cellspacing="0" border="0"
                               style="margin-top:20px;margin-bottom:20px;">
                          <tr>
                            <td bgcolor="#C9A84C" height="2" width="40"
                                style="font-size:0;line-height:2px;mso-line-height-rule:exactly;
                                       background-color:#C9A84C;">&nbsp;</td>
                          </tr>
                        </table>

                        <!-- Titre -->
                        <table cellpadding="0" cellspacing="0" border="0">
                          <tr>
                            <td>
                              <span style="font-size:28px;font-weight:700;color:#ffffff;line-height:1.1;
                                           font-family:\'Segoe UI\',Arial,sans-serif;">
                                Mise en production
                              </span>
                            </td>
                          </tr>
                          <tr>
                            <td style="padding-top:8px;">
                              <span style="font-size:18px;font-weight:400;
                                           color:#FAC7CC;
                                           font-family:\'Segoe UI\',Arial,sans-serif;">
                                MEDACAP v' . $v . '
                              </span>
                            </td>
                          </tr>
                        </table>

                      </td>
                    </tr>
                  </table>

                  <!--[if mso]>
                  </table>
                  </v:textbox>
                  </v:rect>
                  <![endif]-->

                </td>
              </tr>

              <!-- CORPS BLANC -->
              <tr>
                <td bgcolor="#FFFFFF" style="padding:36px 44px 24px;background-color:#ffffff;">

                  <table cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td style="padding-bottom:6px;">
                        <span style="font-size:15px;color:#1F2937;
                                     font-family:\'Segoe UI\',Arial,sans-serif;">
                          Bonjour,
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding-bottom:30px;">
                        <span style="font-size:14px;color:#6B7280;line-height:1.75;
                                     font-family:\'Segoe UI\',Arial,sans-serif;">
                          Un d&eacute;ploiement a &eacute;t&eacute; effectu&eacute;
                          avec succ&egrave;s vers le serveur de production.<br/>
                          Veuillez trouver ci-dessous le r&eacute;capitulatif
                          de l&rsquo;op&eacute;ration.
                        </span>
                      </td>
                    </tr>
                  </table>

                  <!-- Tableau donnees : border="1" + bordercolor = compatible Outlook -->
                  <table width="100%" cellpadding="0" cellspacing="0"
                         border="1" bordercolor="#E5E7EB"
                         style="border-collapse:collapse;border:1px solid #E5E7EB;">

                    <tr>
                      <td colspan="2" bgcolor="#F9FAFB"
                          style="padding:12px 20px;border-bottom:2px solid #E5E7EB;
                                 background-color:#F9FAFB;">
                        <span style="font-size:10px;font-weight:700;letter-spacing:2px;
                                      color:#9CA3AF;text-transform:uppercase;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">
                          Informations de d&eacute;ploiement
                        </span>
                      </td>
                    </tr>

                    <tr>
                      <td width="36%" bgcolor="#FAFAFA"
                          style="padding:15px 20px;border-bottom:1px solid #EFEFEF;
                                 border-right:1px solid #EFEFEF;background-color:#FAFAFA;
                                 vertical-align:middle;">
                        <span style="font-size:11px;font-weight:700;color:#9CA3AF;
                                      text-transform:uppercase;letter-spacing:0.5px;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">Version</span>
                      </td>
                      <td style="padding:15px 20px;border-bottom:1px solid #EFEFEF;vertical-align:middle;">
                        <span style="font-size:14px;font-weight:700;color:#C8102E;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">v' . $v . '</span>
                      </td>
                    </tr>

                    <tr>
                      <td width="36%" bgcolor="#FAFAFA"
                          style="padding:15px 20px;border-bottom:1px solid #EFEFEF;
                                 border-right:1px solid #EFEFEF;background-color:#FAFAFA;
                                 vertical-align:middle;">
                        <span style="font-size:11px;font-weight:700;color:#9CA3AF;
                                      text-transform:uppercase;letter-spacing:0.5px;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">Date</span>
                      </td>
                      <td style="padding:15px 20px;border-bottom:1px solid #EFEFEF;vertical-align:middle;">
                        <span style="font-size:14px;font-weight:600;color:#111827;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">' . $date . '</span>
                      </td>
                    </tr>

                    <tr>
                      <td width="36%" bgcolor="#FAFAFA"
                          style="padding:15px 20px;border-bottom:1px solid #EFEFEF;
                                 border-right:1px solid #EFEFEF;background-color:#FAFAFA;
                                 vertical-align:middle;">
                        <span style="font-size:11px;font-weight:700;color:#9CA3AF;
                                      text-transform:uppercase;letter-spacing:0.5px;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">Serveur</span>
                      </td>
                      <td style="padding:15px 20px;border-bottom:1px solid #EFEFEF;vertical-align:middle;">
                        <span style="font-size:14px;font-weight:600;color:#111827;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">' . $envEsc . '</span>
                      </td>
                    </tr>

                    <tr>
                      <td width="36%" bgcolor="#FAFAFA"
                          style="padding:15px 20px;border-right:1px solid #EFEFEF;
                                 background-color:#FAFAFA;vertical-align:middle;">
                        <span style="font-size:11px;font-weight:700;color:#9CA3AF;
                                      text-transform:uppercase;letter-spacing:0.5px;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">Statut BD</span>
                      </td>
                      <td style="padding:15px 20px;vertical-align:middle;">
                        <table cellpadding="0" cellspacing="0" border="0">
                          <tr>
                            <td bgcolor="#ECFDF5"
                                style="padding:6px 14px;border:1px solid #6EE7B7;
                                       background-color:#ECFDF5;">
                              <span style="font-size:12px;font-weight:700;color:#065F46;
                                            white-space:nowrap;
                                            font-family:\'Segoe UI\',Arial,sans-serif;">
                                &#10003;&nbsp; ' . $dbStatus . '
                              </span>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>';

if (!empty($filesArray)) {
    $message .= '
                    <tr>
                      <td width="36%" bgcolor="#FAFAFA"
                          style="padding:15px 20px;border-top:1px solid #EFEFEF;border-right:1px solid #EFEFEF;
                                 background-color:#FAFAFA;vertical-align:top;">
                        <span style="font-size:11px;font-weight:700;color:#9CA3AF;
                                      text-transform:uppercase;letter-spacing:0.5px;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">Fichiers modifi&eacute;s</span>
                      </td>
                      <td style="padding:15px 20px;border-top:1px solid #EFEFEF;vertical-align:top;">
                        <ul style="margin:0;padding:0;list-style:none;">';
    foreach ($filesArray as $file) {
        $fileName = basename($file);
        $message .= '
                          <li style="margin-bottom:4px;">
                            <span style="font-size:13px;color:#111827;font-family:\'Segoe UI\',Arial,sans-serif;">
                              &bull; ' . htmlspecialchars($fileName) . '
                              <span style="font-size:11px;color:#9CA3AF;">(' . htmlspecialchars($file) . ')</span>
                            </span>
                          </li>';
    }
    $message .= '
                        </ul>
                      </td>
                    </tr>';
}

$message .= '

                  </table>
                </td>
              </tr>

              <!-- CTA -->
              <tr>
                <td bgcolor="#FFFFFF" align="center"
                    style="padding:20px 44px 38px;background-color:#ffffff;">
                  <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td bgcolor="#C8102E" style="background-color:#C8102E;">
                        <a href="http://10.68.0.7/medacap/"
                           style="display:inline-block;padding:14px 38px;
                                   font-size:13px;font-weight:700;color:#ffffff;
                                   text-decoration:none;letter-spacing:0.5px;
                                   white-space:nowrap;
                                   font-family:\'Segoe UI\',Arial,sans-serif;">
                          V&eacute;rifier la plateforme &nbsp;&rarr;
                        </a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

              <!-- SEPARATEUR -->
              <tr>
                <td bgcolor="#FFFFFF" style="padding:0 44px;background-color:#ffffff;">
                  <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td bgcolor="#E5E7EB" height="1"
                          style="font-size:0;line-height:1px;mso-line-height-rule:exactly;
                                 background-color:#E5E7EB;">&nbsp;</td>
                    </tr>
                  </table>
                </td>
              </tr>

              <!-- FOOTER -->
              <tr>
                <td bgcolor="#FFFFFF" style="padding:20px 44px 28px;background-color:#ffffff;">
                  <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td style="vertical-align:middle;">
                        <span style="font-size:11px;color:#9CA3AF;line-height:1.7;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">
                          Ce message est g&eacute;n&eacute;r&eacute; automatiquement.<br/>
                          Merci de ne pas y r&eacute;pondre directement.
                        </span>
                      </td>
                      <td align="right" style="vertical-align:middle;">
                        <span style="font-size:11px;color:#CCCCCC;font-weight:700;
                                      letter-spacing:1px;text-transform:uppercase;
                                      font-family:\'Segoe UI\',Arial,sans-serif;">
                          CFAO &bull; ' . $year . '
                        </span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

              <!-- LIGNE OR BAS (hors de tout VML) -->
              <tr>
                <td bgcolor="#C9A84C" height="5" width="600"
                    style="font-size:0;line-height:5px;mso-line-height-rule:exactly;
                           background-color:#C9A84C;">
                  <!--[if mso]>&nbsp;<![endif]-->
                </td>
              </tr>

            </table>
          </td>
        </tr>
      </table>
      <!-- /WRAPPER BORDURE -->

      <!-- Copyright -->
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
        <tr>
          <td align="center" style="padding:16px 0 0;">
            <span style="font-size:11px;color:#9CA3AF;
                          font-family:\'Segoe UI\',Arial,sans-serif;">
              &copy; ' . $year . ' MEDACAP &mdash; CFAO Mobility Academy
              &mdash; Notification automatique
            </span>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>

</body>
</html>';

// 4. Envoi
if (mail($to, $subject, $message, $headers)) {
    echo "Notification envoyee a " . $to . "\n";
} else {
    echo "Echec de l'envoi.\n";
}
?>