<?php
/* templates/dash_v2_style.php — vocabulaire visuel du tableau de bord, v2.
 *
 * Reprend la maquette de référence : fond très clair, cartes blanches à
 * ombre douce, rangée d'indicateurs avec variation par rapport à la période
 * précédente, graphes au trait fin, tableaux à séparateurs discrets.
 *
 * TOUT est porté par `.dv2`. C'est délibéré et non négociable :
 * pages/pdg_overview.php inclut templates/dash_style.php et doit garder son
 * apparence. Les deux feuilles cohabitent donc sans se marcher dessus, et
 * une règle écrite ici ne peut pas atteindre la vue PDG. Ne jamais sortir
 * une règle de ce préfixe.
 *
 * À inclure après templates/header.php, sur une page dont le contenu vit
 * dans `<div class="dv2">`.
 */
?>
<style>
/* ══════════════════ JETONS ══════════════════
   Redéclarés localement plutôt qu'empruntés à header.php : les variables
   globales servent le reste du site, les changer ici les changerait
   partout. */
.dv2{
  --d-bg:      #F6F7F9;
  --d-surface: #FFFFFF;
  --d-line:    #ECEFF3;
  --d-ink:     #0F172A;
  /* #5A6779 donne 6,4:1 sur blanc : les libellés de 11 à 13 px passent le
     seuil AA de 4,5. Le --muted du site (#64748B, 4,33:1) ne l'aurait pas
     passé, et c'est précisément la taille employée ici. */
  --d-mut:     #5A6779;
  --d-faint:   #8A94A6;

  --d-blue:    #2563EB;
  --d-blue-l:  #EFF4FF;
  --d-green:   #0F8A47;
  --d-green-l: #E8F6EE;
  --d-red:     #D32F35;
  --d-red-l:   #FDEDED;
  --d-amber:   #B45309;
  --d-amber-l: #FEF3C7;

  --d-r:       16px;   /* rayon des cartes */
  --d-r-sm:    9px;    /* rayon des pastilles et boutons */
  --d-sh:      0 1px 2px rgba(15,23,42,.04), 0 6px 18px -8px rgba(15,23,42,.10);

  /* Échelle d'espacement, base 4 */
  --d-1: 4px; --d-2: 8px; --d-3: 12px; --d-4: 16px; --d-5: 20px; --d-6: 24px; --d-8: 32px;

  max-width:1280px;margin:0 auto;
  font-size:14px;color:var(--d-ink);
  font-variant-numeric:tabular-nums;
}
.dv2 *{box-sizing:border-box}

/* La coquille du site pose --tertiary (un lavande) en fond de page ; la
   maquette est neutre. `:has()` permet de ne repeindre que les pages qui
   portent un `.dv2` : la vue PDG et le reste du site gardent le leur, sans
   qu'on ait à poser une classe supplémentaire depuis PHP. */
.main-wrap:has(.dv2),
.main-wrap:has(.dv2) .page-content{background:var(--d-bg)}

/* ══════════════════ EN-TÊTE ══════════════════ */
.dv2-hd{display:flex;justify-content:space-between;align-items:center;gap:var(--d-4);
  flex-wrap:wrap;margin-bottom:var(--d-5)}
.dv2-h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:21px;font-weight:800;
  letter-spacing:-.4px;line-height:1.2}
.dv2-h1-sub{font-size:12.5px;color:var(--d-mut);margin-top:3px;font-weight:500}
.dv2-tools{display:flex;align-items:center;gap:var(--d-2);flex-wrap:wrap}

/* Boutons et sélecteurs de la barre d'outils : même hauteur, même rayon,
   même graisse. La maquette les traite comme une seule famille. */
.dv2-btn{display:inline-flex;align-items:center;gap:7px;height:36px;padding:0 13px;
  border:1px solid var(--d-line);border-radius:var(--d-r-sm);background:var(--d-surface);
  font-family:inherit;font-size:12.5px;font-weight:600;color:var(--d-ink);
  cursor:pointer;text-decoration:none;white-space:nowrap;
  transition:border-color .15s,background-color .15s}
