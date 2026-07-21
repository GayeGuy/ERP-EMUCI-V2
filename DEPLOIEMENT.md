# Déploiement ERP EMUCI sur Render + Neon (PostgreSQL)

Cette application a été **migrée de MySQL vers PostgreSQL** pour être hébergée sur
**Render** (application PHP en Docker) avec **Neon** (base PostgreSQL serverless).

---

## 1. Créer la base Neon

1. Créer un compte sur https://neon.tech → **New Project**.
   - Région conseillée : **Europe (Frankfurt)**.
2. Copier la **connection string** (bouton *Connect*) :
   ```
   postgresql://USER:PASSWORD@ep-xxxx.eu-central-1.aws.neon.tech/DBNAME?sslmode=require
   ```
3. Charger le schéma + les données converties (`sql/stockapp_pg.sql`) :
   ```bash
   psql "postgresql://USER:PASSWORD@ep-xxxx.../DBNAME?sslmode=require" -f sql/stockapp_pg.sql
   ```
   > Si vous n'avez pas `psql` en local : ouvrez l'éditeur SQL de Neon et collez
   > le contenu de `sql/stockapp_pg.sql`, ou lancez la commande depuis Docker :
   > ```bash
   > docker run --rm -v "$PWD/sql:/sql" postgres:16-alpine \
   >   psql "postgresql://USER:PASSWORD@ep-xxxx.../DBNAME?sslmode=require" -f /sql/stockapp_pg.sql
   > ```

## 2. Déployer sur Render

### Option A — via le Blueprint (`render.yaml`)
1. Pousser le code sur GitHub.
2. Render → **New → Blueprint** → sélectionner le dépôt.
3. Render détecte `render.yaml` et crée le service. Renseigner les variables
   marquées « à définir » (voir section 3).

### Option B — manuellement
1. Render → **New → Web Service** → connecter le dépôt GitHub.
2. **Runtime : Docker** (le `Dockerfile` est détecté automatiquement).
3. Plan : Free (ou payant pour éviter la mise en veille).
4. Ajouter les variables d'environnement (section 3) puis **Create Web Service**.

## 3. Variables d'environnement (Render → Environment)

| Clé          | Valeur                                             |
|--------------|----------------------------------------------------|
| `DB_HOST`    | `ep-xxxx.eu-central-1.aws.neon.tech` (hôte Neon)   |
| `DB_PORT`    | `5432`                                             |
| `DB_NAME`    | nom de la base Neon                                |
| `DB_USER`    | utilisateur Neon                                   |
| `DB_PASS`    | mot de passe Neon                                  |
| `DB_SSLMODE` | `require`  (obligatoire pour Neon)                 |
| `APP_URL`    | `https://<votre-service>.onrender.com`             |
| `APP_TIMEZONE` | `Africa/Abidjan`                                 |

Aucun secret n'est stocké dans le code : tout passe par ces variables
(voir `includes/db.php` et `.env.example`).

## 4. Première connexion

- URL : `https://<votre-service>.onrender.com/login.php`
- Compte par défaut : `admin@stockapp.local` — **changez le mot de passe immédiatement.**

## 5. Sécurité — à faire absolument

- **Révoquer** l'ancien mot de passe MySQL Railway qui figurait en clair dans
  l'ancien `includes/db.php` (et donc potentiellement dans l'historique Git public).
- Vérifier qu'aucun secret n'est committé (`includes/db.php` lit désormais `getenv`).
- Changer le mot de passe admin par défaut.

## Notes techniques (migration MySQL → PostgreSQL)

- Schéma converti : `sql/stockapp_pg.sql` (généré depuis `sql/stockapp.sql`).
  Types adaptés (`SERIAL`, `TIMESTAMP`, `TEXT`…), séquences recréées, vues MySQL
  non utilisées retirées, colonnes de production réalignées (patch idempotent en
  fin de fichier).
- Requêtes applicatives adaptées : `ON DUPLICATE KEY` → `ON CONFLICT`,
  `IFNULL`→`COALESCE`, `DATE_FORMAT`→`TO_CHAR`, `DATE_SUB/ADD`→`INTERVAL`,
  `DATEDIFF`→soustraction de dates, `YEAR/MONTH`→`EXTRACT`, `IF()`→`CASE WHEN`,
  `GROUP_CONCAT`→`STRING_AGG`, `FIELD()`→`array_position`, `TIMESTAMPDIFF`→`age()`.
- Plan gratuit : Render **et** Neon mettent le service en veille après inactivité
  (réveil ~30 s). Passez à un plan payant pour un usage continu.
