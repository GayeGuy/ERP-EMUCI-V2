<?php
/* templates/dash_style.php — vocabulaire visuel partagé des tableaux de bord.
 *
 * Cartes, tableaux, barres, bandeaux, graphes empilés, pastilles d'état : tout
 * ce dont un bloc de tableau de bord a besoin. À inclure après
 * templates/header.php, sur une page dont le contenu vit dans `<div class="pdg">`.
 *
 * Extrait de pages/pdg_overview.php : c'est la référence de style, et il n'y
 * en a désormais qu'un seul exemplaire pour toutes les vues par profil.
 */
?>
<style>
/* ── Couleur des textes secondaires
   var(--muted) vaut #64748B, soit 4,33:1 sur le fond des cartes comme sur
   celui de la page : sous le seuil AA de 4,5 pour du texte de 10 à 13 px, et
   c'est précisément la taille de tous les libellés secondaires d'ici. Ils
   passent donc à #475569 (7,2:1).

   Les en-têtes de tableau portent !important : templates/header.php impose
   th{color:var(--muted)!important}, qui écrase silencieusement toute couleur
   déclarée dans une page. */

/* ── RESET */
.pdg{max-width:1200px;margin:0 auto;font-size:14px}
.pdg *{box-sizing:border-box}

/* ── TOP BAR */
/* Barre de filtres collante, calée sous celle du site. Le filtre reste
   ainsi atteignable depuis n'importe quel bloc, sans remonter. Le fond
   reprend celui de la page : invisible en haut, opaque dès qu'un contenu
   passe dessous. */
.pdg-topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
  position:sticky;top:var(--topbar-h,64px);z-index:40;
  background:var(--tertiary,#F0F4FF);
  margin:0 -10px 28px;padding:14px 10px 12px;
  border-bottom:1px solid transparent;
  transition:border-color .18s,box-shadow .18s}
