# DigiStock — Contexte projet pour Claude Code

## Présentation

Application web de gestion de stock industriel (bobines, équipements, EMUCI, opérations terrain).
**Nom interne :** DigiStock / StockApp
**GitHub :** RUTHAXELLE/stockapp, branche `main` (Render/PostgreSQL, déployée) — voir aussi la branche parallèle `vps-mysql` ci-dessous
**Production :** https://stockapp-p8us.onrender.com (auto-déployé depuis GitHub `main` via Render + Docker, service `stockapp`, région Frankfurt)

---

## Stack technique

| Couche | Technologie |
|---|---|
| Langage | PHP 8.2 (pas de framework) |
| Base de données | PostgreSQL (Neon, PDO `pdo_pgsql`, `sslmode=require`) — migré depuis MySQL le 2026-07-21 |
| Hébergement | Render (Docker, `php -S 0.0.0.0:$PORT`) |
| Dev local | Config par variables d'environnement (`includes/db.php` → `env()`), valeurs par défaut PostgreSQL local si absentes |
| Export Excel | `phpoffice/phpspreadsheet ^1.29` |
| Export PDF | `dompdf/dompdf ^3.1` |
| Export PPTX | ZipArchive natif PHP (Open XML manuel) |
| Icons | Phosphor Icons (CDN) |

---

## Structure des fichiers

```
stockapp/
├── index.php                   # Entrée principale (routeur simple)
├── login.php / logout.php
├── Dockerfile                  # php:8.2-cli + pdo_pgsql + php -S (Render)
├── render.yaml                 # Render Blueprint (service web + env vars sync:false)
├── composer.json
├── includes/
│   ├── db.php                  # PDO, constantes DB, helpers SQL
│   ├── session.php             # Auth, permissions, current_user()
│   ├── helpers.php             # h(), fmt_date(), pagination(), etc.
│   └── groupes_config.php      # Groupes de menus par rôle
├── pages/
│   ├── admin/
│   │   ├── users.php           # Gestion utilisateurs
│   │   ├── permissions.php     # Matrice droits rôle × module
│   │   └── audit.php           # Journal d'audit
│   ├── operations/
│   │   ├── point_journalier.php  # Points journaliers coordinateur + réponse corrections GSB
│   │   └── ...
│   ├── stock_bobines_vue.php   # Vue stock bobines par site (+ export XLSX/PPTX)
│   ├── inventaire_bobines.php
│   ├── validation_stock_matin.php  # Validation stock matin GSB + demande corrections bobines
│   ├── rapports_gsb.php        # Rapports & exports GSB (Excel 3 feuilles + PDF)
│   ├── commandes_bobines.php
│   ├── import_emuci.php
│   ├── point_emuci.php
│   ├── dashboard.php
│   ├── equipements.php
│   ├── interventions.php
│   └── ...
├── templates/
│   ├── header.php              # Nav principale avec groupes de menus
│   ├── footer.php
│   └── 403.php
└── sql/
    └── stockapp.sql            # Schéma + données initiales
```

---

## Fonctions clés — includes/db.php

```php
get_db(): PDO              // singleton PDO
db_query(sql, params): PDOStatement
db_fetch_all(sql, params): array
db_fetch_one(sql, params): ?array
db_fetch_value(sql, params): mixed   // première colonne, première ligne
db_last_id(): string
db_begin() / db_commit() / db_rollback()
```

**Constantes disponibles partout :** `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE`, `APP_URL`, `APP_NAME`, `APP_TIMEZONE`

---

## Fonctions clés — includes/session.php

```php
current_user(): ?array       // charge user + role_slug + site_nom + délégations depuis DB (cache statique)
is_logged_in(): bool
require_auth(): void         // redirige vers login.php si non connecté
can(module, droit): bool     // vérifie un droit (admin/superadmin = tout)
require_permission(module, droit): void  // 403 si refusé
json_response(success, message, data): never
is_ajax(): bool
logout(): void
```

**Droits possibles :** `can_read`, `can_create`, `can_update`, `can_delete`, `can_export`

