# DigiStock — Contexte projet pour Claude Code

## Présentation

Application web de gestion de stock industriel (bobines, équipements, EMUCI, opérations terrain).
**Nom interne :** DigiStock / StockApp
**GitHub :** RUTHAXELLE/stockapp, branche `main`
**Production :** https://stockapp-production-e306.up.railway.app (auto-déployé depuis GitHub via Railway + Docker)

---

## Stack technique

| Couche | Technologie |
|---|---|
| Langage | PHP 8.2 (pas de framework) |
| Base de données | MySQL (PDO, `utf8mb4`) |
| Hébergement | Railway (Docker, `php -S 0.0.0.0:8080`) |
| Dev local | XAMPP (localhost, DB `stockapp`) |
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
├── Dockerfile                  # php:8.2-cli + php -S (Railway)
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
│   ├── stock_bobines_vue.php   # Vue stock bobines par site (+ export XLSX/PPTX)
│   ├── inventaire_bobines.php
│   ├── validation_stock_matin.php
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

**Constantes disponibles partout :** `DB_HOST`, `DB_PORT`, `DB_NAME`, `APP_URL`, `APP_NAME`, `APP_TIMEZONE`

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

## Configuration Railway / environnement

**`includes/db.php`** détecte l'environnement :
- Railway : `file_exists('/.dockerenv')` → utilise les constantes hardcodées Railway
- Local : connexion `localhost` / `stockapp` / `root` / pas de mot de passe

> **⚠️ Problème connu :** Les credentials Railway sont en clair dans le code committé sur GitHub.
> Pour un VPS : migrer vers `getenv('DB_HOST')` etc. et stocker les secrets en variables d'environnement.

`APP_URL` est hardcodé sur l'URL Railway même en local. Cela peut causer des redirections login vers le domaine Railway depuis XAMPP — normal, c'est intentionnel pour la démo.

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

- `fix_columns.php` à la racine — script de migration one-shot, **à supprimer** une fois les migrations Railway confirmées
- `pages/admin/permissions.php` — vérifier que `coordinateur_site` a `can_create` + `can_update` sur `inventaire_bobines`

---

## Tâches en cours / backlog

- [ ] Migrer `includes/db.php` pour utiliser des variables d'environnement (`getenv()`) au lieu des credentials hardcodés
- [ ] Supprimer `fix_columns.php` (migration one-shot terminée)
- [ ] Déploiement VPS : Nginx + PHP-FPM 8.2 + MySQL + Certbot (guide préparé, VPS non encore provisionné)
- [ ] Vérifier permissions `coordinateur_site` sur `inventaire_bobines` dans Admin → Permissions

---

## Commandes utiles

```bash
# Dev local XAMPP
# Ouvrir http://localhost/stockapp/

# Déployer (push suffit — Railway auto-déploie depuis GitHub main)
git push origin main

# Voir les logs Railway
# Dashboard Railway → service stockapp → Deployments → logs
```
