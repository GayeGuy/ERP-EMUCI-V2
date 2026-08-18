#!/bin/sh
# ============================================================
#  Applique les migrations du module Achats sur une base distante
#  (Neon), pour monter un environnement de recette.
# ============================================================
#
#  Neon ne passe jamais par docker/initdb.sh — celui-ci ne s'exécute qu'à
#  la création d'un volume Docker local. Les migrations doivent donc être
#  appliquées à la main, une fois, sur la base de recette.
#
#  Usage :
#     sh sql/recette_achats_migrations.sh "postgresql://user:pass@host/db?sslmode=require"
#
#  Le script suppose que la base porte déjà le schéma historique (tables
#  users, sites, roles, articles…). C'est le cas d'une branche Neon créée
#  depuis la production. Sur une base vide, charger d'abord stockapp_pg.sql
#  et les migrations antérieures — voir la liste dans docker/initdb.sh.
#
#  Les migrations sont idempotentes (IF NOT EXISTS, ON CONFLICT) : les
#  rejouer ne casse rien. ON_ERROR_STOP=0 pour qu'une instruction portant
#  sur un objet déjà présent n'interrompe pas la suite.

set -u

if [ $# -lt 1 ]; then
  echo "Usage : sh $0 \"<chaine de connexion postgresql>\"" >&2
  exit 1
fi

DSN="$1"
DOSSIER=$(dirname "$0")
JOURNAL=$(mktemp)

# Dans l'ordre d'écriture. Il n'y a pas de 09 : le numéro n'a jamais servi.
MIGRATIONS="
migration_achats_01_schema.sql
migration_achats_02_referentiels.sql
migration_achats_03_permissions.sql
migration_achats_04_bigint.sql
migration_achats_05_arbitrage.sql
migration_achats_06_derogation.sql
migration_achats_07_visas.sql
migration_achats_08_syscohada.sql
migration_achats_10_reception.sql
migration_achats_11_pdf_dashboard.sql
migration_achats_12_stock_departement.sql
migration_achats_13_budget_validation.sql
migration_achats_14_dedup_departements.sql
migration_achats_15_fournisseur_conformite.sql
"

echo "Base de recette : $(echo "$DSN" | sed 's#://[^@]*@#://***@#')"
echo "───────────────────────────────────────────────"

for f in $MIGRATIONS; do
  if [ ! -f "$DOSSIER/$f" ]; then
    echo "  $f : INTROUVABLE — ignoré"
    continue
  fi
  psql "$DSN" -q -v ON_ERROR_STOP=0 -f "$DOSSIER/$f" > /tmp/sortie_migration 2>&1 || true
  cat /tmp/sortie_migration >> "$JOURNAL"
  # « error » sans ancrage ni casse : psql préfixe ses erreurs SQL par
  # « psql:fichier:12: ERROR: » et ses propres erreurs par « psql: error: ».
  NB=$(grep -ci 'error' /tmp/sortie_migration || true)
  printf "  %-48s %s erreur(s)\n" "$f" "$NB"
done

echo "───────────────────────────────────────────────"
echo "Contrôle du résultat"
psql "$DSN" -tAc "SELECT 'tables Achats : ' || count(*) FROM information_schema.tables
                   WHERE table_schema='public'
                     AND table_name IN ('feb','feb_lignes','feb_offres','feb_suivi','feb_receptions',
                                        'fournisseurs','familles_achat','achat_paliers','achat_types',
                                        'lignes_budgetaires','budget_validations','stock_departement')"
psql "$DSN" -tAc "SELECT 'droits Achats : ' || count(*) FROM permissions
                   WHERE module LIKE 'achats%'" 2>/dev/null || echo "droits Achats : table permissions absente"
psql "$DSN" -tAc "SELECT 'paliers de visa : ' || count(*) FROM achat_paliers WHERE actif=1" 2>/dev/null || true
psql "$DSN" -tAc "SELECT 'départements : ' || count(*) FROM departements" 2>/dev/null || true

echo "───────────────────────────────────────────────"
echo "Erreurs distinctes (vides = tout est passé)"
grep -i 'error' "$JOURNAL" | sed 's/^psql:[^:]*:[0-9]*: //; s/LINE [0-9]*.*//' \
  | sort | uniq -c | sort -rn | head -20
rm -f "$JOURNAL"
