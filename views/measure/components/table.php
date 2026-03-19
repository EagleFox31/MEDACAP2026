<?php

use App\enums\ValidationStatusEnum;

/**
 * Génère un composant HTML de sous-tableau pour les détails déroulants.
 *
 * @param array $headers En-têtes du sous-tableau
 * @param array $subRows Données des lignes du sous-tableau
 * @param string $trainingId ID de la formation parente
 * @param bool $showCheckboxes Afficher ou non les cases à cocher (défaut: true)
 *
 * @return string HTML du sous-tableau généré
 */
function renderSubTableComponent(array $headers, array $subRows, string $trainingId, bool $showCheckboxes = true): string {
    $nonValidated = array_filter($subRows, function($r) {
        return ($r['validation'] ?? '') === ValidationStatusEnum::EN_ATTENTE;
    });

    ob_start();
    ?>
    <form id="<?= $trainingId ?>" method="POST" action="http://localhost/medacap/public/define/trainings/validations">
        <input type="hidden" name="training_id" value="<?= htmlspecialchars($trainingId) ?>">
        <div class="detail-content">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                <tr style="background-color: #fce4e4; position: sticky; top: 0;">
                    <?php foreach ($headers as $h): ?>
                        <th style="padding: 8px; text-align: left;"><?= htmlspecialchars($h['label']) ?></th>
                    <?php endforeach; ?>

                    <?php if ($showCheckboxes && count($nonValidated) > 0): ?>
                        <th style="width: 50px; text-align: center;">
                            <div class="checkbox-container">
                                <input type="checkbox"
                                       class="custom-checkbox select-all-checkbox"
                                       onclick="event.stopPropagation(); toggleAllCheckboxes(this)"
                                       title="Tout sélectionner">
                            </div>
                        </th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($subRows as $subRow):
                    $techId = $subRow['id'] ?? null;
                    $isWaiting = ($subRow['validation'] ?? '') === ValidationStatusEnum::EN_ATTENTE;
                    $autoCheck = ($isWaiting && count($subRows) === 1) ? 'checked' : '';
                    ?>
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <td style="padding: 8px; text-align: left;">
                                <?= htmlspecialchars($subRow[$h['key']] ?? '') ?>
                            </td>
                        <?php endforeach; ?>

                        <?php if ($showCheckboxes): ?>
                            <td style="text-align: center;">
                                <?php if ($isWaiting): ?>
                                    <div class="checkbox-container">
                                        <input type="checkbox"
                                               class="custom-checkbox"
                                               name="technician_ids[]"
                                               value="<?= $techId ?>"
                                            <?= $autoCheck ?>
                                               onclick="event.stopPropagation(); updateSelectedTechnicians(this, '<?= $techId ?>')"
                                        />
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * Génère un composant HTML de tableau principal
 */
function renderTableComponent(array $headers, array $rows, string $dropDownColumn = null): string {
    ob_start();
    ?>
    <style>
        /* Styles inchangés */
        .table-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 500px;
        }
        .table-header {
            flex-shrink: 0;
        }
        .table-body-container {
            flex-grow: 1;
            overflow-y: auto;
            position: relative;
            border: 1px solid #ddd;
        }
        .table-body {
            margin-top: -1px;
        }
        .main-row:hover {
            background-color: #fce4e4 !important;
        }
        .main-row.active {
            background-color: #fce4e4 !important;
        }
        .detail-content {
            max-height: 0;
            overflow-y: auto;
            transition: max-height 0.3s ease;
            background-color: #f9f9f9;
        }
        .detail-row {
            display: none;
        }
        .detail-row.active {
            display: table-row;
        }
        table td {
            min-width: 100px;
            max-width: 200px;
            word-wrap: break-word;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        td.component-cell {
            max-width: none !important;
            white-space: nowrap !important;
            overflow: visible !important;
            width: auto !important;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .custom-checkbox {
            position: relative;
            width: 24px;
            height: 24px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            border: 2px solid #D3101A;
            border-radius: 6px;
            outline: none;
            transition: all 0.3s;
            cursor: pointer;
            margin: 0 5px;
        }
        .custom-checkbox:hover {
            transform: scale(1.05);
            box-shadow: 0 0 5px rgba(211, 16, 26, 0.3);
        }
        .custom-checkbox:checked {
            background-color: #D3101A;
            border-color: #D3101A;
        }
        .custom-checkbox:checked::after {
            content: "✓";
            position: absolute;
            color: white;
            font-size: 16px;
            font-weight: bold;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .custom-checkbox:disabled {
            border-color: #ccc;
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        .select-all-checkbox {
            margin-right: 5px;
        }
    </style>

    <script>
        // Fonctions JavaScript inchangées
        function updateSelectedTechnicians(checkbox, technicianId) {
            const urlParams = new URLSearchParams(window.location.search);
            let selected = urlParams.getAll('technicians[]');

            if (checkbox.checked) {
                if (!selected.includes(technicianId)) {
                    selected.push(technicianId);
                }
            } else {
                selected = selected.filter(id => id !== technicianId);
            }
        }

        function toggleAllCheckboxes(checkbox) {
            const container = checkbox.closest('.detail-content');
            const checkboxes = container.querySelectorAll('input[type="checkbox"]:not(.select-all-checkbox)');

            checkboxes.forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = checkbox.checked;
                    const technicianId = cb.value;
                    updateSelectedTechnicians(cb, technicianId);
                }
            });
        }

        function toggleDetail(row) {
            const detailRow = row.nextElementSibling;
            const isActive = detailRow.classList.contains('active');

            document.querySelectorAll('.detail-row.active').forEach(el => {
                if (el !== detailRow) {
                    el.classList.remove('active');
                    el.previousElementSibling.classList.remove('active');
                }
            });

            row.classList.toggle('active', !isActive);
            detailRow.classList.toggle('active', !isActive);

            const detailContent = detailRow.querySelector('.detail-content');
            if (!isActive) {
                detailContent.style.maxHeight = detailContent.scrollHeight + 'px';
            } else {
                detailContent.style.maxHeight = '0';
            }
        }
    </script>

    <div class="table-container">
        <div class="table-header">
            <table class="table align-middle table-bordered fs-6 gy-5" style="margin-bottom: 0; width: 100%; table-layout: fixed;">
                <thead style="background-color: white;">
                <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                    <?php foreach ($headers as $header): ?>
                        <th style="text-align: center; vertical-align: middle; min-width: 100px; max-width: 200px;"
                            data-key="<?= htmlspecialchars($header['key']) ?>">
                            <?= htmlspecialchars($header['label']) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                </thead>
            </table>
        </div>

        <div class="table-body-container">
            <table class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer table-body" style="margin-top: 0; width: 100%; table-layout: fixed;">
                <tbody class="fw-semibold text-gray-600" style="text-align: center; vertical-align: middle;">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= count($headers) ?>" class="text-center py-5">
                            <div class="d-flex flex-column align-items-center justify-content-center" style="color: #9e0000;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                </svg>
                                <div class="mt-3 fs-5 fw-semibold">Aucun résultat trouvé.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row):
                        $isExpandable = $dropDownColumn !== null && array_key_exists($dropDownColumn, $row);
                        $attributes = $isExpandable ? 'onclick="toggleDetail(this)" style="cursor: pointer;"' : '';
                        ?>
                        <tr class="main-row" <?= $attributes ?>>
                            <?php foreach ($headers as $header):
                                $key = $header['key'];
                                $type = $header['type'] ?? 'text';
                                $value = $row[$key] ?? '';
                                $tdClass = ($type === 'component') ? 'component-cell' : '';
                                ?>
                                <td class="<?= $tdClass ?>">
                                    <?php if ($type === 'image'): ?>
                                        <img src="<?= htmlspecialchars($value) ?>" style="max-height: 40px; max-width: 120px;" alt="">
                                    <?php elseif ($type === 'select'): ?>
                                        <select class="form-select" onclick="event.stopPropagation();">
                                            <?php foreach ($header['options'] ?? [] as $optVal => $optLabel): ?>
                                                <option value="<?= htmlspecialchars($optVal) ?>" <?= $optVal === $value ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($optLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($type === 'button'): ?>
                                        <button onclick="<?= htmlspecialchars($header['action'] ?? '') ?>; event.stopPropagation();">
                                            <?= htmlspecialchars($value) ?>
                                        </button>
                                    <?php elseif ($type === 'component'): ?>
                                        <div class="flex justify-center items-center w-full" style="white-space: nowrap;">
                                            <?= $value ?>
                                        </div>
                                    <?php else: ?>
                                        <?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <?php if ($isExpandable):
                        $datas = $row[$dropDownColumn];
                        if (is_string($datas)) {
                            $decoded = json_decode($datas, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $datas = $decoded;
                            }
                        }

                        if (isset($datas['headers'], $datas['rows'])):
                            $subRows = is_assoc($datas['rows']) ? [$datas['rows']] : $datas['rows'];
                            ?>
                            <tr class="detail-row">
                                <td colspan="<?= count($headers) ?>" style="padding: 0;">
                                    <?= renderSubTableComponent(
                                        $datas['headers'],
                                        $subRows,
                                        $row['id'],
                                        $datas['subRowAction']
                                    ) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function is_assoc(array $arr): bool {
    return array_keys($arr) !== range(0, count($arr) - 1);
}
