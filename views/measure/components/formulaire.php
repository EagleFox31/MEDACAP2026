<?php
function generate_dynamic_form(array $config, bool $canClose = false): string {
    // Libellés date (à adapter selon ta fonction lang)
    $startDateLabel = function_exists('lang') ? lang('start_date') : 'Start Date';
    $endDateLabel = function_exists('lang') ? lang('end_date') : 'End Date';

    // Générer un ID de formulaire unique si absent
    $formId = $config['id'] ?? 'form-' . bin2hex(random_bytes(5));

    // Vérifier présence champ fichier
    $hasFileField = false;
    foreach ($config['sections'] ?? [] as $section) {
        foreach ($section['fields'] ?? [] as $field) {
            if (($field['type'] ?? '') === 'file') {
                $hasFileField = true;
                break 2;
            }
        }
    }

    // CSS complet
    $css = <<<CSS
.scrollable-form-container {
    height: 100%;
    border-radius: 15px;
    padding: 20px;
    overflow-y: auto;
}
.form-header {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}
.form-header img {
    max-width: 150px;
    height: auto;
    margin-bottom: 10px;
}
.form-section {
    margin-bottom: 40px;
}
.form-section-title {
    font-size: 20px;
    font-weight: 600;
    color: #34495e;
    border-left: 4px solid #0d6efd;
    padding-left: 12px;
    margin-bottom: 20px;
}
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}
.form-col {
    flex: 1 1 calc(50% - 10px);
    min-width: 250px;
}
.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}
.form-control,
.form-select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ccc;
    border-radius: 8px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    background: #f9f9f9;
}
.form-control:focus,
.form-select:focus {
    border-color: #0d6efd;
    outline: none;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
}
.form-actions {
    text-align: right;
    margin-top: 20px;
}
.date-pairs-flex-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}
.date-pair-container {
    position: relative;
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    margin-bottom: 15px;
    background-color: #fafafa;
    transition: all 0.3s ease;
    flex: 1 1 calc(50% - 15px);
    min-width: 300px;
}
.date-pair-container.fade-in {
    animation: fadeIn 0.4s ease-in-out;
}
.remove-date {
    position: absolute;
    top: 8px;
    right: 12px;
    width: 24px;
    height: 24px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 24px;
    color: #dc3545;
    line-height: 1;
    font-weight: bold;
    transition: color 0.3s ease;
}
.remove-date:hover {
    color: #a71d2a;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 768px) {
    .form-col {
        flex: 1 1 100%;
    }
    .date-pair-container {
        flex: 1 1 100%;
    }
}
#trainings-informations {
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 0;
}
.modern-file-upload {
    width: 100%;
    min-height: 160px;
    border: 2px dashed #ccc;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px;
    background: #fafafa;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    text-align: center;
}
.modern-file-upload:hover {
    border-color: #2563eb;
    background-color: #f0f4ff;
}
.modern-file-upload input[type="file"] {
    display: none;
}
.file-icon {
    font-size: 48px;
    color: #2563eb;
    transition: transform 0.3s ease;
}
.modern-file-upload:hover .file-icon {
    animation: bounce 0.6s ease;
}
.file-text {
    font-size: 14px;
    color: #666;
}
.file-name {
    font-size: 14px;
    font-weight: bold;
    color: #333;
    margin-top: 5px;
    word-break: break-word;
    min-height: 20px;
}
.file-loading-spinner {
    display: none;
    margin-top: 8px;
    width: 24px;
    height: 24px;
    border: 3px solid #ccc;
    border-top: 3px solid #2563eb;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: auto;
    margin-right: auto;
}
.file-loading-spinner.active {
    display: block;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
@keyframes pulse-border {
    0% { box-shadow: 0 0 0px rgba(37, 99, 235, 0.5); }
    50% { box-shadow: 0 0 15px rgba(37, 99, 235, 0.8); }
    100% { box-shadow: 0 0 0px rgba(37, 99, 235, 0.5); }
}
.modern-file-upload.dragover {
    border-color: #2563eb;
    background-color: #f0f4ff;
    animation: pulse-border 1s infinite ease-in-out;
}
CSS;

    // JS drag & drop fichiers
    $fileJs = <<<JS
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".modern-file-upload").forEach(function(dropZone) {
        let fileInput = dropZone.querySelector("input[type=file]");
        let fileNameDisplay = dropZone.querySelector(".file-name");
        let spinner = dropZone.querySelector(".file-loading-spinner");

        dropZone.addEventListener("click", () => fileInput.click());

        dropZone.addEventListener("dragover", function(e) {
            e.preventDefault();
            dropZone.classList.add("dragover");
        });

        dropZone.addEventListener("dragleave", function() {
            dropZone.classList.remove("dragover");
        });

        dropZone.addEventListener("drop", function(e) {
            e.preventDefault();
            dropZone.classList.remove("dragover");
            spinner.classList.add("active");
            fileInput.files = e.dataTransfer.files;
            setTimeout(() => {
                updateFileName(fileInput, fileNameDisplay, spinner);
            }, 300);
        });

        fileInput.addEventListener("change", function() {
            spinner.classList.add("active");
            setTimeout(() => {
                updateFileName(fileInput, fileNameDisplay, spinner);
            }, 300);
        });

        function updateFileName(input, display, spinner) {
            if (input.files.length > 0) {
                let names = Array.from(input.files).map(f => f.name).join(", ");
                display.textContent = names;
            } else {
                display.textContent = "";
            }
            spinner.classList.remove("active");
        }
    });
});
JS;

    // Construction HTML
    $html = '<style>' . $css . '</style>';
    $html .= '<script>' . $fileJs . '</script>';

    // Header
    $title = strtoupper(htmlspecialchars($config['title'] ?? ''));
    $html .= <<<HTML
