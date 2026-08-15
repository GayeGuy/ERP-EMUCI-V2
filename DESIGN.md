# Système de design — ERP EMUCI

Ce document capture les conventions visuelles et d'accessibilité de l'appli,
telles qu'elles existent réellement dans le code (pas un objectif théorique).
Écrit après l'audit d'accessibilité de 2026-08 (contraste, responsive,
formulaires, typographie) pour éviter que les mêmes dérives ne se reproduisent
page après page. À mettre à jour quand une nouvelle page introduit un
composant ou une couleur qui devrait être réutilisable.

Source de vérité pour tous les tokens : `templates/header.php`, bloc `:root`
(lignes ~37-88). Les pages qui ont leur propre `<style>` (la plupart) doivent
consommer ces variables via `var(--xxx)`, jamais recopier une couleur en dur.

---

## Couleurs

| Token | Valeur | Rôle |
|---|---|---|
| `--primary` | `#7C92FF` | Marque — icônes/fonds décoratifs, y compris sur fond sombre (sidebar) |
| `--primary-d` | `#3D4FD1` | Variante « texte lisible » — tout `color:` sur fond clair, tout bouton plein |
| `--primary-l` | `#E8ECFF` | Fond pâle (hover, badges) |
| `--navy` | `#1E2B4A` | Fond sidebar, texte principal foncé |
| `--muted` | `#64748B` | Texte secondaire |
| `--border` | `#E2E8F0` | Bordures |
| `--danger` / `--danger-d` | `#F87171` / `#C0392B` | Idem couple marque/texte |
| `--success` / `--success-d` | `#34D399` / `#0A7A52` | Idem |
| `--warning` / `--warning-d` | `#FBBF24` / `#8A5A00` | Idem |

**Règle marque vs texte lisible :** `--primary`/`--danger`/`--success`/`--warning`
sont les teintes de marque (bordures, puces, fonds pâles, icônes sur fond
sombre). Elles ne passent **pas** WCAG AA (4.5:1) en texte sur fond clair.
Dès qu'une couleur sert de `color:` sur fond clair, ou de fond plein avec
texte blanc dessus (bouton, pastille, badge), utiliser le suffixe `-d`. Ne
jamais redéfinir `--primary` etc. eux-mêmes pour « corriger » le contraste —
ça casse l'icône active de la sidebar (fond sombre), qui a besoin de la
teinte claire. Chaque nouvel usage de couleur doit être classé marque/texte
au cas par cas, pas par un remplacement global du token.

---

## Typographie

- Corps de texte : `'Manrope', sans-serif`. Titres/marque : `'Plus Jakarta
  Sans', sans-serif`.
- **Plancher 12px.** Aucun texte visible ne descend sous `font-size: 12px`
  (corrigé sur 463 occurrences en 2026-08, cf. commit `a5a2540`). Exception :
  les gabarits HTML générés pour export PDF (Dompdf) — densité papier
  volontaire, hors périmètre WCAG écran. Reconnaissables par le marqueur
  `<!DOCTYPE html>` dans une chaîne PHP assignée à `$html`/`$css` juste avant
  `new Dompdf(...)`.
- **Hiérarchie des titres.** `templates/header.php` fournit le seul `<h1>`
  de chaque page (`.topbar-title`, le titre affiché en haut). Une page ne
  doit **jamais** ajouter son propre `<h1>` — si elle a besoin d'un gros
  titre dans le corps (bannière, en-tête de section), c'est un `<h2>`. Les
  cartes/sections utilisent `<h3>`, les sous-éléments `<h4>`. Le logo
  « ERP EMUCI » du sidebar est du texte de marque (`<p>`), pas un titre —
  il est identique sur toutes les pages et n'apporte rien à la navigation
  par titres d'un lecteur d'écran.

---

## Layout

- Sidebar fixe `--sidebar-w: 252px`, topbar `--topbar-h: 64px`.
- `--radius: 16px` (cartes), `--radius-sm: 10px` (boutons/inputs).
- **Piège flex/grid récurrent :** un enfant flex ou une piste grid refuse de
  rétrécir sous la largeur intrinsèque de son contenu tant qu'il n'a pas
  `min-width: 0` (flex) ou `minmax(0, 1fr)` (grid), même avec `flex: 1` ou
  `width: 100%`. C'est la cause numéro un des débordements horizontaux à
  375px. Réflexe à avoir sur tout nouveau conteneur flex/grid contenant du
  texte ou un tableau : ajouter `min-width: 0` par défaut, l'enlever
  seulement si un test réel montre que ce n'est pas nécessaire.