---

## Fonctions clés — includes/helpers.php

```php
h(str): string               // htmlspecialchars sécurisé pour output HTML
fmt_date(date, format): string
fmt_datetime(dt): string
fmt_number(n, decimals): string
validate_input(fields, source): array  // ['data'=>..., 'errors'=>..., 'valid'=>bool]
pagination(total, per_page, current, base_url): string
calc_fin_cycle(date_mise_en_service, duree_vie_mois): ?string
fin_cycle_class(date_fin): string  // classe CSS 'text-danger', 'text-warning', 'text-success'
```

---

## Système de rôles et permissions

### Rôles (role_slug dans la table `roles`)

| Slug | Description |
|---|---|
| `superadmin` | Tous les droits sans restriction |
| `admin` | Tous les droits |
| `gestionnaire_stock_bobines` (GSB) | Stock + bobines + opérations |
| `gestionnaire_stock` | Stock + bobines + opérations |
| `superviseur_operation` | Dashboard + stock + opérations + bobines + rapports |
| `coordinateur_site` | Dashboard + stock + bobines + opérations (terrain) |
| `gestionnaire_operation` | Dashboard + opérations (+ délégations éventuelles) |
| `controleur_production` | Dashboard + bobines + opérations |
| `lecteur` | Lecture seule (DG/PDG) |
| `maintenance_info` / `superviseur_it` | Dashboard + stock + informatique |
| `support_it` | Sous-rôles configurables (maintenance, bobines, production) |

### Logique de permissions

- `admin` et `superadmin` → `can()` retourne toujours `true`
- Les autres rôles → vérification en BDD dans la table `permissions` (role_id × module)
- `support_it` → sous-rôles dans `support_it_roles` → modules autorisés définis dans `_support_it_can()`
- `gestionnaire_operation` → peut recevoir des délégations (table `delegations`) qui étendent ses droits sur des modules spécifiques
- `_check_permission_db()` utilise un **cache statique** par requête (clé `role_id:module:droit`)

---

## Groupes de menus

Définis dans `includes/groupes_config.php`. 7 groupes : `DASHBOARD`, `STOCK`, `BOBINES`, `OPERATIONS`, `INFORMATIQUE`, `RAPPORTS`, `ADMINISTRATION`.

Chaque groupe a une `first_page` et des `nav` items avec filtres optionnels `perm`, `roles_include`, `roles_exclude`.

Fonctions utiles :
```php
get_groupes_utilisateur(): array      // groupes visibles pour l'utilisateur connecté
get_groupe_nav_items(slug): array     // items nav filtrés par permissions
```

---

## Configuration Render / environnement

**`includes/db.php`** lit tout via `env()` (getenv + $_ENV + $_SERVER), sans aucun secret en dur dans le code. Variables attendues : `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE` (`require` pour Neon), `APP_URL`. En local, des valeurs par défaut PostgreSQL (`localhost:5432`) s'appliquent si les env vars sont absentes.

Sur Render, les env vars sont définies dans le dashboard du service (`render.yaml` les déclare en `sync:false`, à renseigner manuellement) — DB pointant vers le projet Neon `erp-emuci-stockapp` (Frankfurt).

> **Dette de sécurité historique :** l'ancien mot de passe MySQL Railway est resté en clair dans l'historique Git (avant la migration du 2026-07-21) — à révoquer si ce n'est pas déjà fait. Le mot de passe admin par défaut `Admin@2024` est aussi à changer en prod.

---

## Branche parallèle `vps-mysql`

Pour un déploiement sur le VPS de l'utilisateur (Ubuntu + Apache + PHP 8.4 + MySQL 8), il existe une branche séparée **`vps-mysql`** (dialecte MySQL d'origine, `includes/db.php` en `pdo_mysql`, mêmes principes env-based). Guide : `DEPLOY-VPS-MYSQL.md`.

