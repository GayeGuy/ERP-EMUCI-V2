#!/bin/sh
# ============================================================
#  Chargement de la base locale de developpement (variante MySQL)
# ============================================================
#
#  Execute une seule fois, a la creation du volume de la base (comportement
#  standard de l'image mysql officielle pour /docker-entrypoint-initdb.d).
#  Charge le schema de base puis les modules, dans l'ordre ou ils doivent
#  etre appliques (cf. DEPLOY-VPS-MYSQL.md).

set -eu

MYSQL="mysql -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE}"

for f in /sql/stockapp.sql /sql/demandes_internes_mysql.sql /sql/achats_mysql.sql; do
    if [ -f "$f" ]; then
        echo "Chargement $f..."
        $MYSQL < "$f" || echo "  -> erreur non bloquante sur $f (voir ci-dessus)"
    fi
done

echo "Chargement termine."
