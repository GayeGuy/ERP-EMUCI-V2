<?php
// ============================================================
//  includes/helpers.php — Fonctions utilitaires
// ============================================================
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function fmt_date(?string $date, string $format = 'd/m/Y'): string {
    if (!$date || $date === '0000-00-00') return '—';
    return date($format, strtotime($date));
}
function fmt_datetime(?string $dt): string {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '—';
    return date('d/m/Y à H:i', strtotime($dt));
}
function fmt_number(float $n, int $decimals = 0): string {
    return number_format($n, $decimals, ',', ' ');
}
function calc_fin_cycle(?string $date_mise_en_service, ?int $duree_vie_mois): ?string {
    if (!$date_mise_en_service || !$duree_vie_mois) return null;
    $d = new \DateTime($date_mise_en_service);
    $d->add(new \DateInterval("P{$duree_vie_mois}M"));
    return $d->format('Y-m-d');
}
function fin_cycle_class(?string $date_fin): string {
    if (!$date_fin) return '';
    $past = strtotime($date_fin) < time();
    if ($past) return 'text-danger fw-bold';
    $diff = (int)round((strtotime($date_fin) - time()) / 86400);
    if ($diff <= 30) return 'text-warning fw-bold';
    return 'text-success';
}
function validate_input(array $fields, array $source = []): array {
    if (empty($source)) $source = $_POST;
    $out = []; $errors = [];
    foreach ($fields as $key => $type) {
        $val = $source[$key] ?? null;
        switch ($type) {
            case 'string': $out[$key] = trim((string)$val); break;
            case 'int':
                if (!is_numeric($val)) { $errors[] = "Champ $key invalide."; break; }
                $out[$key] = (int)$val; break;
            case 'float':
                if (!is_numeric($val)) { $errors[] = "Champ $key invalide."; break; }
                $out[$key] = (float)$val; break;
            case 'email':
                $val = strtolower(trim((string)$val));
                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) { $errors[] = "Email $key invalide."; break; }
                $out[$key] = $val; break;
            case 'nullable_int':
                $out[$key] = ($val === '' || $val === null) ? null : (int)$val; break;
        }
    }
    return ['data' => $out, 'errors' => $errors, 'valid' => empty($errors)];
}
/**
 * Génère la pagination HTML.
 * Usage : <?= pagination($total, $per_page, $page, 'page.php?q=foo') ?>
 */
function pagination(int $total, int $per_page, int $current, string $base_url): string {
    if ($per_page <= 0 || $total <= $per_page) return '';

    $pages     = (int)ceil($total / $per_page);
    $separator = str_contains($base_url, '?') ? '&' : '?';

    $html  = '<div style="display:flex;align-items:center;justify-content:space-between;';
    $html .= 'padding:12px 18px;border-top:1px solid var(--border);font-size:13px;color:var(--muted)">';
    $html .= '<span>' . number_format($total, 0, ',', ' ') . ' résultat' . ($total > 1 ? 's' : '') . '</span>';
    $html .= '<div style="display:flex;gap:4px;align-items:center">';

    $btn = static function(string $label, int $p, bool $active, bool $disabled) use ($base_url, $separator): string {
        $style  = 'padding:6px 11px;border-radius:8px;border:1.5px solid var(--border);';
        $style .= 'font-size:13px;font-family:inherit;cursor:pointer;text-decoration:none;';
        if ($disabled) {
            $style .= 'opacity:.35;pointer-events:none;background:white;color:var(--muted)';
            return "<span style=\"$style\">$label</span>";
        }
        if ($active) {
            $style .= 'background:var(--navy);color:white;border-color:var(--navy);font-weight:700';
        } else {
            $style .= 'background:white;color:var(--navy)';
        }
        $href = $base_url . $separator . 'page=' . $p;
        return "<a href=\"" . htmlspecialchars($href, ENT_QUOTES) . "\" style=\"$style\">$label</a>";
    };

    $html .= $btn('‹', $current - 1, false, $current <= 1);

    $range = 2;
    for ($p = 1; $p <= $pages; $p++) {
        if ($p === 1 || $p === $pages || ($p >= $current - $range && $p <= $current + $range)) {
            $html .= $btn((string)$p, $p, $p === $current, false);
        } elseif ($p === $current - $range - 1 || $p === $current + $range + 1) {
            $html .= '<span style="padding:6px 4px;color:var(--muted)">…</span>';
        }
    }

    $html .= $btn('›', $current + 1, false, $current >= $pages);
    $html .= '</div></div>';
    return $html;
}

// Retourne une balise <img> avec le logo EMUCI encodé en base64 pour les PDFs Dompdf
function pdf_logo_img(string $height = '44px'): string {
    $path = __DIR__ . '/../assets/logo.jpeg';
    if (!file_exists($path)) return '';
    $b64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($path));
    return "<img src=\"$b64\" style=\"height:$height;max-width:120px;object-fit:contain;display:block\">";
}

// json_response définie dans session.php