**Règle importante :** ne jamais merger `vps-mysql` → `main` (casserait Render/PostgreSQL — dialectes SQL incompatibles). Toute feature touchant du SQL doit être portée manuellement sur les deux branches ; les changements purement front-end (HTML/CSS/JS, comme la réactivité de certaines pages) n'ont besoin d'être portés que si le fichier diverge déjà entre les deux branches.

---

## Flux de correction bobines (GSB → Coordinateur)

Table `corrections_bobines` (créée via `create_missing_tables.php?secret=emuci2026import`).

**Workflow :**
1. GSB voit un écart dans `validation_stock_matin.php` → clique "Demander modif" sur une bobine
2. Mini-modal : comparaison Films PJ vs EMUCI, champ "Films proposés" + motif
3. AJAX `demander_correction_bobine` → INSERT dans `corrections_bobines` (statut `en_attente`) + notification `info` au coordinateur
4. Coordinateur voit le panneau jaune dans `point_journalier.php` (chargé au rendu PHP)
5. Clic "Répondre" → modal avec 3 options :
   - **Confirmer** → `op_films_utilises` ajusté, `op_bobines.films_restants` corrigé, `validations_stock_matin.statut='reajuste'`
   - **Contre-proposer** → même ajustement stock, notification GSB avec la valeur alternative
   - **Refuser** → aucun ajustement stock, notification GSB avec motif
6. AJAX `repondre_correction` gère la transaction + audit_log + notification GSB

**Champs table `corrections_bobines` :** `id`, `point_id`, `bobine_id`, `site_id`, `date_point`, `films_original`, `films_proposes`, `motif_gsb`, `gsb_id`, `statut` enum(`en_attente`,`approuvee`,`contreproposee`,`refusee`), `coord_id`, `reponse_coord`, `films_final`, `traite_at`, `created_at`

---

## Permissions GSB — règle importante

**Ne jamais utiliser `require_permission()` pour les pages spécifiques GSB.** Le rôle `gestionnaire_stock_bobines` n'a pas toujours de permissions explicites en BDD pour tous les modules. Utiliser à la place un contrôle par liste de rôles :

```php
$gsb_roles = ['admin','superadmin','gestionnaire_stock_bobines','gestionnaire_stock','superviseur_operation'];
if (!in_array($user['role_slug'] ?? '', $gsb_roles)) {
    http_response_code(403); include __DIR__.'/../templates/403.php'; exit;
}
```

---

## Rapports & Exports GSB (`pages/rapports_gsb.php`)

Accessible via menu **Bobines → Rapports & Exports** pour GSB, gestionnaire_stock, superviseur_operation, admin.

Exports disponibles (filtres : date_from, date_to, site) :
- **xlsx_journalier** → 3 feuilles : Consommations détail / Stock actuel / Historique validations
- **pdf_journalier** → Tableau conso + totaux + état stock par bobine (paysage A4)
- **xlsx_mensuel** → Synthèse groupée par mois × site
- **pdf_mensuel** → Tableau mensuel avec totaux (paysage A4)
- **pdf_validations** → Historique des validations avec statuts colorés

Les `use PhpOffice\...` sont obligatoirement **en tête de fichier** (avant tout code exécutable).

---

## Notifications — contrainte enum

`notifications.type` n'accepte que : `'fin_cycle'`, `'stock_bas'`, `'alerte_conso'`, `'info'`.
Toutes les notifications applicatives (corrections, validations, blocages) utilisent `'info'`.

---

## validation_stock_matin.php — fonctionnalités

- **Vue GSB** : 2 onglets (Validation en cours / Historique 30 jours), bandeau info, filtres, légende statuts
- **Colonne Action / bouton "Demander modif"** : visible uniquement pour GSB (`$can_valider`), masqué pour coordinateur (PHP injecte `true`/`false` dans le JS au rendu)
- **Statuts affichés** : `Conforme` (vert) / `Avec écart` (orange) / `Réajusté` (bleu) / `Bloqué` (rouge)

---

## point_journalier.php — corrections en attente

