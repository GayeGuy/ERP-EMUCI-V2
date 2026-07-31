<?php
/* templates/dash_charts.php — graphes partagés des tableaux de bord.
 *
 * Anneaux, courbes et barres horizontales, extraits de pages/pdg_overview.php
 * pour que les vues par profil disposent du même matériel visuel.
 *
 * Deux choses distinguent ce composant de l'original :
 *
 *  1. Les séries ne sont plus des variables globales écrites par PHP. Chaque
 *     canvas porte ses données en attributs data-*. C'est ce qui le rend
 *     compatible avec l'échange de contenu sans rechargement : le nouveau
 *     balisage arrive avec ses propres chiffres, sans qu'aucun script ait à
 *     être réexécuté ni aucune globale resynchronisée.
 *
 *  2. Le tracé se découvre en parcourant le DOM, comme le fait déjà
 *     templates/dash_anim.php pour les valeurs. Ajouter un graphe dans un
 *     bloc ne demande donc pas de toucher à ce fichier.
 *
 * À inclure après templates/dash_style.php et AVANT templates/dash_anim.php,
 * qui appelle render() à chaque image de l'animation.
 */
?>
<script>
(function () {
  'use strict';

  var DPR = window.devicePixelRatio || 1;

  function fmtN(n) { return Number(n).toLocaleString('fr-FR'); }

  // Avancement du tracé, de 0 à 1, posé par le moteur d'animation.
  // Ces graphes sont relatifs à leur propre échelle : un anneau ne montre que
  // des rapports, une courbe se cale sur son propre maximum. Mettre la série
  // à l'échelle laisserait donc le tracé identique — c'est la géométrie qu'on
  // anime, l'échelle restant calculée sur les valeurs finales.
  function prog() {
    var p = window.pdgProg;
    return typeof p === 'number' ? Math.max(0, Math.min(1, p)) : 1;
  }

  function lire(el, nom, defaut) {
    var brut = el.getAttribute('data-' + nom);
    if (brut === null || brut === '') return defaut;
    try { return JSON.parse(brut); } catch (e) { return defaut; }
  }

  // Largeur réellement disponible dans un conteneur. Sa boîte de bordure
  // inclut padding et bordure : s'en servir rend le canvas plus large que la
  // place offerte, la piste de grille s'élargit pour l'accueillir, et le
  // redessin suivant lit une largeur encore plus grande. Emballement.
  function largeurUtile(p, defaut) {
    if (!p) return defaut;
    var cs = getComputedStyle(p);
    var w = p.clientWidth - parseFloat(cs.paddingLeft || 0) - parseFloat(cs.paddingRight || 0);
    return w > 40 ? Math.round(w) : defaut;
  }

  // Prépare un canvas pleine largeur.
  //
  // el.height est écrasé plus bas par la taille en pixels physiques
  // (hauteur x DPR). Relire l'attribut au redessin suivant donnerait donc une
  // hauteur déjà multipliée, et le canvas grandirait à chaque passage jusqu'à
  // sortir de son cadre. On mémorise la valeur voulue une fois pour toutes.
  function cadre(el, hParDefaut) {
    if (!el.dataset.hCss) el.dataset.hCss = parseInt(el.getAttribute('height') || hParDefaut, 10);
    var w = largeurUtile(el.parentElement, 400);
    var h = parseInt(el.dataset.hCss, 10);
    el.width  = Math.round(w * DPR);
    el.height = Math.round(h * DPR);
    el.style.width  = Math.round(w) + 'px';
    el.style.height = h + 'px';
    var ctx = el.getContext('2d');
    ctx.scale(DPR, DPR);
    return { ctx: ctx, w: Math.round(w), h: h };
  }

  function vide(ctx, w, h, message) {
    ctx.fillStyle = '#5a6678';
    ctx.font = '12px DM Sans,sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(message || 'Aucune donnée', w / 2, h / 2);
  }

  // ── Anneau ────────────────────────────────────────────────────────
  // Les parts gardent leurs proportions ; c'est le balayage qui progresse,
  // du haut et dans le sens horaire.
  function anneau(el) {
    var valeurs  = lire(el, 'valeurs', []);
    var couleurs = lire(el, 'couleurs', ['#1B75BC', '#f59e0b', '#16a34a', '#6d28d9', '#dc2626']);

    if (!el.dataset.szCss) el.dataset.szCss = parseInt(el.getAttribute('width') || 120, 10);
    var sz = parseInt(el.dataset.szCss, 10);
    el.width = sz * DPR; el.height = sz * DPR;
    el.style.width = sz + 'px'; el.style.height = sz + 'px';
    var ctx = el.getContext('2d');
    ctx.scale(DPR, DPR);

    var cx = sz / 2, cy = sz / 2, R = sz / 2 - 5, r = R * 0.58;
    var total = valeurs.reduce(function (a, b) { return a + b; }, 0);

    if (!total) {
      ctx.fillStyle = '#f1f5f9';
      ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#fff';
      ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#5a6678'; ctx.font = 'bold 12px Montserrat,sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('0', cx, cy);
      return;
    }

    var debut = -Math.PI / 2;
    var limite = debut + prog() * Math.PI * 2;

    // Fond de l'anneau, pour que la couronne existe avant d'être remplie.
    ctx.fillStyle = '#f1f5f9';
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.fill();

    var angle = debut;
    valeurs.forEach(function (v, i) {
      if (!v) return;
      var part = (v / total) * Math.PI * 2;
      var fin  = Math.min(angle + part, limite);
      if (fin > angle) {
        ctx.fillStyle = couleurs[i % couleurs.length];
        ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, R, angle, fin); ctx.closePath(); ctx.fill();
      }
      angle += part;
    });

    ctx.fillStyle = '#fff';
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#06033A'; ctx.font = 'bold 14px Montserrat,sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(fmtN(Math.round(total * prog())), cx, cy);
  }

  // ── Courbe avec aire ──────────────────────────────────────────────
  // L'échelle se calcule sur les valeurs finales : l'axe ne bouge pas
  // pendant que la courbe monte. Seule la hauteur tracée suit l'avancement.
  function courbe(el) {
    var c = cadre(el, 170);
    var ctx = c.ctx, w = c.w, h = c.h;
    var valeurs  = lire(el, 'valeurs', []);
    var libelles = lire(el, 'libelles', []);
    var teinte   = el.getAttribute('data-couleur') || '#1B75BC';

    if (!valeurs.length) { vide(ctx, w, h); return; }

    // Sur écran étroit, la marge de gauche et les étiquettes d'axe mangent
    // toute la place : on allège plutôt que de laisser les textes se
    // chevaucher.
    var etroit = w < 380;
    var pad = { t: 20, r: 12, b: 32, l: etroit ? 30 : 40 };
    var cW = w - pad.l - pad.r, cH = h - pad.t - pad.b;
    var n = valeurs.length;
    var max = Math.max.apply(null, valeurs.concat([1]));
    var pas = cW / Math.max(n - 1, 1);
    var P = prog();

    var y = function (v) { return pad.t + cH * (1 - (v * P) / max); };
    var x = function (i) { return pad.l + i * pas; };

    // Lignes de repère et graduation
    ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
    for (var i = 0; i <= 4; i++) {
      var yy = pad.t + cH * (1 - i / 4);
      ctx.beginPath(); ctx.moveTo(pad.l, yy); ctx.lineTo(w - pad.r, yy); ctx.stroke();
      ctx.fillStyle = '#5a6678'; ctx.font = '10px DM Sans,sans-serif';
      ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
      ctx.fillText(Math.round(max * i / 4), pad.l - 5, yy);
    }

    // Aire sous la courbe
    ctx.beginPath();
    valeurs.forEach(function (v, i) { i === 0 ? ctx.moveTo(x(i), y(v)) : ctx.lineTo(x(i), y(v)); });
    ctx.lineTo(x(n - 1), pad.t + cH);
    ctx.lineTo(pad.l, pad.t + cH);
    ctx.closePath();
    var grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + cH);
    grad.addColorStop(0, 'rgba(27,117,188,.18)');
    grad.addColorStop(1, 'rgba(27,117,188,.02)');
    ctx.fillStyle = grad;
    ctx.fill();

    // Tracé
    ctx.beginPath();
    ctx.strokeStyle = teinte; ctx.lineWidth = 2.5; ctx.lineJoin = 'round';
    valeurs.forEach(function (v, i) { i === 0 ? ctx.moveTo(x(i), y(v)) : ctx.lineTo(x(i), y(v)); });
    ctx.stroke();

    // Points, valeurs et étiquettes. Sur écran étroit une étiquette sur deux,
    // sinon elles se chevauchent et deviennent illisibles.
    valeurs.forEach(function (v, i) {
      ctx.beginPath(); ctx.arc(x(i), y(v), 4, 0, Math.PI * 2);
      ctx.fillStyle = '#fff'; ctx.fill();
      ctx.strokeStyle = teinte; ctx.lineWidth = 2; ctx.stroke();

      if (v > 0 && (!etroit || i % 2 === 0)) {
        ctx.fillStyle = '#06033A'; ctx.font = 'bold 10px DM Sans,sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
        ctx.fillText(fmtN(Math.round(v * P)), x(i), y(v) - 6);
      }
      if (!etroit || i % 2 === 0) {
        ctx.fillStyle = '#5a6678'; ctx.font = '10px DM Sans,sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        ctx.fillText(libelles[i] || '', x(i), pad.t + cH + 6);
      }
    });
  }

  // ── Barres horizontales ───────────────────────────────────────────
  // Un libellé à gauche, la barre, la valeur à droite. La barre part de zéro
  // et s'étend : c'est le seul graphe où l'animation se voit vraiment.
  function barres(el) {
    var valeurs  = lire(el, 'valeurs', []);
    var libelles = lire(el, 'libelles', []);
    var teinte   = el.getAttribute('data-couleur') || '#1B75BC';

    // La hauteur suit le nombre de lignes : un canvas fixe écrase huit sites
    // en bandes de trois pixels, ou laisse un grand vide pour deux.
    var hLigne = 30;
    if (!el.dataset.hCss) {
      el.dataset.hCss = Math.max(60, valeurs.length * hLigne);
      el.setAttribute('height', el.dataset.hCss);
    }

    var c = cadre(el, 200);
    var ctx = c.ctx, w = c.w, h = c.h;
    if (!valeurs.length) { vide(ctx, w, h); return; }

    var etroit = w < 380;
    var lw = etroit ? 88 : 130;          // colonne des libellés
    var rw = 48;                          // colonne des valeurs
    var aire = Math.max(30, w - lw - rw);
    var rowH = h / valeurs.length;
    var max = Math.max.apply(null, valeurs.concat([1]));
    var P = prog();

    valeurs.forEach(function (v, i) {
      var y = i * rowH;
      var hb = Math.min(14, rowH - 10);
      var yb = y + (rowH - hb) / 2;

      // Libellé, tronqué proprement plutôt que débordant sur la barre.
      ctx.fillStyle = '#334155'; ctx.font = '11px DM Sans,sans-serif';
      ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
      var lbl = String(libelles[i] || '');
      while (lbl && ctx.measureText(lbl).width > lw - 10) lbl = lbl.slice(0, -1);
      if (lbl !== String(libelles[i] || '')) lbl = lbl.slice(0, -1) + '…';
      ctx.fillText(lbl, 0, y + rowH / 2);

      // Piste puis remplissage
      ctx.fillStyle = '#f1f5f9';
      arrondi(ctx, lw, yb, aire, hb, hb / 2); ctx.fill();

      var lg = aire * (v / max) * P;
      if (lg > 1) {
        ctx.fillStyle = teinte;
        arrondi(ctx, lw, yb, lg, hb, hb / 2); ctx.fill();
      }

      ctx.fillStyle = '#06033A'; ctx.font = 'bold 11px DM Sans,sans-serif';
      ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
      ctx.fillText(fmtN(Math.round(v * P)), w, y + rowH / 2);
    });
  }

  function arrondi(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y,     x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x,     y + h, r);
    ctx.arcTo(x,     y + h, x,     y,     r);
    ctx.arcTo(x,     y,     x + w, y,     r);
    ctx.closePath();
  }

  // ── Jauge en arc segmenté (maquette v2)
  //
  // L'arc est découpé en graduations plutôt que tracé plein : à 68 %, un arc
  // continu se lit « environ deux tiers », des graduations se comptent. La
  // part atteinte est colorée, le reste reste gris — la position du dernier
  // segment coloré porte l'information, pas seulement sa teinte.
  function jauge(el) {
    var v      = lire(el, 'valeurs', [0])[0] || 0;   // 0 à 100
    var cible  = parseFloat(el.getAttribute('data-cible') || '0');
    var teinte = el.getAttribute('data-couleur') || '#0F8A47';
    var c = cadre(el, 150), ctx = c.ctx, w = c.w, h = c.h;

    var cx = w / 2, cy = h * 0.92, r = Math.min(w / 2, h) * 0.86;
    var N = 44, DEB = Math.PI, FIN = 2 * Math.PI;     // demi-cercle supérieur
    var atteint = Math.round(N * Math.max(0, Math.min(100, v)) / 100 * prog());
    var iCible  = cible > 0 ? Math.round(N * Math.min(100, cible) / 100) : -1;

    for (var i = 0; i < N; i++) {
      var a = DEB + (FIN - DEB) * (i + 0.5) / N;
      var x1 = cx + Math.cos(a) * (r * 0.78), y1 = cy + Math.sin(a) * (r * 0.78);
      var x2 = cx + Math.cos(a) * r,          y2 = cy + Math.sin(a) * r;
      ctx.beginPath();
      ctx.lineWidth = Math.max(2, r * 0.055);
      ctx.lineCap = 'round';
      ctx.strokeStyle = i < atteint ? teinte : '#E7EBF0';
      ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
    }

    // Repère d'objectif : un trait plus long, en dehors de l'arc.
    if (iCible >= 0 && iCible <= N) {
      var ac = DEB + (FIN - DEB) * (iCible + 0.5) / N;
      ctx.beginPath();
      ctx.lineWidth = Math.max(1.5, r * 0.03);
      ctx.strokeStyle = '#0F172A';
      ctx.moveTo(cx + Math.cos(ac) * (r * 0.70), cy + Math.sin(ac) * (r * 0.70));
      ctx.lineTo(cx + Math.cos(ac) * (r * 1.08), cy + Math.sin(ac) * (r * 1.08));
      ctx.stroke();
    }
  }

  var TRACEURS = { anneau: anneau, courbe: courbe, barres: barres, jauge: jauge };

  // ── Parcours ──────────────────────────────────────────────────────
  // Découvre les graphes plutôt que de les tenir dans une liste : un bloc du
  // registre pose un canvas avec ses données, et il est pris en charge.
  function dessinerTout() {
    // `.pdg` est la racine de la vue PDG, `.dv2` celle du tableau de bord v2.
    // Les deux coquilles coexistent : chercher dans une seule laisserait les
    // graphes de l'autre définitivement vides, sans erreur ni trace.
    var cibles = document.querySelectorAll('.pdg canvas[data-graphe], .dv2 canvas[data-graphe]');
    for (var i = 0; i < cibles.length; i++) {
      var el = cibles[i];
      var f = TRACEURS[el.getAttribute('data-graphe')];
      if (!f) continue;
      // Un graphe qui échoue ne doit pas empêcher les suivants d'être tracés,
      // mais il ne doit pas non plus échouer en silence : un canvas resté sur
      // ses anciennes données ne se distingue pas d'un canvas à jour.
      try { f(el); }
      catch (e) { if (window.console) console.warn('graphe ' + el.id + ' : ' + e.message); }
    }
  }

  window.dashGraphes = dessinerTout;

  // Le moteur d'animation appelle render() à chaque image. La vue PDG définit
  // déjà la sienne ; on ne l'écrase pas, on ne fournit la nôtre que si la
  // page n'en a pas.
  if (typeof window.render !== 'function') window.render = dessinerTout;

  // Un canvas est dessiné en pixels : contrairement au reste de la mise en
  // page, il ne se réajuste pas tout seul quand la fenêtre change de largeur.
  // Sans ceci, passer du bureau au téléphone laisse des graphes tronqués.
  var minuteur = null;
  window.addEventListener('resize', function () {
    clearTimeout(minuteur);
    minuteur = setTimeout(function () {
      // Les largeurs mémorisées sont recalculées à chaque tracé ; seule la
      // hauteur des barres, qui dépend du nombre de lignes, est à conserver.
      dessinerTout();
    }, 150);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', dessinerTout);
  } else {
    dessinerTout();
  }
})();
</script>