<div class="form-header">
    <img src="../../public/images/logo.png" alt="CFAO Mobility Academy" class="img-fluid">
    <h1 class="mb-4">{$title}</h1>
</div>
HTML;

    // Form attributes
    $method = htmlspecialchars($config['method'] ?? 'POST');
    $action = htmlspecialchars($config['action'] ?? '');
    $enctype = $hasFileField ? 'enctype="multipart/form-data"' : '';

    $html .= '<div class="scrollable-form-container">';
    $html .= "<form id=\"{$formId}\" method=\"{$method}\" action=\"{$action}\" {$enctype}>";

    // Parcours des sections
    foreach ($config['sections'] ?? [] as $section) {
        $sectionTitle = htmlspecialchars($section['title'] ?? '');
        $html .= "<div class=\"form-section\">";
        $html .= "<h3 class=\"form-section-title\">{$sectionTitle}</h3>";
        $html .= "<div class=\"form-row\">";

        // Champs
        foreach ($section['fields'] ?? [] as $field) {
            $containerClass = htmlspecialchars($field['container_class'] ?? 'form-col');
            $html .= "<div class=\"{$containerClass}\">";

            // Label
            if (!empty($field['label'])) {
                $requiredClass = !empty($field['required']) ? 'required' : '';
                $labelText = htmlspecialchars($field['label']);
                $html .= "<label class=\"form-label fw-bolder text-dark fs-6 {$requiredClass}\">{$labelText}</label>";
            }

            $type = $field['type'] ?? 'text';

            switch ($type) {
                case 'text':
                case 'date':
                case 'email':
                case 'password':
                case 'hidden':
                    $value = $field['value'] ?? '';
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $name = htmlspecialchars($field['name'] ?? '');
                    $placeholder = htmlspecialchars($field['placeholder'] ?? '');
                    $valueEsc = htmlspecialchars($value);
                    $html .= "<input type=\"{$type}\" class=\"form-control form-control-solid\" name=\"{$name}\" placeholder=\"{$placeholder}\" value=\"{$valueEsc}\">";
                    break;

                case 'select':
                    $name = htmlspecialchars($field['name'] ?? '');
                    $multiple = !empty($field['multiple']) ? 'multiple' : '';
                    $select2 = !empty($field['select2']) ? 'data-control="select2"' : '';
                    $html .= "<select name=\"{$name}\" class=\"form-select form-select-solid fw-bold bg-white\" {$multiple} {$select2}>";
                    if (!empty($field['placeholder'])) {
                        $placeholder = htmlspecialchars($field['placeholder']);
                        $html .= "<option disabled selected>{$placeholder}</option>";
                    }
                    $selectedValues = $field['value'] ?? [];
                    if (!is_array($selectedValues)) {
                        $selectedValues = [$selectedValues];
                    }
                    foreach ($field['options'] ?? [] as $val => $label) {
                        $valEsc = htmlspecialchars($val);
                        $labelEsc = htmlspecialchars($label);
                        $selected = in_array($val, $selectedValues) ? 'selected' : '';
                        $html .= "<option value=\"{$valEsc}\" {$selected}>{$labelEsc}</option>";
                    }
                    $html .= "</select>";
                    break;

                case 'textarea':
                    $name = htmlspecialchars($field['name'] ?? '');
                    $placeholder = htmlspecialchars($field['placeholder'] ?? '');
                    $rows = (int)($field['rows'] ?? 3);
                    $value = htmlspecialchars($field['value'] ?? '');
                    $html .= "<textarea class=\"form-control form-control-solid\" name=\"{$name}\" placeholder=\"{$placeholder}\" rows=\"{$rows}\">{$value}</textarea>";
                    break;

                case 'date_range_repeatable':
                    $html .= '<div class="date-pairs-flex-container" id="date-pairs-container">';
                    $startDates = $field['start_dates'] ?? [];
                    $endDates = $field['end_dates'] ?? [];
                    $hasDates = count($startDates) > 0 && count($endDates) > 0;
                    $count = $hasDates ? count($startDates) : 1;

                    for ($i = 0; $i < $count; $i++) {
                        $startVal = $hasDates ? htmlspecialchars($startDates[$i]) : '';
                        $endVal = $hasDates ? htmlspecialchars($endDates[$i]) : '';
                        $startName = htmlspecialchars($field['start_name'] ?? 'startDate[]');
                        $endName = htmlspecialchars($field['end_name'] ?? 'endDate[]');
                        $startLabel = htmlspecialchars($field['start_label'] ?? $startDateLabel);
                        $endLabel = htmlspecialchars($field['end_label'] ?? $endDateLabel);

                        $html .= <<<HTML
<div class="date-pair-container fade-in">
    <button type="button" class="remove-date" aria-label="Supprimer période" onclick="removeDate(this)">&times;</button>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="required form-label">{$startLabel}</label>
            <input class="form-control" type="date" name="{$startName}" value="{$startVal}">
        </div>
        <div class="col-md-6 mb-3">
            <label class="required form-label">{$endLabel}</label>
            <input class="form-control" type="date" name="{$endName}" value="{$endVal}">
        </div>
    </div>
</div>
HTML;
                    }
                    $addText = htmlspecialchars($field['add_button_text'] ?? 'Ajouter une période');
                    $html .= <<<HTML
</div>
<button type="button" class="btn btn-light-primary btn-sm" onclick="addDate()">
    <i class="ki-duotone ki-plus fs-3 me-1"></i> {$addText}
</button>
HTML;
                    break;

                case 'file':
                    $name = htmlspecialchars($field['name'] ?? '');
                    $multiple = !empty($field['multiple']) ? 'multiple' : '';
                    $accept = !empty($field['accept']) ? 'accept="' . htmlspecialchars($field['accept']) . '"' : '';
                    $hint = htmlspecialchars($field['hint'] ?? '');
                    $html .= <<<HTML
<div class="modern-file-upload">
    <input type="file" name="{$name}" {$multiple} {$accept}>
    <div class="file-icon"><i class="ki-duotone ki-cloud-add"></i></div>
    <div class="file-text">Glissez-déposez vos fichiers ou <strong>cliquez pour parcourir</strong></div>
    <div class="file-name"></div>
    <div class="file-loading-spinner"></div>
    <small class="text-muted">{$hint}</small>
</div>
HTML;
                    break;

                case 'custom':
                    $html .= $field['content'] ?? '';
                    break;

                default:
                    $name = htmlspecialchars($field['name'] ?? '');
                    $html .= "<input type=\"text\" class=\"form-control\" name=\"{$name}\">";
                    break;
            }

            $html .= '</div>'; // close container
        }

        $html .= '</div>'; // close form-row
        $html .= '</div>'; // close form-section
    }

    $html .= '</form>';
    $html .= '</div>'; // close scrollable-form-container

    // Boutons externes avec form=ID
    $submitName = htmlspecialchars($config['submit_name'] ?? 'submit');
    $submitText = htmlspecialchars($config['submit_text'] ?? 'Valider');

    $html .= '<div class="form-actions">';

    // Ajout du paramètre $canClose
    if ($canClose) {
        $html .= <<<HTML
<button type="button" class="btn btn-light me-2" onclick="hideFormContainer(this)">
    <i class="ki-duotone ki-cross fs-3 me-1"></i> Fermer
</button>
HTML;
    } else {
        $html .= <<<HTML
<button type="button" class="btn btn-light me-2" onclick="resetForm(this)">
    <i class="ki-duotone ki-arrow-rotate-left fs-3 me-1"></i> Réinitialiser
</button>
HTML;
    }

    $html .= <<<HTML
