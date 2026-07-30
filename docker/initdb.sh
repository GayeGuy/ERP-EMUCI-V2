#!/bin/sh
# ============================================================
#  Chargement de la base locale de développement
# ============================================================
#
#  Exécuté une seule fois, à la création du volume de la base. Charge le
#  dump PostgreSQL puis les migrations, dans l'ordre où elles ont été
#  écrites.
#
#  Volontairement tolérant aux erreurs : le dump date de juin 2026 et
#  certaines migrations recouvrent en partie ce qu'il contient déjà. Une
#  instruction qui échoue parce que l'objet existe déjà ne doit pas laisser
#  une base à moitié chargée. Le bilan est affiché à la fin — le lire, car
#  c'est là qu'on voit si une migration n'est pas passée du tout.

set -u

PSQL="psql -q -v ON_ERROR_STOP=0 -U $POSTGRES_USER -d $POSTGRES_DB"
JOURNAL=/tmp/initdb.log
: > "$JOURNAL"

# Le dump d'abord : il crée les tables que les migrations modifient.
FICHIERS="
stockapp_pg.sql
migration_stockapp_complet.sql
demandes_internes_pg.sql
migration_departements.sql
migration_di_roles_dept.sql
migration_raf_daf.sql
migration_roles_validation_erp.sql
migration_articles_commandes.sql
migration_demandes_correction_saisie.sql
"

for f in $FICHIERS; do
  if [ ! -f "/sql/$f" ]; then
    echo "initdb : /sql/$f introuvable, ignoré"
    continue
  fi
  echo "initdb : chargement de $f"
  $PSQL -f "/sql/$f" 2>&1 | tee -a "$JOURNAL" | grep -c '^ERROR' > /tmp/n || true
  echo "initdb :   $(cat /tmp/n) erreur(s)"
done

echo "───────────────────────────────────────────────"
echo "initdb : bilan"
echo "  tables créées      : $($PSQL -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'")"
echo "  utilisateurs       : $($PSQL -tAc "SELECT count(*) FROM users" 2>/dev/null || echo 'table absente')"
echo "  rôles              : $($PSQL -tAc "SELECT count(*) FROM roles" 2>/dev/null || echo 'table absente')"
echo "  sites              : $($PSQL -tAc "SELECT count(*) FROM sites" 2>/dev/null || echo 'table absente')"
echo "  erreurs au total   : $(grep -c '^ERROR' "$JOURNAL" || true)"
echo "───────────────────────────────────────────────"
echo "initdb : détail des erreurs distinctes"
grep '^ERROR' "$JOURNAL" | sed 's/LINE [0-9]*.*//' | sort | uniq -c | sort -rn | head -25
echo "───────────────────────────────────────────────"
