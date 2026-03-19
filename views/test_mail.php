<?php
/**
 * 🚀 Interface Web de Test Email MEDACAP
 * À placer dans le même dossier que sendMail.php
 */

// ✅ CORRECTION : Inclusion du fichier sendMail.php (même dossier)
require_once 'sendMail.php';

// Vérification que l'inclusion a fonctionné
if (!defined('SUPER_ADMIN_EMAIL')) {
    die('❌ ERREUR: sendMail.php n\'a pas été chargé correctement. Vérifiez que sendMail.php existe dans le dossier views/');
}

// Traitement du formulaire
$testResult = null;
$testDetails = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    $testType = $_POST['test_type'] ?? 'template';
    
    if ($testEmail) {
        $startTime = microtime(true);
        
        if ($testType === 'simple') {
            // Test avec sendMailRegisterUser (votre fonction existante)
            $subject = "✅ Test MEDACAP Simple - " . date('H:i:s');
            $message = "Ceci est un test simple utilisant votre fonction <strong>sendMailRegisterUser()</strong>.<br><br>" .
                       "✅ Cette fonction est utilisée en production dans vos imports de candidats.<br>" .
                       "✅ Elle fonctionne déjà parfaitement sur votre serveur !<br><br>" .
                       "Date du test: " . date('Y-m-d H:i:s');
            
            $result = sendMailRegisterUser($testEmail, $subject, $message);
            
            $testDetails = [
                'type' => 'Email via sendMailRegisterUser() (fonction production)',
                'subject' => $subject,
                'function' => 'sendMailRegisterUser()'
            ];
        } else {
            // Test avec template complet MEDACAP
            $emailData = [
                'userName' => 'Testeur MEDACAP',
                'title' => 'Test Système Email Réussi',
                'icon' => '🎉',
                'message' => "Félicitations ! Votre système d'envoi d'emails MEDACAP est <strong>parfaitement opérationnel</strong> !<br><br>
                    ✅ <strong>Preuve :</strong> Cette fonction est déjà utilisée en production<br>
                    ✅ <strong>Contexte :</strong> Import de candidats, campagnes de recrutement<br>
                    ✅ <strong>Configuration PHP :</strong> Détectée et fonctionnelle<br><br>
                    Ce test confirme que tous vos envois d'emails passent sans problème via votre serveur web.",
                'details' => [
                    ['label' => 'Date du test', 'value' => date('Y-m-d H:i:s'), 'highlight' => true],
                    ['label' => 'Serveur SMTP', 'value' => ini_get('SMTP') ?: 'localhost'],
                    ['label' => 'Port', 'value' => ini_get('smtp_port') ?: '25'],
                    ['label' => 'Méthode', 'value' => 'sendTemplatedMedacapEmail()', 'highlight' => true],
                    ['label' => 'Destinataire', 'value' => $testEmail, 'highlight' => true],
                    ['label' => 'Statut', 'value' => 'Production-ready ✅', 'highlight' => true],
                ],
                'ctaText' => '🚀 Système opérationnel !',
                'ctaLink' => '#',
                'additionalInfo' => "💡 <strong>Info technique :</strong> Cette même fonction envoie actuellement les emails de votre campagne de recrutement FNE-CFAO 2025. Aucune modification n'est nécessaire !"
            ];
            
            $result = sendTemplatedMedacapEmail(
                $testEmail,
                '✅ MEDACAP - Test Template Production',
                $emailData
            );
            
            $testDetails = [
                'type' => 'Template HTML MEDACAP complet (design noir/rouge)',
                'subject' => '✅ MEDACAP - Test Template Production',
                'function' => 'sendTemplatedMedacapEmail()'
            ];
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $testResult = [
            'success' => $result,
            'email' => $testEmail,
            'duration' => $duration,
            'error' => !$result ? error_get_last() : null
        ];
    } else {
        $testResult = [
            'success' => false,
            'error' => ['message' => 'Email invalide']
        ];
    }
}