<button type="submit" form="{$formId}" name="{$submitName}" class="btn btn-primary">
    <span class="indicator-label">
        <i class="ki-duotone ki-check fs-3 me-1"></i> {$submitText}
    </span>
    <span class="indicator-progress">
        Patientez... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
    </span>
</button>
</div>
HTML;

    // JS de gestion formulaire + dates
    $html .= <<<JS
<script>
function hideFormContainer(button) {
    console.log("Bouton cliqué", button);
    let container = button.closest(".glass-effect");
    console.log("Conteneur trouvé", container);
    if (container) {
        container.classList.add("fade-out");
        setTimeout(() => {
            container.style.display = "none";
        }, 300);
    }
}

function resetForm(button) {
    const form = button.closest(".form-actions").previousElementSibling.querySelector("form");
    if (form) {
        form.reset();
        // Réinitialiser les noms de fichiers affichés
        document.querySelectorAll(".file-name").forEach(el => el.textContent = "");
    }
}

function addDate() {
    const container = document.getElementById("date-pairs-container");
    if (!container) return;
    const newPair = document.createElement("div");
    newPair.classList.add("date-pair-container", "fade-in");
    newPair.innerHTML = `
        <button type="button" class="remove-date" aria-label="Supprimer période" onclick="removeDate(this)">&times;</button>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="required form-label">{$startDateLabel}</label>
                <input class="form-control" type="date" name="startDate[]">
            </div>
            <div class="col-md-6 mb-3">
                <label class="required form-label">{$endDateLabel}</label>
                <input class="form-control" type="date" name="endDate[]">
            </div>
        </div>
    `;
    container.appendChild(newPair);
}

function removeDate(button) {
    const pair = button.closest(".date-pair-container");
    if (!pair) return;
    pair.classList.add("fade-out");
    setTimeout(() => {
        pair.remove();
    }, 300);
}
</script>
JS;

    // JS additionnel user
    if (!empty($config['javascript'])) {
        $html .= '<script>' . $config['javascript'] . '</script>';
    }

    return $html;
}