- 11 pages gardent un débordement résiduel à 375px, jamais centralisé :
  articles, consommables, equipements, operations/bobines (le tableau
  lui-même), inventaire_bobines, rapports, import_emuci, reception_site,
  validation_stock_matin, affectations_it, rapport_journalier,
  interventions, plus `admin/permissions.php` (42px, cause non identifiée
  malgré deux correctifs grid). Chacune a une barre de filtres ou un tableau
  local qui n'a pas encore reçu le traitement `min-width:0` — à corriger page
  par page plutôt que par un correctif global, le composant n'étant pas
  partagé.

---

## Composants

- **Cibles tactiles :** plancher 44×44px sur `.btn`, `.page-btn`,
  `.notif-btn`. Exception délibérée : `.btn-sm` (colonnes d'action des
  tableaux denses) reste à 32px de hauteur — réduire la densité
  d'information y coûterait plus qu'un doigt un peu moins précis. ~165
  cibles restent sous 44px, dominées par des variantes `.fsel`/`.tab-btn`
  locales à certaines pages et par les cases à cocher/radios natives
  (volontairement non retaillées).
- **Champs obligatoires :** un seul marqueur visuel, géré globalement dans
  `templates/header.php` :
  ```css
  .form-group:has(> .form-control:required) > label::after,
  .form-group:has(> input:required) > label::after,
  .form-group:has(> select:required) > label::after,
  .form-group:has(> textarea:required) > label::after {
    content: " *"; color: var(--danger-d);
  }
  ```
  Ne pas ajouter de `*` en dur dans un label — laisser cette règle le faire
  à partir de l'attribut HTML `required`.
- **Notifications toast :** un seul motif dans toute l'appli (`templates/footer.php`
  définit la version canonique, chaque page qui a sa propre copie doit suivre
  le même patron) — élément persistant réutilisé par `id`, jamais recréé/détruit,
  avec `role="status" aria-live="polite" aria-atomic="true"` posé une fois à
  la création. Réutiliser l'élément (pas le recréer) est ce qui garantit que
  les lecteurs d'écran annoncent chaque nouveau message.
- **Libellés de champs :** tout input/select doit avoir un nom accessible —
  `<label for="id">` lié par `id`, ou `aria-label` à défaut de libellé visible
  (cas des filtres `.fsel` sans `<label>` dans le HTML). Zéro champ sans nom
  accessible mesuré sur 28 pages échantillonnées en 2026-08.

---

## Icônes : Phosphor plutôt qu'emoji

Les emoji utilisés comme icônes dans le HTML/JS rendu navigateur ont été
convertis en `<i class="ph ph-xxx" aria-hidden="true"></i>` (2026-08, 724
remplacements sur 43 fichiers). Restent volontairement en emoji :

- Tout ce qui est stocké/réaffiché comme texte brut échappé par `h()` —
  messages de notification (`notif_create`, table `notifications`),
  messages d'audit (`audit_log`), réponses JSON (`json_response`), lignes
  `db_query`/`db_insert`. Une balise `<i>` y apparaîtrait littéralement
  (`&lt;i class=...&gt;`) au lieu de s'afficher comme icône.
- Le contenu de `<option>`, les attributs `title=`/`alt=`/`aria-label=`/
  `placeholder=`, et tout ce qui passe par `.textContent =` en JS — le HTML
  n'y est jamais interprété.
- Les gabarits HTML destinés à l'export PDF (Dompdf) — la police Phosphor
  n'y est pas chargée (voir plus haut, plancher 12px).
- Les flèches typographiques (→ ← ↩ etc.) utilisées en ligne dans du texte,
  qui ne jouent pas un rôle d'icône.

Prochain emoji-icône ajouté à une page : vérifier d'abord si sa valeur finit
par transiter par un de ces chemins avant de le convertir — c'est la
majorité des erreurs rencontrées lors de cette conversion.

## Ce qui reste ouvert

- Thème sombre : en cours de finition (2026-08) — une bascule fonctionnelle
  existe déjà (`pages/mon_profil.php`, section « Apparence », JS
  `setThemePref()`, persistée dans `localStorage['ds-theme-pref']`) avec
  anti-FOUC dans `templates/header.php`, mais la couverture CSS
  `[data-theme="dark"]` reste partielle — la plupart des pages n'ont aucune
  règle sombre sur leur propre `<style>` local.
