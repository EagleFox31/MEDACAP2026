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
function renderSimpleActionButtons(array $buttons): string
{
    $html = '';

    foreach ($buttons as $label => $config) {
        $mode = $config['mode'] ?? 'submit'; // Mode: submit (par défaut), modal, custom

        $icon = !empty($config['icon']) ? '<i class="' . htmlspecialchars($config['icon']) . '"></i>' : '';
        $attrs = '';

        // Classes par défaut
        $baseClass = 'btn btn-icon ';
        $customClass = $config['class'] ?? 'btn-light-secondary';

        // Construction selon le mode
        if ($mode === 'submit') {
            if (!isset($config['type'], $config['form_id'])) {
                continue;
            }

            $formId = htmlspecialchars($config['form_id']);
            $value = $config['type'] === 1 ? 'true' : ($config['type'] === 0 ? 'false' : 'neutral');
            $attrs .= 'type="submit" name="action" value="' . $value . '" form="' . $formId . '"';

        } elseif ($mode === 'modal') {
            if (!isset($config['target'])) {
                continue;
            }

            $attrs .= 'type="button" data-bs-toggle="modal" data-bs-target="' . htmlspecialchars($config['target']) . '"';

        } elseif ($mode === 'custom') {
            // tu gères tout manuellement via 'attrs'
            $attrs .= 'type="button"';
        }

        // Ajout des attributs supplémentaires personnalisés
        if (!empty($config['attrs'])) {
            $attrs .= ' ' . $config['attrs'];
        }

        // Construction finale du bouton
        $html .= '<button class="' . $baseClass . $customClass . '" ' . $attrs . '>'
            . $icon . '</button>';
    }

    return '<div class="btn-container d-flex gap-2">' . $html . '</div>';
}

