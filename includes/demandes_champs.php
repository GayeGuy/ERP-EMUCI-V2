<?php
// ============================================================
//  includes/demandes_champs.php — Config des champs par type de demande
//  Porté depuis CHAMPS_CONFIG (frontend React de emu-demandes).
//  Un seul moteur de rendu générique lit cette config.
//  (Phase 1 : Autorisation d'absence. Les autres types s'ajoutent ici.)
// ============================================================

function di_champs_config(): array {
    return [
        'autorisation_absence' => [
            ['key'=>'nb_jours',      'label'=>'Nombre de jours demandés', 'type'=>'number', 'required'=>true],
            ['key'=>'date_debut',    'label'=>'Du',                        'type'=>'date',   'required'=>true],
            ['key'=>'date_fin',      'label'=>'Au (inclus)',               'type'=>'date',   'required'=>true],
            ['key'=>'date_reprise',  'label'=>'Date de reprise du service','type'=>'date',   'required'=>true],
            ['key'=>'motif',         'label'=>'Motif',                     'type'=>'textarea','required'=>true, 'span'=>true],
            ['key'=>'type_permission','label'=>'Type de permission exceptionnelle', 'type'=>'select', 'span'=>true,
             'options'=>[
                '' => '— Aucune —',
                'Mariage du travailleur (4j)'      => 'Mariage du travailleur (4j)',
                "Mariage d'un enfant (2j)"         => "Mariage d'un enfant (2j)",
                'Mariage frère/sœur (2j)'          => 'Mariage frère/sœur (2j)',
                'Décès conjoint (5j)'              => 'Décès conjoint (5j)',
                'Décès enfant/père/mère (5j)'      => 'Décès enfant/père/mère (5j)',
                'Décès beau-père/belle-mère (2j)'  => 'Décès beau-père/belle-mère (2j)',
                "Naissance d'un enfant (2j)"       => "Naissance d'un enfant (2j)",
                "Baptême d'un enfant (2j)"         => "Baptême d'un enfant (2j)",
                'Première communion (1j)'          => 'Première communion (1j)',
                'Déménagement (1j)'                => 'Déménagement (1j)',
             ]],
        ],
        // Phase 2 : creation_acces, basculement_acces, basculement_compte,
        // transfert_agent, creation_site, changement_geolocalisation,
        // imputation_courrier, exceptionnel.
    ];
}

// Champs d'un type (array vide si non encore porté)
function di_champs_of(string $typeCode): array {
    return di_champs_config()[$typeCode] ?? [];
}

// Rendu HTML d'un champ de formulaire (moteur générique)
function di_render_field(array $f, $value = ''): string {
    $key   = h($f['key']);
    $label = h($f['label']);
    $req   = !empty($f['required']) ? ' <span style="color:#e74c3c">*</span>' : '';
    $reqA  = !empty($f['required']) ? ' required' : '';
    $span  = !empty($f['span']) ? ' style="grid-column:1/-1"' : '';
    $type  = $f['type'] ?? 'text';
    $v     = h((string)$value);

    ob_start();
    echo "<div class=\"di-field\"$span><label for=\"f_$key\">$label$req</label>";
    if ($type === 'textarea') {
        echo "<textarea id=\"f_$key\" name=\"champs[$key]\" rows=\"3\"$reqA>$v</textarea>";
    } elseif ($type === 'select') {
        echo "<select id=\"f_$key\" name=\"champs[$key]\"$reqA>";
        foreach (($f['options'] ?? []) as $ov => $ol) {
            $sel = ((string)$value === (string)$ov) ? ' selected' : '';
            echo '<option value="'.h((string)$ov).'"'.$sel.'>'.h((string)$ol).'</option>';
        }
        echo "</select>";
    } else {
        $t = in_array($type, ['number','date','email']) ? $type : 'text';
        echo "<input type=\"$t\" id=\"f_$key\" name=\"champs[$key]\" value=\"$v\"$reqA>";
    }
    echo "</div>";
    return ob_get_clean();
}