.pdg-collee .pdg-topbar{border-bottom-color:var(--border,#e2e8f0);
  box-shadow:0 6px 14px -10px rgba(6,3,58,.35)}
@media(max-width:700px){.pdg-topbar{position:static;margin:0 0 20px;padding:0}}
.pdg-title{font-size:24px;font-weight:900;color:var(--navy,#06033A);letter-spacing:-.5px}
.pdg-sub{font-size:13px;color:#475569;margin-top:2px}
.pdg-controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.alrt-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:20px;font-size:12px;font-weight:700}
.alrt-warn{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.alrt-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.month-inp{padding:7px 11px;border:1.5px solid var(--border,#e2e8f0);border-radius:9px;font-size:13px;
  background:white;outline:none;font-family:inherit;cursor:pointer}
.month-inp:focus{border-color:#1B75BC;box-shadow:0 0 0 3px rgba(27,117,188,.12)}

/* ── Pastilles d'état, partagées par les bandeaux de la page */
.hc-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;margin-top:8px}
.hc-green{background:#d1fae5;color:#065f46}
.hc-orange{background:#fff7ed;color:#c2410c}
.hc-red{background:#fee2e2;color:#991b1b}

/* ── SITE PERFORMANCE SECTION */
.perf-wrap{display:grid;grid-template-columns:1fr 1.6fr;gap:16px;margin-bottom:20px}
@media(max-width:820px){.perf-wrap{grid-template-columns:minmax(0,1fr)}}
.card{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:18px;padding:20px 22px}
.card-ttl{font-size:13px;font-weight:800;color:var(--navy,#06033A);margin:0 0 4px}
.card-sub{font-size:12px;color:#475569;margin-bottom:18px}

/* Sites distribution bar */
.dist-bar{height:8px;border-radius:4px;overflow:hidden;display:flex;margin-bottom:18px}
.dist-seg{height:100%;transition:.3s}

/* Site row */
.site-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border,#f1f5f9)}
.site-row:last-child{border-bottom:none}
.site-av{width:34px;height:34px;border-radius:50%;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;letter-spacing:0}
.site-name{font-size:13px;font-weight:700;color:var(--navy,#06033A)}
.site-sub{font-size:11px;color:#475569}
.site-mini-bar{flex:1;min-width:60px}
.site-mini-fill{height:4px;border-radius:2px;background:currentColor}
.site-pct{font-size:12px;font-weight:800;color:var(--navy,#06033A);min-width:38px;text-align:right}

/* Performance table */
.ptbl{width:100%;border-collapse:collapse}
.ptbl th{font-size:10px;font-weight:700;color:#475569!important;text-transform:uppercase;letter-spacing:.4px;
  padding:9px 10px;border-bottom:2px solid var(--border,#e2e8f0);text-align:right;white-space:nowrap}
.ptbl th:first-child{text-align:left}
.ptbl td{padding:11px 10px;border-bottom:1px solid var(--border,#f1f5f9);font-size:13px;text-align:right;vertical-align:middle}
.ptbl td:first-child{text-align:left}
.ptbl tr:last-child td{border-bottom:none}
.ptbl tr:hover td{background:#fafbff}
.mvh{font-size:12px;font-weight:700;padding:3px 8px;border-radius:8px;display:inline-block}
.mvh-g{background:#d1fae5;color:#065f46}
.mvh-o{background:#fff7ed;color:#c2410c}
.mvh-r{background:#fee2e2;color:#991b1b}
.att-dot{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#c2410c}

/* ── PFW + ÉVOLUTION CÔTE À CÔTE */
.pfw-evol-row{display:flex;gap:14px;align-items:stretch;margin-bottom:20px}
.pfw-evol-row .pfw-card{flex:1.3;min-width:0;margin-bottom:0}
.pfw-evol-row .ch-box{flex:1;min-width:0}
@media(max-width:900px){.pfw-evol-row{flex-direction:column}}

/* ── CHARTS ROW */
.charts-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
@media(max-width:580px){.charts-row{grid-template-columns:minmax(0,1fr)}}
.ch-box{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:18px;padding:20px 22px}
/* Sans min-width:0, une piste de grille ne descend pas sous la taille de
   son contenu : un canvas trop large élargit sa colonne et pousse les
   voisines hors du cadre. Le max-width borne le dégât à la source. */
.charts-row > *{min-width:0}
.ch-box canvas{display:block;max-width:100%}
.pfw-right canvas{display:block;max-width:100%}
.ch-ttl{font-size:13px;font-weight:800;color:var(--navy,#06033A);margin-bottom:4px}
.ch-sub{font-size:11px;color:#475569;margin-bottom:14px}
.donut-wrap{display:flex;align-items:center;justify-content:center;gap:16px;padding:8px 0}
.leg-list{display:flex;flex-direction:column;gap:7px}
.leg-item{display:flex;align-items:center;gap:7px;font-size:11px;color:#475569}
.leg-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.leg-val{margin-left:auto;font-weight:700;color:var(--navy,#06033A);font-size:12px}

/* ── Grille générique de blocs (tableaux de bord par profil)
   Deux colonnes de largeur égale, minmax(0,1fr) pour qu'un tableau large
   n'élargisse pas sa piste. Un bloc « plein » occupe la ligne entière. */
.pdg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}
.pdg-grid > .plein{grid-column:1/-1}
.pdg-grid > *{min-width:0}
@media(max-width:820px){.pdg-grid{grid-template-columns:minmax(0,1fr)}}

/* ── BOTTOM ROW */
.bottom-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
@media(max-width:720px){.bottom-row{grid-template-columns:minmax(0,1fr)}}

/* ── Demandes table */
.dtbl{width:100%;border-collapse:collapse;font-size:12px}
.dtbl th{font-size:10px;font-weight:700;color:#475569!important;text-transform:uppercase;padding:7px 8px;
  border-bottom:2px solid var(--border,#e2e8f0);text-align:left;letter-spacing:.3px}
.dtbl td{padding:9px 8px;border-bottom:1px solid #f1f5f9}
.dtbl tr:last-child td{border-bottom:none}
.d-statut{display:inline-block;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700}
.ds-att{background:#fff7ed;color:#c2410c}
.ds-enc{background:#dbeafe;color:#1e40af}
.kpi-mini{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.kpi-m{background:#f8fafc;border-radius:12px;padding:14px 16px}
.kpi-m-val{font-size:24px;font-weight:900;font-family:'Montserrat',sans-serif;line-height:1}
.kpi-m-lbl{font-size:10px;color:#475569;font-weight:700;text-transform:uppercase;margin-top:4px;letter-spacing:.3px}

/* ── Alertes */
.atbl{width:100%;border-collapse:collapse;font-size:12px}
.atbl th{font-size:10px;font-weight:700;color:#475569!important;text-transform:uppercase;padding:7px 8px;
  border-bottom:2px solid var(--border,#e2e8f0);text-align:left;letter-spacing:.3px}
.atbl td{padding:9px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.atbl tr:last-child td{border-bottom:none}
.type-pill{display:inline-block;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700;background:#fff7ed;color:#c2410c}

/* ── Stock mini row */
.stock-stat-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
.stock-stat-row:last-child{border-bottom:none}
.stock-val{font-weight:800;font-family:'Montserrat',sans-serif;font-size:15px}

/* ── Widget Performance par site */
.pfw-card{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:20px;overflow:hidden;margin-bottom:20px}
.pfw-top{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;gap:10px}
.pfw-site-lbl{font-size:11px;color:#5a6678;letter-spacing:.3px;margin-bottom:3px;text-transform:uppercase;font-weight:700}
.pfw-site-sel{display:flex;align-items:center;gap:6px;cursor:pointer}
.pfw-site-sel select{font-size:16px;font-weight:900;color:#06033A;background:transparent;border:none;outline:none;cursor:pointer;font-family:inherit;appearance:none;-webkit-appearance:none;padding-right:4px}
.pfw-site-arr{font-size:11px;color:#5a6678}
.pfw-quarters{display:flex;gap:5px;align-items:center}
.pfw-q{padding:4px 11px;border-radius:16px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:11px;font-weight:700;color:#5a6678;cursor:pointer;font-family:inherit;transition:.15s;white-space:nowrap}
.pfw-q:hover{border-color:#06033A;color:#06033A}
.pfw-q.active{background:#06033A;color:#fff;border-color:#06033A}
.pfw-legend{display:flex;align-items:center;gap:14px;padding:10px 20px 0;font-size:11px;color:#64748b}
.pfw-leg-i{display:flex;align-items:center;gap:5px;font-weight:600}
.pfw-leg-sq{width:10px;height:10px;border-radius:2px;flex-shrink:0}
.pfw-body{display:grid;grid-template-columns:200px 1fr;min-height:250px}
@media(max-width:700px){.pfw-body{grid-template-columns:minmax(0,1fr)}}
.pfw-left{padding:22px 16px 22px 20px;display:flex;gap:10px;transition:background .35s;border-radius:0 0 0 18px}
.pfw-vert{writing-mode:vertical-rl;transform:rotate(180deg);font-size:10px;font-weight:700;color:rgba(255,255,255,.55);letter-spacing:.8px;text-transform:uppercase;flex-shrink:0;align-self:center}
.pfw-stats{flex:1;display:flex;flex-direction:column;justify-content:center;gap:18px}
.pfw-stat-lbl{font-size:11px;color:rgba(255,255,255,.65);margin-bottom:3px;font-weight:600}
.pfw-stat-val{font-size:21px;font-weight:900;color:#fff;font-family:'Montserrat',sans-serif;line-height:1}
.pfw-right{padding:18px 20px 10px;position:relative;overflow:hidden}
.pfw-empty{display:flex;align-items:center;justify-content:center;height:100%;color:#5a6678;font-size:13px}

/* ══════════ BANDEAU PERFORMANCE BUSINESS ══════════ */
.biz{--biz-muted:#5a6678;display:flex;flex-direction:column;gap:14px;margin-bottom:20px}
.biz-hd{display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap}
.biz-hd-t{font-size:11px;font-weight:700;color:var(--biz-muted);text-transform:uppercase;letter-spacing:.5px}
.biz-hd-s{font-size:12px;color:var(--biz-muted)}

.biz-lead{display:grid;grid-template-columns:1.4fr 1fr;gap:14px}
@media(max-width:900px){.biz-lead{grid-template-columns:minmax(0,1fr)}}

/* Carte de tête : taux de service */
.biz-hero{background:var(--navy,#06033A);border-radius:18px;padding:22px 26px;color:#fff;position:relative;overflow:hidden}
.biz-hero::after{content:'';position:absolute;right:-30px;bottom:-30px;width:150px;height:150px;
  background:rgba(255,255,255,.05);border-radius:50%}
.biz-hero-lbl{font-size:10px;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.5px}
.biz-hero-val{font-size:44px;font-weight:900;letter-spacing:-1.5px;line-height:1;font-family:'Montserrat',sans-serif;
  margin-top:8px;font-variant-numeric:tabular-nums}
.biz-hero-u{font-size:20px;margin-left:3px;color:rgba(255,255,255,.5)}
.biz-hero-note{font-size:12px;color:rgba(255,255,255,.68);margin-top:8px;line-height:1.5;max-width:52ch;position:relative;z-index:1}
.biz-hero-note b{color:#fff;font-weight:700}
/* Barre de composition de la demande */
.biz-dem{display:flex;height:26px;border-radius:7px;overflow:hidden;margin-top:14px;background:rgba(255,255,255,.1);position:relative;z-index:1}
.biz-dem-s{display:flex;align-items:center;padding:0 9px;font-size:11px;font-weight:700;white-space:nowrap;overflow:hidden;
  font-variant-numeric:tabular-nums}
.biz-dem-ok{background:#86efac;color:#052e16}
.biz-dem-ko{background:#fca5a5;color:#450a0a}
.biz-dem-key{display:flex;gap:16px;flex-wrap:wrap;margin-top:10px;font-size:11px;color:rgba(255,255,255,.6);position:relative;z-index:1}
.biz-dem-key b{color:#fff;font-weight:700;font-variant-numeric:tabular-nums}
.biz-dot{display:inline-block;width:8px;height:8px;border-radius:3px;margin-right:5px;vertical-align:middle}

/* Quatre métriques compactes */
.biz-mx{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:580px){.biz-mx{grid-template-columns:minmax(0,1fr)}}
.biz-m{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:18px;padding:16px 18px;display:flex;flex-direction:column}
.biz-m-hd{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;min-height:32px}
.biz-m-lbl{font-size:10px;font-weight:700;color:var(--biz-muted);text-transform:uppercase;letter-spacing:.5px}
.biz-m-val{font-size:26px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif;line-height:1;
  margin-top:6px;font-variant-numeric:tabular-nums}
.biz-m-u{font-size:14px;color:var(--biz-muted);margin-left:3px;font-weight:700}
.biz-m-sub{font-size:11px;color:var(--biz-muted);margin-top:6px;line-height:1.45}
.biz-m-na{color:var(--biz-muted)}

/* Mix produit */
.biz-card{background:#fff;border:1.5px solid var(--border,#e2e8f0);border-radius:18px;padding:18px 20px}
.biz-card-hd{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.biz-card-t{font-size:15px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif}
.biz-card-s{font-size:11.5px;color:var(--biz-muted);margin-top:2px}
.biz-mix{display:flex;height:38px;border-radius:9px;overflow:hidden;background:#f1f5f9}
.biz-mix-s{display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;
  font-variant-numeric:tabular-nums;overflow:hidden;white-space:nowrap;padding:0 6px;min-width:0}
/* Une teinte par série A/B/C/D, prises dans la famille des badges hc-* */
.biz-a{background:var(--navy,#06033A);color:#fff}
.biz-b{background:#1e40af;color:#fff}
.biz-c{background:#6d28d9;color:#fff}
.biz-d{background:#f59e0b;color:#422006}
.biz-dot-a{background:var(--navy,#06033A)}
.biz-dot-b{background:#1e40af}
.biz-dot-c{background:#6d28d9}
.biz-dot-d{background:#f59e0b}
/* Sous 700px les segments sont trop étroits pour un pourcentage lisible :
   la légende juste dessous porte déjà chaque valeur. */
@media(max-width:700px){.biz-pct,.biz-dem-lbl{display:none}}
.biz-mix-key{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:14px}
.biz-mix-k-t{font-size:11.5px;color:var(--biz-muted);font-weight:600}
.biz-mix-k-v{font-size:19px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif;
  line-height:1;margin-top:4px;font-variant-numeric:tabular-nums}
.biz-mix-k-p{font-size:11px;color:var(--biz-muted);margin-top:3px}
/* Absence de saisie : la barre reste, vide et rayée, pour dire « rien
   à répartir » plutôt que de laisser croire à un bloc manquant. */
.biz-mix-vide{background:repeating-linear-gradient(-45deg,#f1f5f9 0 6px,#e8edf3 6px 12px)}
.biz-dot-off{background:#cbd5e1 !important}
.biz-mix-k-off{color:#5a6678}

/* Couverture + fiabilité */
.biz-split{display:grid;grid-template-columns:1.15fr 1fr;gap:14px}
@media(max-width:900px){.biz-split{grid-template-columns:minmax(0,1fr)}}
.biz-cov{display:flex;flex-direction:column;gap:2px}
.biz-cov-r{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(70px,1.5fr) auto;align-items:center;gap:12px;
  padding:9px 0;border-bottom:1px solid #f1f5f9}
.biz-cov-r:last-child{border-bottom:none}
@media(max-width:520px){.biz-cov-r{grid-template-columns:1fr auto}.biz-cov-bar{display:none}}
.biz-cov-n{font-size:13px;font-weight:700;color:var(--navy,#06033A);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.biz-cov-s{font-size:10.5px;color:var(--biz-muted);margin-top:2px}
.biz-cov-bar{position:relative;height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden}
.biz-cov-f{height:100%;border-radius:4px;background:#16a34a}
.biz-cov-f.low{background:#d97706}
/* Repère du seuil de réapprovisionnement (15 j sur une échelle de 60 j) */
.biz-cov-mk{position:absolute;top:-2px;bottom:-2px;width:1.5px;background:rgba(6,3,58,.35)}
/* Tooltip couverture */
.cov-tip{position:fixed;z-index:9999;background:#06033A;color:#fff;border-radius:10px;padding:10px 14px;font-size:12px;min-width:180px;max-width:260px;pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.22);opacity:0;transition:opacity .15s}
.cov-tip-row{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:3px 0;border-bottom:1px solid rgba(255,255,255,.08)}
.cov-tip-row:last-child{border-bottom:none}
.cov-tip-lbl{color:#cbd5e1;font-size:11px;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cov-tip-val{font-weight:700;color:#fff;white-space:nowrap;font-variant-numeric:tabular-nums}
.biz-cov-d{display:flex;align-items:baseline;gap:5px;justify-content:flex-end;white-space:nowrap}
.biz-cov-num{font-size:18px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif;
  font-variant-numeric:tabular-nums}
.biz-cov-u{font-size:11px;color:var(--biz-muted);font-weight:700}

.biz-risk{display:flex;flex-direction:column;gap:9px}
.biz-risk-r{display:flex;align-items:center;gap:11px;background:#f8fafc;border-radius:12px;padding:12px 14px}
.biz-risk-i{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;
  font-size:15px;flex-shrink:0;background:#fff7ed;color:#c2410c}
.biz-risk-b{flex:1;min-width:0}
.biz-risk-t{font-size:12.5px;font-weight:700;color:var(--navy,#06033A)}
.biz-risk-s{font-size:11px;color:var(--biz-muted);margin-top:2px;line-height:1.4}
.biz-risk-n{font-size:20px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif;
  font-variant-numeric:tabular-nums}
.biz-risk-ok{display:flex;align-items:center;gap:11px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px}
.biz-risk-ok .biz-risk-i{background:#d1fae5;color:#065f46}
.biz-risk-ok .biz-risk-t{color:#065f46}
.biz-risk-ok .biz-risk-s{color:#15803d}
.biz-risk-ft{display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding-top:12px;border-top:1px solid #f1f5f9;margin-top:3px}

/* ══════════ POINT DU PARC MATÉRIEL ══════════ */
.eq{--biz-muted:#5a6678}
.eq-hd{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:18px}
.eq-t{font-size:15px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif}
.eq-s{font-size:11.5px;color:var(--biz-muted);margin-top:3px}
.eq-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.eq-sel{padding:7px 28px 7px 11px;border:1.5px solid var(--border,#e2e8f0);border-radius:9px;font-size:12.5px;
  font-weight:700;color:var(--navy,#06033A);font-family:inherit;cursor:pointer;appearance:none;-webkit-appearance:none;
  outline:none;transition:border-color .15s;
  background:#fff url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2 4.5L6 8.5L10 4.5' stroke='%235a6678' stroke-width='1.6' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 9px center/11px}
.eq-sel:hover{border-color:#94a3b8}
.eq-sel:focus-visible{border-color:var(--navy,#06033A);box-shadow:0 0 0 3px rgba(6,3,58,.12)}
.eq-leg{display:flex;gap:13px;flex-wrap:wrap}
.eq-leg-i{display:flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;color:var(--navy,#06033A)}
.eq-sq{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.sq-op{background:var(--navy,#06033A)}
.sq-mt{background:#f59e0b}
.sq-hs{background:#dc2626}

.eq-body{display:grid;grid-template-columns:210px 1fr;gap:22px;align-items:start}
@media(max-width:820px){.eq-body{grid-template-columns:minmax(0,1fr)}}
.eq-stats{display:flex;flex-direction:column;gap:11px}
.eq-big{font-size:40px;font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif;
  line-height:1;font-variant-numeric:tabular-nums}
.eq-big-u{font-size:18px;color:var(--biz-muted);margin-left:2px}
.eq-big-l{font-size:10px;font-weight:700;color:var(--biz-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
.eq-r{display:flex;align-items:center;gap:9px;font-size:12.5px}
.eq-r-n{font-weight:900;color:var(--navy,#06033A);font-family:'Montserrat',sans-serif;
  font-variant-numeric:tabular-nums;margin-left:auto;font-size:15px}
.eq-r-l{color:var(--biz-muted);font-weight:600}

/* Graphe empilé. L'axe X vit dans la même grille que le tracé :
   ses libellés s'alignent sur les colonnes par construction.
   Sur mobile c'est le graphe qui défile, jamais la page. */
.eq-scroll{overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch}
.eq-chart{display:grid;grid-template-columns:auto 1fr;column-gap:10px}
.eq-yax{grid-column:1;grid-row:1;position:relative;height:210px;min-width:2.2em;
  font-size:10.5px;color:var(--biz-muted);font-variant-numeric:tabular-nums;font-weight:600}
/* Chaque graduation est centrée sur sa ligne, pas posée à côté. */
.eq-yax span{position:absolute;right:0;transform:translateY(-50%);line-height:1;white-space:nowrap}
.eq-plot{grid-column:2;grid-row:1;position:relative;height:210px}
.eq-grid{position:absolute;inset:0}
.eq-grid span{position:absolute;left:0;right:0;border-top:1px dashed #e2e8f0}
.eq-cols{position:absolute;inset:0;display:flex;align-items:flex-end;gap:10px;padding:0 2px}
.eq-col{flex:1;min-width:0;max-width:64px;margin:0 auto;height:100%;
  display:flex;flex-direction:column;justify-content:flex-end;gap:3px}
.eq-seg{border-radius:6px;min-height:3px;transition:height .22s ease-out}
.seg-op{background:var(--navy,#06033A)}
.seg-mt{background:#f59e0b}
.seg-hs{background:#dc2626}
.eq-xax{grid-column:2;grid-row:2;display:flex;gap:10px;padding:9px 2px 0}
.eq-xc{flex:1;min-width:0;text-align:center}
.eq-xl{font-size:11px;font-weight:700;color:var(--navy,#06033A);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* Une colonne peu volumineuse mais très dégradée doit se lire :
   le taux prend le pas sur la hauteur de la barre. */
.eq-xn{font-size:10.5px;color:var(--biz-muted);font-weight:600;font-variant-numeric:tabular-nums;margin-top:2px}
.eq-xn b{font-weight:800}
.eq-xn.warn b{color:#b45309}
.eq-xn.bad b{color:#b91c1c}
@media(prefers-reduced-motion:reduce){.eq-seg{transition:none}}

.eq-empty{text-align:center;padding:30px 20px}
.eq-empty i{font-size:28px;color:#cbd5e1}
.eq-empty-t{font-size:13.5px;font-weight:700;color:var(--navy,#06033A);margin-top:9px}
.eq-empty-s{font-size:12px;color:var(--biz-muted);margin-top:5px;line-height:1.55;max-width:46ch;margin-inline:auto}

/* ══════════ CHANGEMENT DE FILTRE ══════════
   Le filtre recharge la page. Deux moments à couvrir : l'attente, où
   il ne se passait rien, et l'arrivée, où les chiffres apparaissaient
   d'un coup. */

/* 1. Attente : un trait de progression, rien d'autre. Le contenu reste
   entier et lisible — le rechargement ne doit pas se voir. */
.pdg-bar{position:fixed;top:0;left:0;right:0;height:2px;z-index:9999;background:transparent;
  opacity:0;transition:opacity .15s;pointer-events:none}
.pdg-busy .pdg-bar{opacity:1}
.pdg-bar::after{content:'';position:absolute;top:0;left:0;height:100%;width:38%;
  background:var(--navy,#06033A);animation:pdg-slide 1.05s cubic-bezier(.65,0,.35,1) infinite}
@keyframes pdg-slide{0%{left:-38%}100%{left:100%}}
.pdg-busy .month-inp,.pdg-busy .eq-sel{cursor:progress}

/* 2. Arrivée : les transitions sont posées en ligne par le script, sur les
   éléments qu'il a lui-même repérés. Rien à déclarer ici : un bloc ajouté
   plus tard sera animé sans qu'on ait à toucher à cette feuille. */

@media(prefers-reduced-motion:reduce){
  .pdg-bar::after{animation:none;width:100%}
  .pdg *{transition:none !important}
}

/* ══════════════════════════════════════════════════════════════════
   TABLEAUX DE BORD PAR PROFIL

   Le bureau et le téléphone comptent autant l'un que l'autre ici : le
   coordinateur consulte depuis le terrain, le superviseur depuis son
   bureau. La version étroite est donc traitée comme une mise en page à
   part entière, pas comme une dégradation de la large.

   À noter, la largeur réellement disponible est bien plus petite que la
   fenêtre : la coquille du site prend 68 px de rail latéral et 2 x 28 px
   de marge, soit 251 px utiles sur un écran de 375. Les seuils ci-dessous
   sont calés là-dessus.
   ══════════════════════════════════════════════════════════════════ */

/* En-tête de carte : titre à gauche, lien d'action à droite. */
.dash-tete{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.dash-lien{font-size:12px;font-weight:700;color:#15568B;text-decoration:none;white-space:nowrap;
  border-radius:8px;transition:background-color .15s,color .15s}
.dash-lien:hover{color:#0e3d63;background:#eff6ff}
.dash-lien:focus-visible{outline:2px solid #1B75BC;outline-offset:2px}

/* Corps de carte. Le défilement horizontal vit ici parce que .card est en
   overflow:hidden : sans ce conteneur, un tableau plus large que sa carte
   est tronqué en silence au lieu de pouvoir défiler. */
.dash-corps{margin-top:16px;overflow-x:auto;-webkit-overflow-scrolling:touch}

/* Un lien de 17 px de haut n'est pas atteignable au doigt.
   Deux conditions plutôt qu'une : pointer:coarse vise le tactile, mais tous
   les appareils ne l'annoncent pas — un navigateur en fenêtre étroite, ou un
   portable à écran tactile, passent à côté. La largeur sert donc de second
   déclencheur, parce qu'un écran étroit est presque toujours un doigt.
   Sur bureau large, la règle ne s'applique pas : agrandir les liens y
   casserait l'alignement avec le titre sans rien apporter. */
@media(pointer:coarse),(max-width:580px){
  .dash-lien{display:inline-flex;align-items:center;min-height:44px;padding:0 10px;margin:-10px -10px -10px 0}
  .pdg .eq-sel,.pdg .month-inp{min-height:44px}
}

/* Métriques de synthèse en écran étroit : une ligne par métrique, libellé
   à gauche et valeur à droite, plutôt que quatre cartes hautes empilées.
   Même information, moitié moins de hauteur avant d'atteindre le contenu. */
@media(max-width:580px){
  .biz-mx{grid-template-columns:minmax(0,1fr);gap:8px}
  .biz-m{flex-direction:row;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px}
  .biz-m-hd{min-height:0;flex-direction:row-reverse;align-items:center;justify-content:flex-end;
    gap:10px;flex:1;min-width:0}
  .biz-m-val{font-size:22px}
  .biz-m-sub{margin-top:0;text-align:right;flex-shrink:0;max-width:42%}
}

/* L'anneau et sa légende ne tiennent plus côte à côte sous 400 px. */
@media(max-width:400px){
  .donut-wrap{flex-direction:column;gap:12px}
}

/* ══════════ SYNTHÈSE — deux familles d'indicateurs ══════════
   Les indicateurs tenaient sur une seule grille en remplissage
   automatique : douze pastilles de même poids, qui retombaient en 5 + 5 + 2
   et laissaient une dernière ligne orpheline. Surtout, rien ne distinguait
   ce qui a été produit aujourd'hui de ce avec quoi on le produit.

   Ils sont désormais rangés en deux familles, chacune sur sa propre ligne
   de six. Six colonnes fixes plutôt qu'un auto-fill : une famille se lit
   d'un seul balayage, et le nombre de colonnes ne dépend plus du nombre
   d'indicateurs que les permissions ont laissé passer. */
.syn{display:flex;flex-direction:column;gap:24px}

/* Titre de famille en casse normale, quand les libellés des pastilles sont
   en capitales : la différence de casse porte la hiérarchie à elle seule,
   sans ajouter un troisième niveau de graisse ou de taille. Le filet prend
   la largeur restante — il rattache le titre à sa grille sans l'encadrer. */
.syn-hd{display:flex;align-items:center;gap:12px;margin-bottom:11px}
.syn-t{font-size:12.5px;font-weight:800;color:var(--navy,#06033A);white-space:nowrap;letter-spacing:-.1px}
.syn-hd::after{content:'';flex:1;height:1px;background:var(--border,#e2e8f0)}

.syn-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
/* Paliers structurels : 6 → 3 → 2. Trois divise six sans laisser d'orphelin,
   deux reste lisible sur la largeur utile d'un téléphone (251 px). */
@media(max-width:1080px){.syn-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:560px){.syn-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}

.syn-k{border-radius:12px;padding:14px 16px;min-width:0}
.syn-v{font-size:26px;font-weight:900;line-height:1;font-variant-numeric:tabular-nums}
/* Le libellé portait opacity:.75. À 11 px, #0369a1 à 75 % sur #f0f9ff donne
   3,45:1 — sous le seuil AA de 4,5 qui s'applique à cette taille. En pleine
   valeur, la même paire donne 5,56:1. L'opacité est donc retirée : le
   contraste entre le chiffre et son libellé passe par la taille et la
   graisse, qui ne coûtent rien en lisibilité. */
.syn-l{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
  margin-top:6px;line-height:1.3}

</style>
