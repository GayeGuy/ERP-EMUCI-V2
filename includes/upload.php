<?php
// ============================================================
//  includes/upload.php  —  Gestion uploads BL et fiches signées
// ============================================================

define('UPLOAD_DIR',       __DIR__ . '/../uploads/');
define('UPLOAD_BL_DIR',    UPLOAD_DIR . 'bl/');
define('UPLOAD_FICHE_DIR', UPLOAD_DIR . 'fiches/');
define('UPLOAD_FEB_DIR',   UPLOAD_DIR . 'feb/');
define('UPLOAD_VALIDATION_DIR', UPLOAD_DIR . 'validation/');
define('UPLOAD_MAX_SIZE',  10 * 1024 * 1024);  // 10 Mo
define('UPLOAD_URL',       APP_URL . '/uploads/');

/**
 * Types MIME autorisés pour les BL / fiches signées
 */
const UPLOAD_ALLOWED_TYPES = [
    'application/pdf'  => 'pdf',
    'image/jpeg'       => 'jpg',
    'image/jpg'        => 'jpg',
    'image/png'        => 'png',
    'image/webp'       => 'webp',
];

/**
 * S'assure que les dossiers d'upload existent.
 */
function upload_ensure_dirs(): void {
    foreach ([UPLOAD_BL_DIR, UPLOAD_FICHE_DIR, UPLOAD_FEB_DIR, UPLOAD_VALIDATION_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

/**
 * Traite l'upload d'un fichier BL ou fiche signée.
 *
 * @param string $field_name   Nom du champ input file ($_FILES key)
 * @param string $type         'bl', 'fiche' ou 'feb'
 * @param string $prefix       Préfixe du nom de fichier (ex: 'livraison_42')
 * @param bool   $required     Si true, retourne une erreur si aucun fichier
 * @return array ['success'=>bool, 'filename'=>string|null, 'message'=>string]
 */
function upload_document(string $field_name, string $type, string $prefix, bool $required = true): array {
    upload_ensure_dirs();

    $dir = match ($type) {
        'fiche' => UPLOAD_FICHE_DIR,
        'feb'   => UPLOAD_FEB_DIR,
        default => UPLOAD_BL_DIR,
    };

    // Vérifier si un fichier est fourni
    if (empty($_FILES[$field_name]) || $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            $label = match ($type) {
                'fiche' => 'fiche signée',
                'feb'   => 'pièce jointe',
                default => 'bon de livraison',
            };
            return ['success' => false, 'filename' => null,
                    'message' => "Le document $label est obligatoire."];
        }
        return ['success' => true, 'filename' => null, 'message' => ''];
    }

    $file  = $_FILES[$field_name];
    $error = $file['error'];

    if ($error !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur).',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux.',
            UPLOAD_ERR_PARTIAL    => 'Upload incomplet. Réessayez.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque.',
        ];
        return ['success' => false, 'filename' => null,
                'message' => $msgs[$error] ?? 'Erreur upload inconnue.'];
    }

    // Taille
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'filename' => null,
                'message' => 'Fichier trop volumineux (max 10 Mo).'];
    }

    // Type MIME réel (via finfo)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    if (!array_key_exists($mime, UPLOAD_ALLOWED_TYPES)) {
        return ['success' => false, 'filename' => null,
                'message' => 'Format non autorisé. Utilisez PDF, JPG, PNG ou WEBP.'];
    }

    $ext      = UPLOAD_ALLOWED_TYPES[$mime];
    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'filename' => null,
                'message' => 'Impossible de déplacer le fichier. Vérifiez les permissions du dossier uploads/.'];
    }

    return ['success' => true, 'filename' => $filename, 'message' => 'Fichier uploadé avec succès.'];
}

/**
 * Supprime un fichier uploadé (bl ou fiche).
 */
function upload_delete(string $filename, string $type): void {
    $dir = match ($type) {
        'fiche'      => UPLOAD_FICHE_DIR,
        'feb'        => UPLOAD_FEB_DIR,
        'validation' => UPLOAD_VALIDATION_DIR,
        default      => UPLOAD_BL_DIR,
    };
    $path = $dir . basename($filename);
    if (file_exists($path)) {
        unlink($path);
    }
}

/**
 * Retourne l'URL publique d'un fichier uploadé.
 */
function upload_url(string $filename, string $type): string {
    $sub = match ($type) {
        'fiche'      => 'fiches',
        'feb'        => 'feb',
        'validation' => 'validation',
        default      => 'bl',
    };
    return UPLOAD_URL . $sub . '/' . rawurlencode($filename);
}

/**
 * Retourne le HTML d'un lien de téléchargement pour un fichier uploadé.
 */
function upload_link(string $filename, string $type, string $label = 'Voir le document'): string {
    if (!$filename) return '<span style="color:var(--muted);font-size:12px">—</span>';
    $url  = upload_url($filename, $type);
    $icon = str_ends_with(strtolower($filename), '.pdf') ? '<i class="ph ph-file-text" aria-hidden="true"></i>' : '<i class="ph ph-image" aria-hidden="true"></i>';
    return "<a href='$url' target='_blank' style='font-size:12.5px;color:var(--blue-mid,#1a56a0);text-decoration:none'>$icon $label</a>";
}

/**
 * Retourne true si le rôle courant est limité aux équipements informatiques.
 */
function is_maintenance_info(): bool {
    $user = current_user();
    return $user && $user['role_slug'] === 'maintenance_info';
}

/**
 * Retourne true si l'utilisateur est gestionnaire de stock.
 */
function is_gestionnaire_stock(): bool {
    $user = current_user();
    return $user && $user['role_slug'] === 'gestionnaire_stock';
}

/**
 * Retourne true si l'utilisateur est coordinateur de site.
 */
function is_coordinateur_site(): bool {
    $user = current_user();
    return $user && $user['role_slug'] === 'coordinateur_site';
}

/**
 * Retourne true si l'utilisateur est superviseur opération.
 */
function is_superviseur_operation(): bool {
    $user = current_user();
    return $user && $user['role_slug'] === 'superviseur_operation';
}

/**
 * Filtre SQL catégorie équipement pour maintenance_info.
 * Retourne la clause WHERE supplémentaire.
 */
function equip_categorie_filter(string $alias = 'e'): string {
    if (is_maintenance_info()) {
        return " AND {$alias}.categorie = 'informatique'";
    }
    return '';
}
