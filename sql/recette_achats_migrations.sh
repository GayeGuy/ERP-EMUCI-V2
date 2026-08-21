#!/bin/sh
# ============================================================
#  Prépare une base de recette distante (Neon) pour le module Achats
# ============================================================
#
#  Neon ne passe jamais par docker/initdb.sh — celui-ci ne s'exécute qu'à
#  la création d'un volume Docker local. Le schéma doit donc être posé à la
#  main, une fois, sur la base de recette.
#
#  Usage :
#     sh sql/recette_achats_migrations.sh "postgresql://user:pass@host/db?sslmode=require"
#
#  Le script détecte l'état de la base :
#
#    - table users absente  -> base neuve : le socle historique est chargé
#      d'abord (dump + migrations antérieures), puis les migrations Achats ;
#    - table users présente -> branche créée depuis la production : seules
#      les migrations Achats sont appliquées.
#
#  La détection évite d'avoir à choisir. Recharger le socle sur une base qui
#  le porte déjà produirait un mur d'erreurs « objet existe déjà » ; ne pas
#  le charger sur une base vide ferait échouer chaque migration sur des
#  tables absentes.
#
#  ON_ERROR_STOP=0 : le dump date de juin 2026 et certaines migrations
#  recouvrent en partie ce qu'il contient. Une instruction qui échoue parce
#  que l'objet existe déjà ne doit pas laisser une base à moitié chargée.

set -u

if [ $# -lt 1 ]; then
  echo "Usage : sh $0 \"<chaine de connexion postgresql>\"" >&2
  exit 1
fi

DSN="$1"
DOSSIER=$(dirname "$0")
JOURNAL=$(mktemp)

# Socle historique, dans l'ordre de docker/initdb.sh. Quatre fichiers de sql/
# en sont volontairement absents (stockapp.sql, migration_stockapp_complet,
# migration_articles_commandes, migration_demandes_correction_saisie) : écrits
# pour MySQL, psql les rejette en bloc et stockapp_pg.sql couvre déjà leur
# contenu.
SOCLE="
stockapp_pg.sql
demandes_internes_pg.sql
migration_departements.sql
migration_di_roles_dept.sql
migration_raf_daf.sql
migration_roles_validation_erp.sql
agents_pg.sql
add_bus_post_platform.sql
migration_permissions_point_emuci.sql
migration_permissions_27modules.sql
migration_fix_action_flags.sql
migration_fix_permissions_regressions.sql
migration_ecarts_permissions.sql
migration_site_id.sql
migration_lot1_ticket_glpi.sql
migration_lot2_role_pdg.sql
migration_lot3_roles.sql
migration_lot_sessions_inventaire.sql
migration_lot4_periode_inventaire.sql
migration_lot5_corrections_inventaire.sql
migration_lot6_autorisation_modif_inventaire.sql
"

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
migration_achats_16_visa_n1.sql
migration_achats_17_equipements_dai.sql
migration_achats_18_affectation_validee.sql
"

charger() {
  for f in $1; do
    if [ ! -f "$DOSSIER/$f" ]; then
      printf "  %-48s INTROUVABLE — ignoré\n" "$f"
      continue
    fi
    psql "$DSN" -q -v ON_ERROR_STOP=0 -f "$DOSSIER/$f" > /tmp/sortie_migration 2>&1 || true
    cat /tmp/sortie_migration >> "$JOURNAL"
    # « error » sans ancrage ni casse : psql préfixe ses erreurs SQL par
    # « psql:fichier:12: ERROR: » et ses propres erreurs par « psql: error: ».
    # Un grep ancré sur ^ERROR ne voit ni l'une ni l'autre.
    NB=$(grep -ci 'error' /tmp/sortie_migration || true)
    printf "  %-48s %s erreur(s)\n" "$f" "$NB"
  done
}

echo "Base de recette : $(echo "$DSN" | sed 's#://[^@]*@#://***@#')"
echo "───────────────────────────────────────────────"

BASE_NEUVE=$(psql "$DSN" -tAc "SELECT to_regclass('public.users') IS NULL" 2>/dev/null)
if [ "$BASE_NEUVE" != "t" ] && [ "$BASE_NEUVE" != "f" ]; then
  echo "Impossible de joindre la base — vérifiez la chaîne de connexion." >&2
  rm -f "$JOURNAL"
  exit 1
fi

if [ "$BASE_NEUVE" = "t" ]; then
  echo "Base neuve (table users absente) — chargement du socle historique"
  charger "$SOCLE"
  echo "───────────────────────────────────────────────"
else
  echo "Schéma historique déjà présent — socle non rechargé"
fi

echo "Migrations Achats"
charger "$MIGRATIONS"

echo "───────────────────────────────────────────────"
echo "Contrôle du résultat"
psql "$DSN" -tAc "SELECT '  tables               : ' || count(*) FROM information_schema.tables WHERE table_schema='public'"
psql "$DSN" -tAc "SELECT '  dont tables Achats   : ' || count(*) FROM information_schema.tables
                   WHERE table_schema='public'
                     AND table_name IN ('feb','feb_lignes','feb_offres','feb_suivi','feb_receptions',
                                        'fournisseurs','familles_achat','achat_paliers','achat_types',
                                        'lignes_budgetaires','budget_validations','stock_departement')"
psql "$DSN" -tAc "SELECT '  droits Achats        : ' || count(*) FROM permissions WHERE module LIKE 'achats%'" 2>/dev/null || true
psql "$DSN" -tAc "SELECT '  paliers de visa      : ' || count(*) FROM achat_paliers WHERE actif=1" 2>/dev/null || true
psql "$DSN" -tAc "SELECT '  familles d''achat     : ' || count(*) FROM familles_achat" 2>/dev/null || true
psql "$DSN" -tAc "SELECT '  départements         : ' || count(*) FROM departements" 2>/dev/null || true
psql "$DSN" -tAc "SELECT '  rôles                : ' || count(*) FROM roles" 2>/dev/null || true
psql "$DSN" -tAc "SELECT '  utilisateurs         : ' || count(*) FROM users" 2>/dev/null || true

echo "───────────────────────────────────────────────"
echo "Erreurs distinctes (aucune ligne = tout est passé)"
grep -i 'error' "$JOURNAL" | sed 's/^psql:[^:]*:[0-9]*: //; s/LINE [0-9]*.*//' \
  | sort | uniq -c | sort -rn | head -20
rm -f "$JOURNAL"
