<?php
// Composant Toast utilisable directement en PHP
function toastComponent(string $message = '', ?string $type = null): string
{
    $colors = [
        'success' => '#16a34a',
        'error'   => '#dc2626',
        'info'    => '#3b82f6'
    ];

    // Styles et scripts ajoutés une seule fois
    $style = <<<CSS
        <style>
            .ui-toast {
                display: none;
                position: fixed;
                top: 0;
                left: 50%;
                transform: translateX(-50%);
                z-index: 1000;
                opacity: 0;
                transition: opacity 0.5s ease;
                max-width: 75%;
                margin: 10px 0;
            }
            .ui-toast .toast-content {
                padding: 12px 20px;
                border-radius: 4px;
                color: white;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                font-size: 14px;
                line-height: 1.5;
                text-align: center;
            }
        </style>
    CSS;

    $script = <<<JS
        <script>
            // Ce script ne fait rien automatiquement,
            // il sera utilisé par showToast() côté JS
        </script>
    JS;

    // Si aucun type fourni → afficher 2 toasts vides prêts à être utilisés
    if ($type === null) {
        return $style . '
            <div class="ui-toast" data-type="success">
                <div class="toast-content" style="background: ' . $colors['success'] . '"></div>
            </div>
            <div class="ui-toast" data-type="error">
                <div class="toast-content" style="background: ' . $colors['error'] . '"></div>
            </div>
        ' . $script;
    }

    // Si type fourni → afficher un seul toast avec message
    $color = $colors[$type] ?? $colors['info'];
    return $style . '
        <div class="ui-toast" data-type="' . $type . '">
            <div class="toast-content" style="background: ' . $color . '">
                ' . htmlspecialchars($message) . '
            </div>
        </div>
    ' . $script;
}
