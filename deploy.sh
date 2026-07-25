#!/usr/bin/env bash
# ============================================================
#  deploy.sh — Déploiement / mise à jour de l'ERP EMUCI sur le VPS
#  Branche vps-mysql (Apache + PHP 8.4 + MySQL 8). Idempotent.
#
#  Étapes : git pull  ->  sauvegarde DB  ->  (1re install : schéma de base)
#           ->  schéma Demandes internes (idempotent)  ->  permissions + reload.
#
#  Usage :   sudo ./deploy.sh
#  Options (variables d'environnement) :
#     APP_DIR=/chemin        (défaut /var/www/stockapp)
#     APP_USER=www-data      propriétaire des fichiers
#     BRANCH=vps-mysql       branche git à déployer
#     SKIP_BACKUP=1          ne pas sauvegarder la base avant
#     SKIP_PULL=1            ne pas faire de git pull
#     RELOAD_APACHE=0        ne pas recharger Apache
# ============================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/stockapp}"
APP_USER="${APP_USER:-www-data}"
BRANCH="${BRANCH:-vps-mysql}"
SKIP_BACKUP="${SKIP_BACKUP:-0}"
SKIP_PULL="${SKIP_PULL:-0}"
RELOAD_APACHE="${RELOAD_APACHE:-1}"

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m/!\\\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31mERREUR:\033[0m %s\n' "$*" >&2; exit 1; }

cd "$APP_DIR" 2>/dev/null || die "Répertoire introuvable : $APP_DIR"
log "ERP EMUCI — déploiement ($APP_DIR, branche $BRANCH)"

command -v git   >/dev/null || die "git introuvable"
command -v mysql >/dev/null || die "client mysql introuvable"
[ -f .env ] || die "Fichier .env manquant ($APP_DIR/.env)"

# ── Identifiants DB depuis .env ─────────────────────────────
getenv() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^["'\'']//' -e 's/["'\'']$//'; }
DB_HOST="$(getenv DB_HOST)"; DB_HOST="${DB_HOST:-localhost}"
DB_PORT="$(getenv DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(getenv DB_NAME)"
DB_USER="$(getenv DB_USER)"
DB_PASS="$(getenv DB_PASS)"
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ] || die "DB_NAME / DB_USER absents du .env"
export MYSQL_PWD="$DB_PASS"                       # évite le mot de passe dans la liste des process
MYSQL=(mysql --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME")
"${MYSQL[@]}" -e "SELECT 1" >/dev/null 2>&1 || die "Connexion MySQL impossible (vérifier .env)"

# ── 1. Récupérer le code ────────────────────────────────────
if [ "$SKIP_PULL" != "1" ]; then
  if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Modifications locales non commitées — git pull peut échouer."
  fi
  log "git pull --ff-only origin $BRANCH"
  git pull --ff-only origin "$BRANCH"
else
  log "git pull ignoré (SKIP_PULL=1)"
fi

# ── 2. Sauvegarde de la base (avant tout changement) ────────
if [ "$SKIP_BACKUP" != "1" ]; then
  mkdir -p backups
  BK="backups/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
  log "Sauvegarde DB -> $BK"
  if command -v gzip >/dev/null; then
    mysqldump --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip > "$BK" \
      || die "Sauvegarde échouée (relancer avec SKIP_BACKUP=1 pour forcer)"
  else
    mysqldump --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" > "${BK%.gz}" \
      || die "Sauvegarde échouée (relancer avec SKIP_BACKUP=1 pour forcer)"
  fi
else
  warn "Sauvegarde DB ignorée (SKIP_BACKUP=1)"
fi

# ── 3. Schémas SQL ──────────────────────────────────────────
# Le schéma de base n'est chargé QUE si la base est vide (1re installation) :
# le recharger sur une base existante écraserait les données.
if "${MYSQL[@]}" -N -e "SELECT 1 FROM users LIMIT 1" >/dev/null 2>&1; then
  log "Base déjà initialisée — sql/stockapp.sql non rechargé (protège les données)"
else
  log "Première installation — chargement sql/stockapp.sql"
  "${MYSQL[@]}" < sql/stockapp.sql
fi

log "Module Demandes internes — sql/demandes_internes_mysql.sql (idempotent)"
"${MYSQL[@]}" < sql/demandes_internes_mysql.sql

# ── 4. Permissions + reload (si root) ───────────────────────
if [ "$(id -u)" = "0" ]; then
  log "chown -R $APP_USER:$APP_USER $APP_DIR"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
  if [ "$RELOAD_APACHE" = "1" ] && command -v systemctl >/dev/null; then
    log "reload apache2"
    systemctl reload apache2 || warn "reload apache2 échoué (à faire manuellement)"
  fi
else
  warn "Non-root : chown / reload Apache ignorés (relancer avec sudo si besoin)."
fi

log "✅ Déploiement terminé."
