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

## Thème sombre

Bascule fonctionnelle dans `pages/mon_profil.php` (section « Apparence »,
JS `setThemePref()`, persistée dans `localStorage['ds-theme-pref']`), plus
un raccourci icône ☀️/🌙 dans la topbar (`toggleThemeQuick()`, même clé
localStorage, synchronisé avec Mon Profil) — `templates/header.php` applique
l'attribut `data-theme` avant le premier paint pour éviter le flash.

La couverture CSS (2026-08) a été étendue depuis `templates/header.php` en
recensant par script tout le dépôt plutôt qu'en reprenant chaque page à la
main :
- Variables CSS pâles redéfinies pour le mode sombre (`--tertiary`,
  `--white`, `--lighter`, `--card` — cette dernière n'était même jamais
  définie ailleurs : ~20 usages `var(--card,#fff)` retombaient tous sur le
  repli blanc).
- ~100 classes de carte/panneau/filtre à fond blanc en dur (repérées par
  script, une même teinte de fond partout plutôt qu'une reprise par page).
- Dégradés décoratifs utilisant `var(--navy)` comme fond (bannières,
  en-têtes de rôle) : `--navy` redéfini en clair pour le texte les délavait
  — repointés sur la teinte navy d'origine, indépendamment du thème.
- ~25 classes à fond `var(--navy)` plein + texte blanc en dur (même bug,
  variante badge/avatar/en-tête de tableau).
- Un garde-fou générique par attribut (`[style*="background:#fff"]` etc.)
  pour les styles inline non couverts par les classes ci-dessus.
- Couleurs de texte en ligne (bleu/vert/orange/rouge, ex. `#1D4ED8`,
  `#065F46`) calibrées pour un fond blanc, éclaircies pour rester lisibles
  sur les cartes désormais sombres.

Vérifié via audit de contraste programmatique (Playwright, calcul de ratio
WCAG sur chaque nœud de texte, pas à l'œil) sur 20 pages : environ 240
échecs de contraste mesurés avant correctifs, ~15 cas résiduels après —
tous des paires couleur/fond ponctuelles (une cellule de tableau, un badge
de statut isolé) plutôt qu'un problème de lisibilité générale. Non
poursuivi au-delà : chaque cas restant nécessite de tracer une couleur en
ligne jusqu'à sa source une par une, rendement décroissant par rapport au
reste de la page. À reprendre si signalé comme gênant en usage réel.

Reste ouvert :
- Les ~15 paires résiduelles ci-dessus (voir git log du commit correspondant
  pour le détail exact des classes/pages).
- Les badges de statut pastel (fond pâle + texte saturé, ex.
  `.badge-success`, `.ac-type-badge`) sont volontairement laissés tels
  quels : ils restent lisibles indépendamment du thème (texte contrasté sur
  son propre fond), les reprendre en sombre serait cosmétique, pas
  fonctionnel.
- `pages/accueil.php`, `login.php`, `forgot-password.php`,
  `reset-password.php`, `templates/403.php` sont des pages autonomes qui
  n'incluent pas `templates/header.php` : elles ne reçoivent ni l'attribut
  `data-theme` ni le script anti-flash, et restent toujours en thème clair.
  Délibéré pour les pages pré-connexion ; `accueil.php` (page d'accueil
  post-connexion mais avec sa propre mise en page complète) est un cas
  limite non traité — l'y rattacher demanderait de dupliquer ou factoriser
  le script anti-FOUC, hors périmètre de cette passe.

