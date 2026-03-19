<?php
/**
 * Génère un ensemble de boutons d'action avec styles personnalisés et icônes facultatives.
 * Version compatible PHP 7, optimisée pour les boutons externes (form="form-id").
 *
 * Chaque bouton est défini par un label et un tableau de configuration :
 * - 'type' => int (1 = valider, 0 = refuser, 2 = neutre)
 * - 'icon' => (optionnel) classe CSS de l'icône
 * - 'form_id' => string, ID du formulaire ciblé (obligatoire)
 * - 'action' => (optionnel) URL de soumission personnalisée
 * - 'attrs' => (optionnel) autres attributs HTML (ex: data-*)
 *
 * @param array $buttons Liste des boutons à générer.
 * @return string HTML généré.
 */
function renderActionButtons(array $buttons)
{
    $html = '';

    $typeClasses = [
        1 => 'positive',
        0 => 'negative',
        2 => 'neutral'
    ];

    foreach ($buttons as $label => $config) {
        if (!isset($config['type'], $config['form_id'])) {
            continue; // éléments obligatoires
        }

        $type = $config['type'];
        $class = isset($typeClasses[$type]) ? $typeClasses[$type] : 'neutral';
        $icon = !empty($config['icon']) ? '<i class="' . htmlspecialchars($config['icon']) . '"></i> ' : '';
        $formId = htmlspecialchars($config['form_id']);
        $actionValue = $type === 1 ? 'true' : ($type === 0 ? 'false' : 'neutral');

        // Attributs HTML sans 'formaction'
        $attrs = ' type="submit" name="action" value="' . $actionValue . '" form="' . $formId . '"';

        if (!empty($config['attrs'])) {
            $attrs .= ' ' . $config['attrs'];
        }

        $html .= '<button class="custom-btn ' . $class . '"' . $attrs . '>' . $icon . htmlspecialchars($label) . '</button>';
    }

    return '<div class="btn-container">' . $html . '</div>';
}

/**
 * Génère des boutons d'action avec icônes Kiduotone
 *
 * @param string $editUrl    URL/action pour l'édition
 * @param string $deleteUrl  URL/action pour la suppression
 * @param mixed  $itemId     ID de l'item (optionnel)
 * @param string $size       Taille des icônes (ex: 'fs-2', 'fs-3')
 * @param string $spacing    Espacement entre boutons (ex: 'me-3')
 * @return string            HTML des boutons
 */

