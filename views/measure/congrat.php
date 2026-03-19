<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {
    include_once "partials/header.php";
    ?>
    <!--begin::Title-->
    <title><?php echo isset($evaluation_completee) ? $evaluation_completee : 'Évaluation Complétée'; ?> | CFAO Mobility Academy</title>
    <!--end::Title-->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/core/variables.css">
    <link rel="stylesheet" href="../../public/css/core/utilities.css">
    <link rel="stylesheet" href="../../public/css/modules/skeleton-loader.css">
    <link rel="stylesheet" href="../../public/css/modules/training-plan.css">
    <link rel="stylesheet" href="/MEDACAP/public/css/background-manager.css">

    <style>
        :root {
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #DBEAFE;
            --secondary: #7C3AED;
            --accent: #93C5FD;
            --success: #22C55E;
            --success-light: #D1FAE5;
            --dark: #111827;
            --gray-700: #374151;
            --gray-500: #6B7280;
            --gray-200: #E5E7EB;
            --gray-100: #F9FAFB;
            --light: #FFFFFF;
        }

        .content-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lms-card {
            background: var(--light);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 3.5rem 3rem;
            text-align: center;
            width: fit-content;
            position: relative;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin: 0 auto;
        }

        .lms-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
        }

        .lms-card-header {
            margin-bottom: 2.5rem;
            position: relative;
        }

        .lms-badge {
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            color: var(--light);
            font-size: 3rem;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
            position: relative;
            z-index: 1;
        }

        .lms-badge::after {
            content: '';
            position: absolute;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: var(--primary-light);
            z-index: -1;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.7; }
            50% { transform: scale(1.1); opacity: 0.4; }
            100% { transform: scale(0.95); opacity: 0.7; }
        }

        .lms-progress-container {
            width: 100%;
            margin: 2.5rem 0;
            position: relative;
        }

        .lms-progress {
            width: 100%;
            height: 10px;
            background-color: var(--gray-200);
            border-radius: 5px;
            overflow: hidden;
        }

        .lms-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 5px;
            width: 100%;
            animation: lmsProgress 1.5s cubic-bezier(0.65, 0, 0.35, 1);
            position: relative;
        }

        .lms-progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background-image: linear-gradient(-45deg, rgba(255, 255, 255, 0.2) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0.2) 75%, transparent 75%, transparent);
            background-size: 15px 15px;
            animation: moveStripes 1s linear infinite;
            border-radius: 5px;
        }

        @keyframes lmsProgress {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        @keyframes moveStripes {
            0% { background-position: 0 0; }
            100% { background-position: 30px 0; }
        }

        .lms-title {
            color: var(--dark);
            font-weight: 700;
            font-size: 2.25rem;
            margin-bottom: 1rem;
            letter-spacing: -0.025em;
        }

        .lms-message {
            color: var(--gray-700);
            font-size: 1.125rem;
            line-height: 1.7;
            margin-bottom: 2.5rem;
        }

        .lms-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin: 2.5rem 0;
        }

        .lms-stat-card {
            padding: 1.5rem;
            background: var(--gray-100);
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .lms-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border-color: var(--primary-light);
        }

        .lms-stat-icon {
            margin-bottom: 1rem;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .lms-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .lms-stat-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .lms-success-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--success-light);
            color: var(--success);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .lms-success-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--success);
            color: var(--light);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }

        .lms-evaluation-note {
            margin: 2rem 0;
            padding: 1.5rem;
            background-color: var(--primary-light);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            text-align: left;
            display: flex;
            align-items: flex-start;
        }

        .lms-evaluation-icon {
            color: var(--primary);
            font-size: 1.5rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .lms-evaluation-text {
            flex: 1;
            color: var(--dark);
            font-size: 1rem;
        }

        .lms-evaluation-text strong {
            color: var(--primary);
        }

        .lms-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .lms-btn-primary {
            background: linear-gradient(to right, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            border-radius: 8px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            color: var(--light);
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
        }

        .lms-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);
        }

        @media (max-width: 768px) {
            .lms-card {
                padding: 2.5rem 1.5rem;
            }

            .lms-stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .lms-title {
                font-size: 1.875rem;
            }

            .lms-message {
                font-size: 1rem;
            }

            .lms-actions {
                flex-direction: column;
            }

            .lms-btn-primary {
                width: 100%;
            }
        }
    </style>

    <!--begin::Body-->
    <div class="content-container fs-6 page-background-container" id="kt_content">
        <div class="page-background-overlay with-gradient bg-welcome-tech"></div>
        <div class="page-content-container">
        <div class="lms-card glass-effect">
            <div class="lms-card-header">
                <!-- Achievement badge with animation -->
                <div class="lms-badge">
                    <i class="fas fa-check-circle"></i>
                </div>
                <!-- Progress animation -->
                <div class="lms-progress-container">
                    <div class="lms-progress">
                        <div class="lms-progress-bar"></div>
                    </div>
                </div>
            </div>

            <!--begin::Title-->
            <h1 class="lms-title"><?php echo isset($evaluation_completee) ? $evaluation_completee : 'Évaluation Complétée'; ?></h1>
            <!--end::Title-->

            <!--begin::Message-->
            <div class="lms-message"><?php echo isset($congrat_text) ? $congrat_text : 'Félicitations ! Vous avez terminé l\'évaluation des tâches professionnelles.'; ?></div>
            <!--end::Message-->

            <!-- Completion indicator -->
            <div class="lms-success-container">
            <span class="lms-success-icon">
                <i class="fas fa-check"></i>
            </span>
                <?php echo isset($evaluation_terminee) ? $evaluation_terminee : 'Évaluation terminée avec succès'; ?>
            </div>

            <!-- Statistics -->
            <div class="lms-stats-grid">
                <div class="lms-stat-card">
                    <div class="lms-stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="lms-stat-value"><?php echo isset($_GET['p']) ? htmlspecialchars($_GET['p']) : 100; ?>%</div>
                    <div class="lms-stat-label"><?php echo isset($taux_completion) ? $taux_completion : 'Taux de complétion'; ?></div>
                </div>
                <div class="lms-stat-card">
                    <div class="lms-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="lms-stat-value"><?php echo date('d/m/Y'); ?></div>
                    <div class="lms-stat-label"><?php echo isset($termine_le) ? $termine_le : 'Terminé le'; ?></div>
                </div>
            </div>

            <!-- Evaluation note -->
            <div class="lms-evaluation-note">
                <div class="lms-evaluation-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="lms-evaluation-text">
                    <strong><?php echo isset($resultats_disponibles) ? $resultats_disponibles : 'Note :'; ?></strong><br>
                    <?php echo isset($resultats_disponibles_message) ? $resultats_disponibles_message : 'Vérifiez que le statut de l\'évaluation est bien à (terminé) pour ce niveau.'; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="lms-actions">
                <a href="./dashboard" class="lms-btn-primary">
                    <i class="fas fa-arrow-right me-2"></i><?php echo isset($retour_tableau_bord) ? $retour_tableau_bord : 'Retour au Tableau de Bord'; ?>
                </a>
            </div>
        </div>
    </div>
        </div> <!--end::page-content-container-->
    </div> <!--end::Body-->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const badge = document.querySelector('.lms-badge');
            setTimeout(() => {
                badge.style.transform = 'scale(1.05) rotate(5deg)';
                badge.style.transition = 'transform 0.5s ease';
            }, 700);
            setTimeout(() => {
                badge.style.transform = 'scale(1) rotate(0deg)';
            }, 1200);

            function createConfetti() {
                const colors = ['#3B82F6', '#7C3AED', '#93C5FD', '#22C55E'];
                const container = document.querySelector('.lms-card');
                for (let i = 0; i < 30; i++) {
                    const confetti = document.createElement('div');
                    confetti.className = 'confetti';
                    confetti.style.cssText = `
                    position: absolute;
                    width: 10px;
                    height: 10px;
                    background-color: ${colors[Math.floor(Math.random() * colors.length)]};
                    opacity: 0;
                    border-radius: 2px;
                    z-index: 0;
                    top: -20px;
                    left: ${Math.random() * 100}%;
                    animation: fall ${Math.random() * 3 + 2}s ease-in forwards;
                    animation-delay: ${Math.random() * 1.5}s;
                    transform: rotate(${Math.random() * 360}deg);
                `;
                    container.appendChild(confetti);
                }
                const style = document.createElement('style');
                style.textContent = `
                @keyframes fall {
                    0% { opacity: 0; transform: translateY(0) rotate(0deg); }
                    10% { opacity: 1; }
                    90% { opacity: 0.8; }
                    100% { opacity: 0; transform: translateY(400px) rotate(${Math.random() * 360}deg); }
                }
            `;
                document.head.appendChild(style);
            }
            createConfetti();
        });
    </script>

    <?php include_once "partials/footer.php"; ?>
    <?php
}
?>