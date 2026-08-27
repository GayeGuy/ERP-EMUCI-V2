-- ============================================================
--  sql/migration_vps_mysql_points_rivets_types.sql
-- ============================================================
--
--  op_points_journaliers.rivets_gonflables / rivets_eclates existent sur
--  PostgreSQL (main) mais pas sur ce fork MySQL — meme scission
--  gonflable/eclate que op_stock_rivets (cf.
--  migration_vps_mysql_stock_rivets_type.sql), jamais rattrapee ici.
--
--  Trouve en testant pages/operations/rivets.php contre un vrai MySQL
--  (Phase 3 du rattrapage main -> vps-mysql) :
--  "Unknown column 'p.rivets_gonflables'".
-- ============================================================

ALTER TABLE op_points_journaliers
    ADD COLUMN rivets_gonflables INT NOT NULL DEFAULT 0 AFTER rivets_endommages,
    ADD COLUMN rivets_eclates    INT NOT NULL DEFAULT 0 AFTER rivets_gonflables;
