<?php
// ============================================================
//  includes/demandes_champs.php — Config des champs par type de demande
//  Porté depuis CHAMPS_CONFIG (frontend React de emu-demandes).
//  Un seul moteur de rendu générique lit cette config.
//  (Phase 1 : Autorisation d'absence. Les autres types s'ajoutent ici.)
// ============================================================

function di_champs_config(): array {
    return [
        // Type de permission en premier : il porte le nombre de jours autorisés (entre parenthèses).
        // Choisir un type + saisir « Du » remplit automatiquement jours, « Au » et la reprise (JS
        // dans demandes_new.php). « — Aucune — » = saisie manuelle libre (congé/permission simple).
        'autorisation_absence' => [
            ['key'=>'agent_nom',     'label'=>"Nom & Prénoms de l'agent", 'type'=>'agent', 'required'=>true],
            ['key'=>'agent_email',   'label'=>"Email de l'agent",         'type'=>'email'],
            ['key'=>'agent_fonction','label'=>"Fonction de l'agent",      'type'=>'text'],
            ['key'=>'type_permission','label'=>'Type de permission', 'type'=>'select', 'span'=>true,
             'options'=>[
                '' => '— Aucune (saisie manuelle) —',
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
            ['key'=>'date_debut',    'label'=>'Du (date de départ)',       'type'=>'date',   'required'=>true],
            ['key'=>'nb_jours',      'label'=>'Nombre de jours demandés',  'type'=>'number', 'required'=>true],
            ['key'=>'date_fin',      'label'=>'Au (inclus)',               'type'=>'date',   'required'=>true],
            ['key'=>'date_reprise',  'label'=>'Date de reprise du service','type'=>'date',   'required'=>true],
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
        'basculement_acces' => [
            ['key'=>'agent_nom',     'label'=>"Nom & Prénoms de l'agent", 'type'=>'agent', 'required'=>true],
            ['key'=>'agent_email',   'label'=>"Email de l'agent",         'type'=>'email'],
            ['key'=>'agent_fonction','label'=>"Fonction de l'agent",      'type'=>'text'],
            ['key'=>'periode_acces', 'label'=>"Période d'accès",          'type'=>'daterange', 'span'=>true],
            ['key'=>'site_origine',  'label'=>"Site d'origine",           'type'=>'site',  'required'=>true],
            ['key'=>'nouveau_site',  'label'=>'Nouveau site',             'type'=>'site',  'required'=>true],
            ['key'=>'ancien_role',   'label'=>'Ancien rôle',              'type'=>'text',  'required'=>true],
            ['key'=>'nouveau_role',  'label'=>'Nouveau rôle',             'type'=>'text',  'required'=>true],
            ['key'=>'plateformes',   'label'=>'Accès aux plateformes',    'type'=>'plateformes', 'span'=>true],
        ],
        'basculement_compte' => [
            ['key'=>'agent_nom',      'label'=>"Nom & Prénoms de l'agent", 'type'=>'agent', 'required'=>true],
            ['key'=>'agent_email',    'label'=>"Email de l'agent",         'type'=>'email'],
            ['key'=>'agent_fonction', 'label'=>"Fonction de l'agent",      'type'=>'text'],
            ['key'=>'agent_matricule','label'=>"Matricule de l'agent",    'type'=>'text'],
            ['key'=>'site',           'label'=>'Site',                    'type'=>'text', 'required'=>true],
            ['key'=>'ancien_poste',   'label'=>'Ancien poste',            'type'=>'text', 'required'=>true],
            ['key'=>'nouveau_poste',  'label'=>'Nouveau poste',           'type'=>'text', 'required'=>true],
            ['key'=>'login',          'label'=>"Nom d'utilisateur / Login",'type'=>'text'],
            ['key'=>'email_pro',      'label'=>'Adresse email professionnelle','type'=>'email'],
            ['key'=>'applications',   'label'=>'Application(s) concernée(s)','type'=>'text', 'span'=>true],
            ['key'=>'profil',         'label'=>'Profil',                  'type'=>'text'],
            ['key'=>'date_demande',   'label'=>'Date de la demande',      'type'=>'date'],
            ['key'=>'motif',          'label'=>'Motif du basculement',    'type'=>'select', 'required'=>true, 'span'=>true,
             'options'=>['' =>'— Choisir —', 'Mutation interne'=>'Mutation interne', 'Renfort temporaire'=>'Renfort temporaire',
                'Réorganisation des équipes'=>'Réorganisation des équipes', 'Autre'=>'Autre']],
        ],
        'transfert_agent' => [
            ['key'=>'agent_nom',     'label'=>"Nom & Prénoms de l'agent", 'type'=>'agent', 'required'=>true],
            ['key'=>'agent_email',   'label'=>"Email de l'agent",         'type'=>'email'],
            ['key'=>'agent_fonction','label'=>"Fonction de l'agent",      'type'=>'text'],
            ['key'=>'site_origine',  'label'=>"Site d'origine",           'type'=>'text', 'required'=>true],
            ['key'=>'nouveau_site',  'label'=>'Nouveau site',             'type'=>'text', 'required'=>true],
            ['key'=>'motif',         'label'=>'Motif',                    'type'=>'textarea', 'required'=>true, 'span'=>true],
            ['key'=>'type_transfert','label'=>'Type de transfert',        'type'=>'select', 'required'=>true,
             'options'=>['' =>'— Choisir —', 'Temporaire'=>'Temporaire', 'Définitif'=>'Définitif']],
            ['key'=>'duree',         'label'=>'Durée (si temporaire)',    'type'=>'text'],
            ['key'=>'date_effet',    'label'=>"Date de prise d'effet",    'type'=>'date', 'required'=>true],
        ],
        'creation_site' => [
            ['key'=>'nom_site',    'label'=>'Nom du site',   'type'=>'text', 'required'=>true],
            ['key'=>'localisation','label'=>'Localisation',  'type'=>'text', 'required'=>true],
            ['key'=>'usage',       'label'=>'Usage',         'type'=>'select',
             'options'=>['' =>'— Choisir —', 'POSE'=>'POSE', 'SAISIE'=>'SAISIE', 'POSE et SAISIE'=>'POSE et SAISIE']],
            ['key'=>'operation',   'label'=>'Opération',     'type'=>'text'],
            ['key'=>'duree',       'label'=>'Durée',         'type'=>'text'],
            ['key'=>'timeout',     'label'=>'Timeout',       'type'=>'text'],
            ['key'=>'longitude',   'label'=>'Longitude',     'type'=>'text'],
            ['key'=>'latitude',    'label'=>'Latitude',      'type'=>'text'],
            ['key'=>'date_demande','label'=>'Date de la demande','type'=>'date'],
        ],
        'imputation_courrier' => [
            ['key'=>'reference',      'label'=>'Référence du courrier', 'type'=>'text'],
            ['key'=>'date_reception', 'label'=>'Date de réception',     'type'=>'date'],
            ['key'=>'expediteur',     'label'=>'Expéditeur',            'type'=>'text', 'required'=>true],
            ['key'=>'objet',          'label'=>'Objet du courrier',     'type'=>'text', 'required'=>true, 'span'=>true],
            ['key'=>'resume',         'label'=>'Résumé du contenu',     'type'=>'textarea', 'span'=>true],
            ['key'=>'service_impute', 'label'=>'Service / Personne imputé(e)', 'type'=>'text', 'required'=>true, 'span'=>true],
            ['key'=>'motif_imputation','label'=>"Motif de l'imputation",'type'=>'text', 'span'=>true],
            ['key'=>'priorite',       'label'=>'Niveau de priorité',    'type'=>'select',
             'options'=>['' =>'— Choisir —', 'Urgent'=>'Urgent', 'Normal'=>'Normal', 'Faible'=>'Faible']],
            ['key'=>'type_traitement','label'=>'Type de traitement attendu','type'=>'select',
             'options'=>['' =>'— Choisir —', 'Réponse'=>'Réponse', 'Analyse'=>'Analyse', 'Classement'=>'Classement', 'Suivi'=>'Suivi', 'Autre'=>'Autre']],
            ['key'=>'echeance',       'label'=>'Échéance de traitement', 'type'=>'date'],
        ],
        'exceptionnel' => [
            ['key'=>'agent_nom',     'label'=>"Nom & Prénoms de l'agent", 'type'=>'agent', 'required'=>true],
            ['key'=>'agent_email',   'label'=>"Email de l'agent",         'type'=>'email'],
            ['key'=>'agent_fonction','label'=>"Fonction de l'agent",      'type'=>'text'],
            ['key'=>'date_souhaitee','label'=>'Date souhaitée',       'type'=>'date'],
            ['key'=>'objet',         'label'=>'Objet de la demande',  'type'=>'text', 'required'=>true],
            ['key'=>'motif',         'label'=>'Motif (obligatoire)',  'type'=>'textarea', 'required'=>true, 'span'=>true],
        ],
        'changement_geolocalisation' => [
            ['key'=>'ancien_nom_site',      'label'=>'Site actuel',        'type'=>'select_site', 'required'=>true],
            ['key'=>'nouveau_nom_site',     'label'=>'Nouveau site',       'type'=>'select_site'],
            ['key'=>'ancienne_localisation','label'=>'Adresse actuelle',   'type'=>'text'],
            ['key'=>'nouvelle_localisation','label'=>'Nouvelle adresse',   'type'=>'text'],
            ['key'=>'ancienne_longitude',   'label'=>'Longitude actuelle', 'type'=>'text'],
            ['key'=>'ancienne_latitude',    'label'=>'Latitude actuelle',  'type'=>'text'],
            ['key'=>'nouvelle_longitude',   'label'=>'Nouvelle longitude', 'type'=>'text'],
            ['key'=>'nouvelle_latitude',    'label'=>'Nouvelle latitude',  'type'=>'text'],
            ['key'=>'date_effet',           'label'=>"Date d'effet",       'type'=>'date'],
            ['key'=>'motif',                'label'=>'Motif du changement','type'=>'select',
             'options'=>['' =>'— Choisir —', 'Erreur de saisie'=>'Erreur de saisie', 'Déménagement'=>'Déménagement', 'Réorganisation'=>'Réorganisation', 'Autre'=>'Autre']],
            ['key'=>'commentaires',         'label'=>'Commentaires',       'type'=>'textarea', 'span'=>true],
        ],
    ];
}

// Formulaire générique par défaut (types créés via l'admin, sans config dédiée)
function di_champs_defaut(): array {
    return [
        ['key'=>'objet',   'label'=>'Objet de la demande', 'type'=>'text',     'required'=>true],
        ['key'=>'details', 'label'=>'Détails',             'type'=>'textarea', 'required'=>true, 'span'=>true],
    ];
}

// Champs d'un type (formulaire générique par défaut si pas de config dédiée)
function di_champs_of(string $typeCode): array {
    return di_champs_config()[$typeCode] ?? di_champs_defaut();
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
    } elseif ($type === 'select_site') {
        echo "<select id=\"f_$key\" name=\"champs[$key]\"$reqA><option value=\"\">— Choisir un site —</option>";
        foreach (db_fetch_all("SELECT nom FROM sites WHERE actif=1 ORDER BY nom") as $s) {
            $sel = ((string)$value === (string)$s['nom']) ? ' selected' : '';
            echo '<option value="'.h($s['nom']).'"'.$sel.'>'.h($s['nom']).'</option>';
        }
        echo "</select>";
    } elseif ($type === 'plateformes') {
        $sel = is_array($value) ? $value : [];
        echo '<div class="di-plats">';
        foreach (di_plateformes() as $p) {
            $ck = in_array($p['code'], $sel, true) ? ' checked' : '';
            echo '<label class="di-plat">'
               . '<input type="checkbox" name="champs['.$key.'][]" value="'.h($p['code']).'"'.$ck.'>'
               . h($p['label']).'</label>';
        }
        echo '</div>';
    } elseif ($type === 'agent') {
        // Liste déroulante recherchable des agents (users actifs) ; alimente email + fonction (rôle ERP)
        echo "<input type=\"text\" id=\"f_$key\" name=\"champs[$key]\" value=\"$v\" list=\"dl_$key\" autocomplete=\"off\" data-agentfill=\"1\"$reqA>";
        echo "<datalist id=\"dl_$key\">";
        foreach (db_fetch_all("SELECT u.prenom, u.nom, u.email, r.nom AS fonction FROM users u JOIN roles r ON r.id=u.role_id WHERE u.actif=1 ORDER BY u.nom, u.prenom") as $u) {
            $full = h(trim(($u['prenom'] ?? '').' '.($u['nom'] ?? '')));
            echo '<option value="'.$full.'" data-email="'.h((string)($u['email'] ?? '')).'" data-fonction="'.h((string)($u['fonction'] ?? '')).'"></option>';
        }
        echo "</datalist>";
    } elseif ($type === 'site') {
        // Liste déroulante recherchable des sites actifs (saisie libre autorisée)
        echo "<input type=\"text\" id=\"f_$key\" name=\"champs[$key]\" value=\"$v\" list=\"dl_$key\" autocomplete=\"off\"$reqA>";
        echo "<datalist id=\"dl_$key\">";
        foreach (db_fetch_all("SELECT nom FROM sites WHERE actif=1 ORDER BY nom") as $s) {
            echo '<option value="'.h($s['nom']).'"></option>';
        }
        echo "</datalist>";
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
