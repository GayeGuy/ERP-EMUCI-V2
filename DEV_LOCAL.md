# Développement local

Jusqu'ici le code ne pouvait être que relu : PHP n'est pas installé sur la
machine, donc toute vérification passait par de l'analyse statique ou un banc
de test HTML reconstitué. Plusieurs conclusions fausses en sont venues.

L'application tourne maintenant en local, dans deux conteneurs.

## Démarrer

```bash
docker compose up -d --build
```

Puis <http://localhost:8080>. Dans VS Code, `Ctrl+Shift+B` fait la même chose,
et les autres commandes sont dans **Tasks: Run Task**.

Le code est monté depuis le disque : un fichier enregistré est pris en compte
au rechargement de la page, sans reconstruire l'image.

## Ce que contient la base

Le dump `sql/stockapp_pg.sql` puis les migrations de `sql/`, dans l'ordre où
elles ont été écrites — 71 tables, 17 rôles, 21 sites. Le chargement n'a lieu
qu'à la création du volume. Pour repartir de zéro :

```bash
docker compose down -v && docker compose up -d
```

Le bilan du chargement s'affiche dans `docker compose logs db` : il indique le
nombre de tables et les erreurs SQL éventuelles. Le lire, c'est là qu'on voit
si une migration n'est pas passée.

## Comptes de test

Ils n'existent que dans la base locale et ne sont pas créés automatiquement :
c'est volontaire, aucun compte à mot de passe connu ne doit pouvoir se
retrouver en production. Pour les (re)créer, adapter et exécuter :

```bash
docker compose exec -T app php <<'PHP'
<?php
require '/var/www/html/includes/db.php';
$hash = password_hash('devlocal2026', PASSWORD_BCRYPT, ['cost' => 12]);
$site = db_fetch_value("SELECT id FROM sites WHERE actif=1 ORDER BY id LIMIT 1");
foreach ([['lecteur',4,null],['coord',6,$site],['superv',8,null],
          ['gsb',13,null],['gestop',14,null],['super',1,null]] as [$n,$role,$sid]) {
    $mail = "$n.dev@local.test";
    $ex = db_fetch_value("SELECT id FROM users WHERE email=?", [$mail]);
    if ($ex) db_query("UPDATE users SET password_hash=?,role_id=?,site_id=?,actif=1 WHERE id=?", [$hash,$role,$sid,$ex]);
    else db_query("INSERT INTO users (nom,prenom,email,password_hash,role_id,site_id,actif) VALUES (?,?,?,?,?,?,1)",
                  ['Dev',ucfirst($n),$mail,$hash,$role,$sid]);
}
PHP
```

Le domaine doit avoir un point : `auth_login()` passe par
`FILTER_VALIDATE_EMAIL`, qui refuse `@local`.

## Rendre une page en ligne de commande

La connexion se fait en AJAX uniquement (`is_ajax()`), d'où l'en-tête :

```bash
curl -s -c j.txt -o /dev/null http://localhost:8080/login.php
curl -s -b j.txt -c j.txt -H 'X-Requested-With: XMLHttpRequest' \
     -X POST -d 'email=super.dev@local.test&password=devlocal2026' \
     http://localhost:8080/login.php
curl -s -b j.txt http://localhost:8080/pages/dashboard.php > sortie.html
```

## Viser Neon plutôt que la base locale

Ne pas mettre d'identifiants Neon dans `docker-compose.yml`. Créer un `.env`
(non versionné) et ajouter `env_file: [.env]` au service `app`. À faire avec
prudence : le conteneur écrirait alors dans la base de production.

## Ce que ce montage ne change pas

Render déploie à partir du `Dockerfile` seul et ignore `docker-compose.yml`.
Rien ici n'a d'effet sur la production.