- Panneau jaune en haut : corrections `en_attente` pour le site du coordinateur, chargées au rendu PHP
- AJAX `get_corrections_coord` : liste (non utilisé côté HTML, réservé usage futur)
- AJAX `repondre_correction` : accessible coordinateur_site + admin/superadmin uniquement (vérif `$user['role_slug']`)
- Bugs corrigés :
  - **Brouillon reset à zéro** : `editPoint` JS restaure tous les champs (véhicules, bobines, rivets)
  - **Double déduction stock** : le chemin UPDATE restaure `films_restants` avant DELETE + re-INSERT
  - **Point vide soumis** : `soumettre_point` et `valider_point_coord` vérifient `COUNT(*) FROM op_films_utilises`

---

## Export PPTX — notes importantes

Le fichier `pages/stock_bobines_vue.php` génère un PPTX via `ZipArchive::addFromString()` (pas de répertoire temporaire).

Règles Open XML à respecter si tu modifies ce code :
- **Ne jamais mettre `<p:ph type="body"/>` dans un slide** si le layout référencé est "blank" (pas de placeholder body → PowerPoint rejette le fichier)
- Les text boxes utilisent `<p:cNvSpPr txBox="1"/>` sans `<p:ph>`
- Le fond de slide se définit via `<p:bg><p:bgPr>` et non via une forme rectangle
- Toujours utiliser `tempnam(sys_get_temp_dir(), 'pptx_')` pour le fichier temp, puis `unlink()` après `readfile()`

---

## Export XLSX — notes importantes

Les `use PhpOffice\PhpSpreadsheet\...` **doivent être au niveau fichier** (pas dans un bloc `if`).
PHP interdit les alias `use` à l'intérieur de fonctions ou conditions.

Dans `stock_bobines_vue.php`, l'alias est `XlsxWriter` (pas `Xlsx`) pour éviter le conflit avec la variable string `$export === 'xlsx'`.

---

## CSS — conventions visuelles

Variables CSS globales (définies dans `templates/header.php`) :
```css
--navy: #06033A
--blue: #1B75BC
--border: #e2e8f0
--muted: #94a3b8
```

**Piège CSS connu :** `.vue-table tbody tr:nth-child(even) td` a une spécificité plus haute que `.vue-table tr.total-row td` (à cause de `tbody`). Pour forcer le style sur la ligne total, utiliser `!important` sur les règles total-row.

---

## Pages à ne pas oublier

- `fix_columns.php` à la racine — script de migration one-shot, **à supprimer** une fois les migrations confirmées
- `pages/admin/permissions.php` — vérifier que `coordinateur_site` a `can_create` + `can_update` sur `inventaire_bobines`

---

## Tâches en cours / backlog

- [ ] Supprimer `fix_columns.php` (migration one-shot terminée)
- [ ] Déploiement VPS : Nginx/Apache + PHP-FPM + MySQL + Certbot (branche `vps-mysql`, guide `DEPLOY-VPS-MYSQL.md`)
- [ ] Vérifier permissions `coordinateur_site` sur `inventaire_bobines` dans Admin → Permissions
- [ ] Révoquer l'ancien mot de passe MySQL Railway exposé dans l'historique Git ; changer le mot de passe admin par défaut `Admin@2024`
- [x] Migration MySQL → PostgreSQL/Neon + déploiement Render (2026-07-21, branche `main`)
- [x] Flux correction bobines GSB → Coordinateur (table `corrections_bobines` + UI des deux côtés)
- [x] Page Rapports & Exports GSB (`pages/rapports_gsb.php`)
- [x] validation_stock_matin.php : onglets, filtres, légende, masquage bouton coordinateur
- [x] point_journalier.php : restauration brouillon, double déduction stock, point vide bloqué
- [x] Recherche + filtres réactifs sur la page Utilisateurs (`pages/admin/users.php`)

---

## Commandes utiles

```bash
# Déployer (push suffit — Render auto-déploie depuis GitHub main)
git push origin main

# Voir les logs Render
# Dashboard Render → service stockapp → Logs

# Charger le schéma PostgreSQL sur Neon
psql "<connection string Neon>" < sql/stockapp_pg.sql
```
