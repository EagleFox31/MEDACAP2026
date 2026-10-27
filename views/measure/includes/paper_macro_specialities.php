<?php
/**
 * Mapping spécialité → macro-spécialité pour rapports recrutement PL.
 */

const PAPER_MACRO_ELECTRICITE = [
    'Electricité Poids Lourds',
    'Electricité et Electronique',
    'Réseaux de Communication',
    'Assistance à la Conduite',
];

/** @return array<string, list<string>> macro => [specialities] */
function paperMacroSpecialityGroups(): array
{
    return [
        'Électricité' => PAPER_MACRO_ELECTRICITE,
        'Mécanique et maintenance' => [], // complété dynamiquement (toutes les autres spécialités du paper)
    ];
}

/** @param list<string> $paperSpecialities */
function paperBuildMacroMap(array $paperSpecialities): array
{
    $elec = array_flip(PAPER_MACRO_ELECTRICITE);
    $mech = [];
    foreach ($paperSpecialities as $spec) {
        if ($spec === '' || isset($elec[$spec])) {
            continue;
        }
        $mech[] = $spec;
    }
    sort($mech);

    $map = ['Électricité' => PAPER_MACRO_ELECTRICITE];
    if ($mech !== []) {
        $map['Mécanique et maintenance'] = $mech;
    }
    return $map;
}

/** @param array<string, array> $specialtyNotes */
function paperRollupByMacroSpeciality(array $specialtyNotes, array $macroMap): array
{
    $out = [];
    foreach ($macroMap as $macro => $specs) {
        $correct = 0;
        $correctMaitrise = 0;
        $answered = 0;
        $total = 0;
        foreach ($specs as $spec) {
            if (!isset($specialtyNotes[$spec])) {
                continue;
            }
            $sn = $specialtyNotes[$spec];
            $correct += (int) ($sn['correct'] ?? 0);
            $answered += (int) ($sn['answered'] ?? 0);
            $total += (int) ($sn['total'] ?? 0);
            $correctMaitrise += (int) ($sn['correct_maitrise'] ?? $sn['correct'] ?? 0);
        }
        if ($total <= 0) {
            continue;
        }
        $out[$macro] = [
            'correct' => $correct,
            'correct_maitrise' => $correctMaitrise,
            'answered' => $answered,
            'total' => $total,
            'note_maitrise' => paperReportNoteSur20($correctMaitrise, $answered),
            'note_completude' => paperReportNoteSur20($correct, $total),
            'pct_completion' => paperReportPct($answered, $total),
        ];
    }
    return $out;
}