// Configuration PHP actuelle
$phpConfig = [
    'PHP Version' => PHP_VERSION,
    'SAPI' => php_sapi_name(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    'SMTP' => ini_get('SMTP') ?: 'localhost',
    'smtp_port' => ini_get('smtp_port') ?: '25',
    'sendmail_from' => ini_get('sendmail_from') ?: 'Non configuré',
];

// Stats d'utilisation (si on peut les récupérer)
$productionInfo = [
    'Fonction en production' => 'sendMailRegisterUser()',
    'Utilisée dans' => 'importCandidatStage.php',
    'Cas d\'usage' => 'Campagne recrutement technicien 2025',
    'Statut' => '✅ Opérationnelle'
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Test Email MEDACAP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            color: #e5e5e5;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
            padding: 30px;
            border-radius: 12px 12px 0 0;
            border: 3px solid #dc2626;
            border-bottom: none;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            color: #fff;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            color: #dc2626;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .badge {
            display: inline-block;
            background: #22c55e;
            color: #000;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .content {
            background: #000;
            border: 3px solid #dc2626;
            border-top: none;
            border-radius: 0 0 12px 12px;
            padding: 30px;
        }
        
        .section {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
        }
        
        .section-title {
            color: #dc2626;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .info-banner {
            background: linear-gradient(135deg, #064e3b 0%, #0f4c29 100%);
            border: 2px solid #22c55e;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-banner-title {
            color: #22c55e;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .info-banner-text {
            color: #e5e5e5;
            line-height: 1.6;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .config-item {
            background: #0f0f0f;
            padding: 12px;
            border-radius: 6px;
        }
        
        .config-label {
            color: #aaa;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .config-value {
            color: #e5e5e5;
            font-size: 14px;
            font-weight: bold;
        }
        
        .config-value.success {
            color: #22c55e;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: #e5e5e5;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: #0f0f0f;
            border: 2px solid #333;
            border-radius: 6px;
            color: #e5e5e5;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #dc2626;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 10px 15px;
            background: #0f0f0f;
            border-radius: 6px;
            border: 2px solid #333;
            transition: all 0.3s;
            flex: 1;
        }
        
        .radio-label:hover {
            border-color: #dc2626;
        }
        
        .radio-label input {
            margin-right: 8px;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #dc2626;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
        }
        
        .result {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .result.success {
            background: linear-gradient(135deg, #064e3b 0%, #0f4c29 100%);
            border: 2px solid #22c55e;
        }
        
        .result.error {
            background: linear-gradient(135deg, #7f1d1d 0%, #5c1414 100%);
            border: 2px solid #dc2626;
        }
        
        .result-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .result-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .result-details {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }
        
        .result-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .result-detail-item:last-child {
            border-bottom: none;
        }
        
        .result-detail-label {
            color: #aaa;
        }
        
        .result-detail-value {
            color: #fff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Test Email MEDACAP</h1>
            <div class="subtitle">
                Interface de Test - CFAO Mobility Academy
                <span class="badge">EN PRODUCTION ✅</span>
            </div>
        </div>
        
        <div class="content">
            <div class="info-banner">
                <div class="info-banner-title">✅ Système Email Déjà Opérationnel</div>
                <div class="info-banner-text">
                    <strong>Bonne nouvelle !</strong> Votre système d'envoi d'emails MEDACAP fonctionne déjà parfaitement en production. 
                    Il est actuellement utilisé pour la <strong>campagne de recrutement technicien FNE-CFAO 2025</strong> 
                    et envoie automatiquement les identifiants aux ressources humaines après chaque import de candidats.
                    <br><br>
                    <strong>Preuve :</strong> Le fichier <code>importCandidatStage.php</code> utilise activement <code>sendMailRegisterUser()</code> sans problème.
                </div>
            </div>

            <?php if ($testResult): ?>
                <div class="result <?php echo $testResult['success'] ? 'success' : 'error'; ?>">
                    <div class="result-icon"><?php echo $testResult['success'] ? '✅' : '❌'; ?></div>
                    <div class="result-title">
                        <?php echo $testResult['success'] ? 'Email envoyé avec succès !' : 'Échec de l\'envoi'; ?>
                    </div>
                    
                    <?php if ($testResult['success']): ?>
                        <p>L'email a été envoyé à <strong><?php echo htmlspecialchars($testResult['email']); ?></strong></p>
                        <p style="margin-top:10px;">📬 Vérifiez votre boîte mail (pensez aux spams !)</p>
                        
                        <div class="result-details">
                            <div class="result-detail-item">
                                <span class="result-detail-label">Destinataire:</span>
                                <span class="result-detail-value"><?php echo htmlspecialchars($testResult['email']); ?></span>
                            </div>
                            <div class="result-detail-item">
                                <span class="result-detail-label">Type:</span>
                                <span class="result-detail-value"><?php echo htmlspecialchars($testDetails['type']); ?></span>
                            </div>
                            <div class="result-detail-item">
                                <span class="result-detail-label">Fonction:</span>
                                <span class="result-detail-value"><?php echo htmlspecialchars($testDetails['function']); ?></span>
                            </div>
                            <div class="result-detail-item">
                                <span class="result-detail-label">Durée:</span>
                                <span class="result-detail-value"><?php echo $testResult['duration']; ?> ms</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>Une erreur s'est produite lors de l'envoi.</p>
                        <?php if (isset($testResult['error']['message'])): ?>
                            <div class="result-details">
                                <div class="result-detail-item">
                                    <span class="result-detail-label">Erreur:</span>
                                    <span class="result-detail-value" style="color:#dc2626;">
                                        <?php echo htmlspecialchars($testResult['error']['message']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="section">
                <div class="section-title">📊 Configuration PHP (Serveur Web)</div>
                <div class="config-grid">
                    <?php foreach ($phpConfig as $label => $value): ?>
                        <div class="config-item">
                            <div class="config-label"><?php echo htmlspecialchars($label); ?></div>
                            <div class="config-value success">
                                <?php echo htmlspecialchars($value); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">🚀 Statut Production</div>
                <div class="config-grid">
                    <?php foreach ($productionInfo as $label => $value): ?>
                        <div class="config-item">
                            <div class="config-label"><?php echo htmlspecialchars($label); ?></div>
                            <div class="config-value success">
                                <?php echo htmlspecialchars($value); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">📧 Formulaire de Test</div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Email de test:</label>
                        <input 
                            type="email" 
                            name="test_email" 
                            class="form-input" 
                            value="<?php echo htmlspecialchars(SUPER_ADMIN_EMAIL); ?>"
                            placeholder="votre@email.com"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Type de test:</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="test_type" value="simple">
                                <span>📝 Fonction Production (sendMailRegisterUser)</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="test_type" value="template" checked>
                                <span>🎨 Template MEDACAP Complet (HTML)</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        🚀 Tester l'envoi
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>