.dv2-btn:hover{border-color:#D7DDE6;background:#FBFCFD}
.dv2-btn:focus-visible{outline:2px solid var(--d-blue);outline-offset:2px}
.dv2-btn i{font-size:15px;color:var(--d-mut)}
.dv2-btn-pri{background:var(--d-blue);border-color:var(--d-blue);color:#fff}
.dv2-btn-pri:hover{background:#1D4FD8;border-color:#1D4FD8}
.dv2-btn-pri i{color:#fff}
/* Le sélecteur natif porte sa propre flèche : on la remplace pour qu'il
   soit indiscernable d'un bouton. */
.dv2-sel{appearance:none;-webkit-appearance:none;padding-right:30px;
  background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2.5 4.5L6 8L9.5 4.5' stroke='%235A6779' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 10px center;background-size:11px}

/* ══════════════════ CARTE ══════════════════ */
.dv2-c{background:var(--d-surface);border:1px solid var(--d-line);border-radius:var(--d-r);
  padding:var(--d-5);box-shadow:var(--d-sh);min-width:0}
.dv2-c-hd{display:flex;justify-content:space-between;align-items:flex-start;gap:var(--d-3);
  margin-bottom:var(--d-4)}
.dv2-c-t{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;
  letter-spacing:-.2px}
.dv2-c-s{font-size:11.5px;color:var(--d-mut);margin-top:2px;font-weight:500}
/* Le « ••• » de la maquette est un lien réel vers la page du bloc, pas un
   menu décoratif : il mène là où l'on peut agir. */
.dv2-more{flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;
  min-width:28px;height:28px;padding:0 8px;border-radius:8px;
  color:var(--d-mut);text-decoration:none;font-size:12px;font-weight:700;
  transition:background-color .15s,color .15s}
.dv2-more:hover{background:var(--d-blue-l);color:var(--d-blue)}
.dv2-more:focus-visible{outline:2px solid var(--d-blue);outline-offset:2px}
/* Cible tactile de 44 px sans grossir la pastille. */
@media(pointer:coarse),(max-width:600px){
  .dv2-more{min-height:44px;min-width:44px}
  .dv2-btn,.dv2-sel{height:44px}
}
.dv2-c-body{min-width:0;overflow-x:auto;-webkit-overflow-scrolling:touch}

/* ══════════════════ RANGÉE D'INDICATEURS ══════════════════
   Quatre cartes de même forme, comme la maquette : la comparaison entre
   elles n'a de sens que si rien ne les distingue à part leurs chiffres. */
.dv2-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--d-4);
  margin-bottom:var(--d-4)}
@media(max-width:1000px){.dv2-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.dv2-kpis{grid-template-columns:1fr}}

.dv2-k{background:var(--d-surface);border:1px solid var(--d-line);border-radius:var(--d-r);
  padding:var(--d-5);box-shadow:var(--d-sh);min-width:0}
.dv2-k-hd{display:flex;justify-content:space-between;align-items:center;gap:var(--d-2)}
.dv2-k-l{font-size:13px;font-weight:600;color:var(--d-ink)}
.dv2-k-i{width:30px;height:30px;border-radius:8px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:16px}
.dv2-k-v{font-family:'Plus Jakarta Sans',sans-serif;font-size:29px;font-weight:800;
  letter-spacing:-1px;line-height:1.1;margin-top:var(--d-3)}
.dv2-k-ft{display:flex;align-items:center;gap:var(--d-2);margin-top:var(--d-2);flex-wrap:wrap}
.dv2-k-vs{font-size:11.5px;color:var(--d-mut);font-weight:500}

/* Variation : la flèche double la couleur, pour que l'information ne repose
   pas sur elle seule. */
.dv2-d{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:20px;
  font-size:11.5px;font-weight:700;white-space:nowrap}
.dv2-d-up{background:var(--d-green-l);color:var(--d-green)}
.dv2-d-dn{background:var(--d-red-l);color:var(--d-red)}
.dv2-d-flat{background:#F1F3F6;color:var(--d-mut)}

/* ══════════════════ GRILLE DE BLOCS ══════════════════
   Douze colonnes : la maquette alterne des blocs deux tiers et un tiers,
   ce qu'une grille en deux colonnes égales ne sait pas faire. */
/* `dense` comble les trous : sans lui, un bloc court placé à côté d'un bloc
   haut laisse sous lui une colonne de vide jusqu'à la rangée suivante. Le
   remplissage dense remonte le bloc suivant qui tient dans la place libre.
   L'ordre de lecture s'en trouve légèrement réordonné, ce qui est acceptable
   ici : les blocs sont autonomes et titrés, aucun ne se lit par rapport au
   précédent. */
.dv2-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));
  gap:var(--d-4);align-items:start;grid-auto-flow:row dense}
.dv2-grid > *{min-width:0}
.dv2-w-8{grid-column:span 8}
.dv2-w-4{grid-column:span 4}
.dv2-w-6{grid-column:span 6}
.dv2-w-12{grid-column:span 12}
@media(max-width:1000px){
  .dv2-w-8,.dv2-w-4,.dv2-w-6{grid-column:span 6}
}
@media(max-width:720px){
  .dv2-w-8,.dv2-w-4,.dv2-w-6,.dv2-w-12{grid-column:span 12}
}

/* ══════════════════ BLOC DE TÊTE (métrique + courbe) ══════════════════ */
.dv2-hero-v{font-family:'Plus Jakarta Sans',sans-serif;font-size:34px;font-weight:800;
  letter-spacing:-1.4px;line-height:1}
.dv2-hero-row{display:flex;align-items:flex-end;justify-content:space-between;
  gap:var(--d-5);flex-wrap:wrap}
.dv2-hero-l{flex-shrink:0}
.dv2-hero-ch{flex:1;min-width:240px}
.dv2-hero-ch canvas{display:block;max-width:100%}

/* Bande de répartition sous la métrique de tête. Le filet coloré sous
   chaque chiffre est celui de la maquette : il code la catégorie, et sa
   longueur code la part. */
.dv2-split{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--d-4);
  margin-top:var(--d-5);padding-top:var(--d-5);border-top:1px solid var(--d-line)}