function renderSmallActionButtons($url, $itemId = null)
{
    static $cssLoaded = false;
    $css = '';

    if (!$cssLoaded) {
        $css = '<style>
            .compact-action-buttons{display:flex;gap:12px;justify-content:center;align-items:center;padding:8px 0;}
            .compact-action-form{margin:0;padding:0;display:inline-block;}
            .compact-action-btn{border:none;border-radius:8px;width:36px;height:36px;cursor:pointer;display:flex;justify-content:center;align-items:center;transition:.2s;box-shadow:0 2px 5px rgba(0,0,0,.15);position:relative;background:transparent;}
            .compact-action-btn .ki-duotone{font-size:16px;color:#fff!important;}
            .compact-action-btn .path1,.compact-action-btn .path2{fill:#fff!important;}
            .compact-edit-btn{background:linear-gradient(135deg,#3498db,#2980b9);}
            .compact-edit-btn:hover{background:linear-gradient(135deg,#2980b9,#3498db);transform:scale(1.1);box-shadow:0 4px 8px rgba(52,152,219,.3);}
            .compact-delete-btn{background:linear-gradient(135deg,#e74c3c,#c0392b);}
            .compact-delete-btn:hover{background:linear-gradient(135deg,#c0392b,#e74c3c);transform:scale(1.1);box-shadow:0 4px 8px rgba(231,76,60,.3);}
            .compact-action-btn[title]:hover::after{content:attr(title);position:absolute;bottom:-30px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:4px 8px;border-radius:4px;font-size:12px;white-space:nowrap;z-index:10;}
            .custom-dialog-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000;}
            .custom-dialog{background:#fff;border-radius:12px;padding:24px;max-width:400px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,.2);animation:slideIn .3s ease-out;}
            .custom-dialog h3{margin:0 0 16px;font-size:20px;color:#2c3e50;}
            .custom-dialog p{margin:0 0 24px;color:#666;}
            .custom-dialog-buttons{display:flex;gap:12px;justify-content:flex-end;}
            .custom-dialog-btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:600;transition:.2s;}
            .custom-dialog-cancel{background:#ecf0f1;color:#2c3e50;}
            .custom-dialog-cancel:hover{background:#dfe4ea;}
            .custom-dialog-confirm{background:#e74c3c;color:#fff;}
            .custom-dialog-confirm:hover{background:#c0392b;}
            @keyframes slideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
        </style>';
        $cssLoaded = true;
    }

    $idField      = $itemId ? '<input type="hidden" name="trainingID" value="'.htmlspecialchars($itemId).'">' : '';
    $editFormId   = 'edit-form-'.($itemId ?: uniqid());
    $deleteFormId = 'delete-form-'.($itemId ?: uniqid());

    return $css.'
    <div id="custom-delete-dialog" class="custom-dialog-overlay">
        <div class="custom-dialog">
            <h3>Confirmer la suppression</h3>
            <p>Êtes-vous sûr de vouloir supprimer cet élément ?</p>
            <div class="custom-dialog-buttons">
                <button type="button" class="custom-dialog-btn custom-dialog-cancel">Annuler</button>
                <button type="button" class="custom-dialog-btn custom-dialog-confirm">Supprimer</button>
            </div>
        </div>
    </div>
    <div class="compact-action-buttons">
        <!-- Éditer -->
        <form id="'.$editFormId.'" class="compact-action-form ajax-form" action="'.htmlspecialchars($url).'" method="GET">
            '.$idField.'
            <button type="submit" form="'.$editFormId.'" class="compact-action-btn compact-edit-btn" title="Éditer">
                <span class="ki-duotone ki-pencil"><span class="path1"></span><span class="path2"></span></span>
            </button>
        </form>
        <!-- Supprimer -->
        <form id="'.$deleteFormId.'" class="compact-action-form ajax-form" action="'.htmlspecialchars($url).'" method="POST">
            '.$idField.'
            <input type="hidden" name="_method" value="DELETE">
            <button type="button" class="compact-action-btn compact-delete-btn" title="Supprimer" data-form-id="'.$deleteFormId.'">
                <span class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></span>
            </button>
        </form>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const container = document.getElementById("trainings-table");
            if (!container) return;

            container.addEventListener("click", async (e) => {
                const button = e.target.closest(".compact-delete-btn");
                if (!button) return;

                e.preventDefault();
                const dialog = document.getElementById("custom-delete-dialog");
                if (!dialog) return;

                dialog.style.display = "flex";

                const cancelButton = dialog.querySelector(".custom-dialog-cancel");
                const confirmButton = dialog.querySelector(".custom-dialog-confirm");
                const newCancelButton = cancelButton.cloneNode(true);
                const newConfirmButton = confirmButton.cloneNode(true);
                cancelButton.replaceWith(newCancelButton);
                confirmButton.replaceWith(newConfirmButton);

                newCancelButton.addEventListener("click", () => {
                    dialog.style.display = "none";
                });

                newConfirmButton.addEventListener("click", async () => {
                    dialog.style.display = "none";
                    const formId = button.getAttribute("data-form-id");
                    const form = document.getElementById(formId);
                    if (!form) return;

                    try {
                        const response = await handleAjaxFormSubmit(form);
                        const data = await response.json();

                        if (data.success) {
                            uiManager.showToast(data.message || "Suppression réussie !", "success");
                            ajaxManager.reloadFilteredContent("trainings-table");
                        } else {
                            uiManager.showToast(data.message || "Erreur lors de la suppression", "error");
                        }
                    } catch (err) {
                        uiManager.showToast("Erreur réseau : " + err.message, "error");
                    }
                });
            });
        });
    </script>';
}
?>
