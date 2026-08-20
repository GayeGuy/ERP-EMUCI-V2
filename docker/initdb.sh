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

# Le dump PostgreSQL d'abord : il crée les tables que les migrations
# modifient. Il fait autorité — vérifié le 2026-07-30, il contient déjà les
# 49 tables des migrations restées en syntaxe MySQL.
#
# Trois fichiers de sql/ sont volontairement absents de cette liste, parce
# qu'ils sont écrits pour MySQL (accents graves, AUTO_INCREMENT, ENGINE=) et
# que psql les rejette intégralement :
#
#   stockapp.sql                          (dump MySQL d'origine)
#   migration_stockapp_complet.sql
#   migration_articles_commandes.sql
#   migration_demandes_correction_saisie.sql
#
# Les charger produisait 78 erreurs de syntaxe sans rien créer. Leur contenu
# est déjà couvert par stockapp_pg.sql : aucune table ne manque.
FICHIERS="
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
"

for f in $FICHIERS; do
  if [ ! -f "/sql/$f" ]; then
    echo "initdb : /sql/$f introuvable, ignoré"
    continue
  fi
  echo "initdb : chargement de $f"
  # Deux pièges successifs sur ce comptage, d'où la forme actuelle :
  #
  #  1. psql préfixe ses erreurs SQL par « psql:/sql/fichier.sql:12: ERROR: ».
  #     Un grep ancré sur '^ERROR' ne les voit jamais — c'est ce qui a fait
  #     annoncer « 0 erreur » alors qu'il y en avait 78.
  #  2. Les erreurs de psql lui-même, dont « fichier introuvable », s'écrivent
  #     « psql: error: » en minuscules. Un grep sur « ERROR: » les manque, et
  #     un fichier jamais chargé passe alors pour un succès.
  #
  # On cherche donc « error » n'importe où, sans tenir compte de la casse.
  $PSQL -f "/sql/$f" > /tmp/sortie 2>&1 || true
  cat /tmp/sortie >> "$JOURNAL"
  echo "initdb :   $(grep -ci 'error' /tmp/sortie || true) erreur(s)"
done

echo "───────────────────────────────────────────────"
echo "initdb : bilan"
echo "  tables créées      : $($PSQL -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'")"
echo "  utilisateurs       : $($PSQL -tAc "SELECT count(*) FROM users" 2>/dev/null || echo 'table absente')"
echo "  rôles              : $($PSQL -tAc "SELECT count(*) FROM roles" 2>/dev/null || echo 'table absente')"
echo "  sites              : $($PSQL -tAc "SELECT count(*) FROM sites" 2>/dev/null || echo 'table absente')"
echo "  erreurs au total   : $(grep -ci 'error' "$JOURNAL" || true)"
echo "───────────────────────────────────────────────"
echo "initdb : détail des erreurs distinctes"
grep -i 'error' "$JOURNAL" | sed 's/^psql:[^:]*:[0-9]*: //; s/LINE [0-9]*.*//' \
  | sort | uniq -c | sort -rn | head -25
echo "───────────────────────────────────────────────"
