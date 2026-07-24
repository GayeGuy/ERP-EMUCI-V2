# Déploiement ERP EMUCI sur VPS (Apache + PHP 8.4 + MySQL 8)

Cette branche **`vps-mysql`** fait tourner l'application sur la pile **déjà présente
sur le VPS** : PHP 8.4, MySQL 8, Apache. **Aucune base à installer** (MySQL est là),
**aucune dépendance à télécharger** : le dossier `vendor/` (PhpSpreadsheet, Dompdf)
est **committé dans la branche**. Un `git clone` suffit, l'app est prête.

> La branche `main` reste en PostgreSQL pour Render/Neon. Cette branche `vps-mysql`
> est dédiée au VPS et utilise MySQL — les deux ne se marchent pas dessus.

Testé de bout en bout sous **MySQL 8.0 + PHP 8.4** (24 pages parcourues, connexion
comprise, sans erreur).

---

## 1. Prérequis PHP (probablement déjà là)

Le module PHP d'Apache et les extensions utilisées par l'app :

```bash
apt-get install -y libapache2-mod-php8.4 \
    php8.4-mysql php8.4-gd php8.4-zip php8.4-mbstring php8.4-xml php8.4-curl
a2enmod rewrite php8.4
```

- `php8.4-mysql` : connexion PDO MySQL (indispensable).
- `php8.4-gd`, `php8.4-zip`, `php8.4-mbstring`, `php8.4-xml` : exports Excel / PDF / PPTX.

## 2. Créer la base MySQL

```bash
mysql -u root -p
```

```sql
CREATE DATABASE stockapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stockapp'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON stockapp.* TO 'stockapp'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Récupérer le code (branche vps-mysql)

```bash
cd /var/www
git clone -b vps-mysql https://github.com/RUTHAXELLE/stockapp.git stockapp
chown -R www-data:www-data /var/www/stockapp
```

> Dépôt privé → utiliser un token : `https://VOTRE_TOKEN@github.com/RUTHAXELLE/stockapp.git`

## 4. Charger le schéma + les données

Un seul fichier fait tout (tables, données de démo, et alignement des colonnes
de production — le patch en fin de fichier est idempotent) :

```bash
mysql -u stockapp -p stockapp < /var/www/stockapp/sql/stockapp.sql
```

Puis charger le module **Demandes internes** (tables `di_*`, départements, rôles
RAF/DAF/Directeur Général, permissions — idempotent) :

```bash
mysql -u stockapp -p stockapp < /var/www/stockapp/sql/demandes_internes_mysql.sql
```

## 5. Configurer les identifiants

Créer le fichier `.env` à la racine (chargé automatiquement par `includes/db.php`) :

```bash
cat > /var/www/stockapp/.env <<'EOF'
DB_HOST=localhost
DB_PORT=3306
DB_NAME=stockapp
DB_USER=stockapp
DB_PASS=MOT_DE_PASSE_FORT
APP_URL=https://votre-domaine.ci
APP_TIMEZONE=Africa/Abidjan
EOF
chown www-data:www-data /var/www/stockapp/.env
chmod 640 /var/www/stockapp/.env
```

> `APP_URL` doit correspondre au domaine public : l'app y redirige après connexion.

## 6. VirtualHost Apache

`/etc/apache2/sites-available/stockapp.conf` (adapter le domaine, dont
l'enregistrement DNS A pointe vers le VPS) :

```apache
<VirtualHost *:80>
    ServerName votre-domaine.ci
    DocumentRoot /var/www/stockapp

    <Directory /var/www/stockapp>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/stockapp-error.log
    CustomLog ${APACHE_LOG_DIR}/stockapp-access.log combined
</VirtualHost>
```

Le `.htaccess` fourni gère la réécriture et bloque déjà `includes/`, `templates/`,
`cron/`, `vendor/`. `AllowOverride All` est donc requis.

```bash
a2ensite stockapp
systemctl reload apache2
```

## 7. HTTPS

```bash
apt-get install -y certbot python3-certbot-apache
certbot --apache -d votre-domaine.ci
```

## 8. Première connexion & sécurité

- URL : `https://votre-domaine.ci/login.php`
- Compte par défaut : **`admin@stockapp.local`**. Le mot de passe est celui de la
  base importée — **changez-le immédiatement** (Administration → Utilisateurs), ou
  réinitialisez-le en base si vous ne le connaissez pas :
  ```sql
  -- hash à générer : php -r "echo password_hash('NouveauMotDePasse', PASSWORD_BCRYPT);"
  UPDATE users SET password_hash='<hash>' WHERE email='admin@stockapp.local';
  ```
- ⚠️ **Révoquer** l'ancien mot de passe MySQL Railway qui figure en clair dans
  l'historique Git (`includes/db.php` d'avant la refonte). Le nouveau `db.php` ne
  contient plus aucun secret : tout passe par le `.env`.

## 9. Mises à jour

```bash
cd /var/www/stockapp && git pull
# si le schéma a évolué, recharger sql/stockapp.sql est sans risque (patch idempotent)
```

---

## Notes techniques

- **Retour à MySQL** : cette branche restaure les requêtes en syntaxe MySQL (la
  branche `main` les avait converties en PostgreSQL pour Render). `includes/db.php`
  se connecte en MySQL via PDO (`utf8mb4`) et lit sa config dans les variables
  d'environnement / `.env`.
- **Schéma complet** : `sql/stockapp.sql` = dump de base + patch d'alignement
  idempotent en fin de fichier (colonnes ajoutées après le dump : `type_rivet`,
  colonnes rivets/corrections des points journaliers, colonnes d'import Optotrace/
  Optoplate, validation des commandes, distributions…). Compatible MySQL 8
  (`DEFAULT (curdate())` parenthésé).
- **Dépendances** : `vendor/` est committé dans cette branche → pas de `composer
  install` sur le VPS. Pour les régénérer : `composer install --no-dev`.
- **`uploads/`** : dossier d'upload (fiches, BL). Vérifier qu'il est accessible en
  écriture par `www-data` : `chown -R www-data:www-data /var/www/stockapp/uploads`.