@media(max-width:600px){.dv2-split{grid-template-columns:1fr;gap:var(--d-3)}}
.dv2-sp-v{display:flex;align-items:baseline;gap:6px;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:20px;font-weight:800;letter-spacing:-.5px}
.dv2-sp-i{font-size:13px}
.dv2-sp-l{font-size:11.5px;color:var(--d-mut);font-weight:500;margin-top:2px}
.dv2-sp-bar{height:3px;border-radius:2px;margin-top:var(--d-2);background:#EEF1F5;overflow:hidden}
.dv2-sp-fill{height:100%;border-radius:2px;transition:width .4s cubic-bezier(.22,1,.36,1)}
@media(prefers-reduced-motion:reduce){.dv2-sp-fill{transition:none}}

/* ══════════════════ JAUGE ══════════════════ */
.dv2-gauge{display:flex;flex-direction:column;align-items:center;text-align:center}
.dv2-gauge canvas{display:block;max-width:100%}
.dv2-g-v{font-family:'Plus Jakarta Sans',sans-serif;font-size:32px;font-weight:800;
  letter-spacing:-1.2px;line-height:1;margin-top:-38px}
.dv2-g-s{font-size:11.5px;color:var(--d-mut);margin-top:var(--d-2);font-weight:500;max-width:30ch}

/* ══════════════════ TABLEAU ══════════════════ */
.dv2-t{width:100%;border-collapse:collapse;font-size:13px}
/* header.php impose th{color:var(--muted)!important} sur tout le site :
   sans !important la couleur déclarée ici serait écrasée en silence. */
.dv2-t th{font-size:10.5px;font-weight:700;color:var(--d-mut)!important;background:transparent!important;
  text-transform:uppercase;letter-spacing:.5px;text-align:right;white-space:nowrap;
  padding:0 var(--d-3) var(--d-3);border-bottom:1px solid var(--d-line)}
.dv2-t th:first-child{text-align:left;padding-left:0}
.dv2-t th:last-child{padding-right:0}
.dv2-t td{padding:var(--d-3);border-bottom:1px dashed var(--d-line);text-align:right;
  vertical-align:middle;white-space:nowrap}
.dv2-t td:first-child{text-align:left;padding-left:0}
.dv2-t td:last-child{padding-right:0}
.dv2-t tr:last-child td{border-bottom:none}
.dv2-t tbody tr{transition:background-color .12s}
.dv2-t tbody tr:hover td{background:#FAFBFC}
.dv2-t-id{color:var(--d-mut);font-weight:600;font-size:12px}
/* Cellule nom : pastille d'icône puis libellé, comme la maquette. */
.dv2-t-n{display:flex;align-items:center;gap:10px;min-width:0}
.dv2-t-ic{width:30px;height:30px;border-radius:8px;background:#F3F5F8;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--d-mut)}
.dv2-t-lbl{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  max-width:26ch}
.dv2-t-num{font-weight:700}
/* Valeur signée : la pastille colorée de la colonne « revenue ». */
.dv2-t-sig{display:inline-flex;align-items:center;gap:4px;font-weight:700;font-size:12.5px}
.dv2-t-sig.up{color:var(--d-green)}
.dv2-t-sig.dn{color:var(--d-red)}
.dv2-t-star{display:inline-flex;align-items:center;gap:4px;font-weight:700;font-size:12.5px}
.dv2-t-star i{color:#F59E0B;font-size:14px}

/* ══════════════════ LISTES ══════════════════ */
.dv2-l{display:flex;flex-direction:column}
.dv2-l-r{display:flex;align-items:center;gap:var(--d-3);padding:11px 0;
  border-bottom:1px dashed var(--d-line)}
.dv2-l-r:last-child{border-bottom:none}
.dv2-l-b{flex:1;min-width:0}
.dv2-l-t{font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dv2-l-s{font-size:11.5px;color:var(--d-mut);margin-top:2px}
.dv2-l-v{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15px;flex-shrink:0}

/* Pastilles d'état */
.dv2-p{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;
  font-size:11px;font-weight:700;white-space:nowrap}
.dv2-p-g{background:var(--d-green-l);color:var(--d-green)}
.dv2-p-o{background:var(--d-amber-l);color:var(--d-amber)}
.dv2-p-r{background:var(--d-red-l);color:var(--d-red)}
.dv2-p-b{background:var(--d-blue-l);color:var(--d-blue)}
.dv2-p-n{background:#F1F3F6;color:var(--d-mut)}

/* ══════════════════ ÉTAT VIDE ══════════════════
   Une phrase qui dit ce que l'absence signifie, jamais un cadre nu. */
.dv2-empty{text-align:center;padding:26px 16px}
.dv2-empty i{font-size:26px;color:#CBD5E1}
.dv2-empty-t{font-size:13px;font-weight:600;margin-top:8px}
.dv2-empty-s{font-size:12px;color:var(--d-mut);margin-top:4px;line-height:1.5;
  max-width:44ch;margin-inline:auto}
.dv2-empty.ok .dv2-empty-t{color:var(--d-green)}
.dv2-empty.ok i{color:#86EFAC}

/* ══════════════════ ATTENTE DE FILTRE ══════════════════ */
.dv2-bar{position:fixed;top:0;left:0;right:0;height:2px;z-index:9999;
  opacity:0;transition:opacity .15s;pointer-events:none}
.dv2-busy .dv2-bar{opacity:1}
.dv2-bar::after{content:'';position:absolute;top:0;left:0;height:100%;width:38%;
  background:var(--d-blue);animation:dv2-slide 1.05s cubic-bezier(.65,0,.35,1) infinite}
@keyframes dv2-slide{0%{left:-38%}100%{left:100%}}
@media(prefers-reduced-motion:reduce){
  .dv2-bar::after{animation:none;width:100%}
  .dv2 *{transition:none!important}
}
</style>
