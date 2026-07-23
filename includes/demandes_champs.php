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
        'creation_acces' => [
            ['key'=>'agent_nom',      'label'=>"Nom & Prénoms de l'agent", 'type'=>'text',  'required'=>true],
            ['key'=>'agent_email',    'label'=>"Email de l'agent",         'type'=>'email'],
            ['key'=>'agent_fonction', 'label'=>"Fonction de l'agent",      'type'=>'text',  'required'=>true],
            ['key'=>'periode_acces',  'label'=>"Période d'accès",          'type'=>'daterange', 'span'=>true],
            ['key'=>'departement',    'label'=>'Département',               'type'=>'text'],
            ['key'=>'responsable_hierarchique','label'=>'Responsable hiérarchique','type'=>'text'],
            ['key'=>'site',           'label'=>'Site',                     'type'=>'text',  'required'=>true],
            ['key'=>'date_demande',   'label'=>'Date de la demande',       'type'=>'date'],
            ['key'=>'plateformes',    'label'=>'Accès aux plateformes',    'type'=>'plateformes', 'span'=>true],
        ],
        // Phase 2 (suite) : basculement_acces, basculement_compte,
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
    } elseif ($type === 'daterange') {
        $vd = is_array($value) ? h((string)($value['debut'] ?? '')) : '';
        $vf = is_array($value) ? h((string)($value['fin'] ?? '')) : '';
        echo "<div style=\"display:flex;gap:10px;align-items:center\">
                <input type=\"date\" name=\"champs[$key][debut]\" value=\"$vd\" style=\"flex:1\">
                <span style=\"color:#7f8c8d\">→</span>
                <input type=\"date\" name=\"champs[$key][fin]\" value=\"$vf\" style=\"flex:1\"></div>";
    } elseif ($type === 'plateformes') {
        $sel = is_array($value) ? $value : [];
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px">';
        foreach (di_plateformes() as $p) {
            $ck = in_array($p['code'], $sel, true) ? ' checked' : '';
            echo '<label style="display:flex;align-items:center;gap:8px;font-weight:500;cursor:pointer">'
               . '<input type="checkbox" name="champs['.$key.'][]" value="'.h($p['code']).'"'.$ck.' style="width:auto">'
               . h($p['label']).'</label>';
        }
        echo '</div>';
    } else {
        $t = in_array($type, ['number','date','email']) ? $type : 'text';
        echo "<input type=\"$t\" id=\"f_$key\" name=\"champs[$key]\" value=\"$v\"$reqA>";
    }
    echo "</div>";
    return ob_get_clean();
}

// ── Affichage lisible d'une valeur de champ (fiche détail + PDF)
function di_display_value(array $f, $value): string {
    $type = $f['type'] ?? 'text';
    if ($type === 'daterange') {
        if (!is_array($value)) return '';
        $d = $value['debut'] ?? ''; $fi = $value['fin'] ?? '';
        return ($d || $fi) ? h("$d → $fi") : '';
    }
    if ($type === 'plateformes') {
        if (!is_array($value) || !$value) return '';
        $labels = [];
        foreach (di_plateformes() as $p) if (in_array($p['code'], $value, true)) $labels[] = $p['label'];
        return h(implode(', ', $labels));
    }
    if ($type === 'select' && isset($f['options'][$value])) return h((string)$f['options'][$value]);
    return h((string)$value);
}

// Une valeur de champ est-elle "vide" (pour masquer les lignes vides) ?
function di_value_empty($value): bool {
    if (is_array($value)) return count(array_filter($value, fn($x) => $x !== '' && $x !== null)) === 0;
    return $value === '' || $value === null;
}
