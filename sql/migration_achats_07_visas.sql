-- ============================================================
--  Migration : module Achats — socle du circuit de visas FEB
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (feb créée avant) et du
--  module Demandes internes (sql/demandes_internes_pg.sql, di_roles /
--  di_can_validate() / di_next_step() — includes/demandes.php).
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. Correction des signataires de palier — RG-05/Bloc 0 : les
--     paliers stockent des CODES D'ÉTAPE (vocabulaire di_roles : n1,
--     raf, daf, dg, it, gestionnaire), jamais des slugs de rôle ERP.
--     Le troisième palier semé par migration_achats_02_referentiels.sql
--     portait "lecteur" (le slug ERP du PDG), qui n'existe pas comme
--     étape — di_can_validate() ne l'aurait jamais trouvé dans les
--     rôles du PDG (['dg'], via di_user_roles()) et la FEB serait
--     restée bloquée à cette étape. Remplacement par le code d'étape
--     correct "dg". Idempotent : après le premier passage, plus aucune
--     ligne ne contient "lecteur", donc plus rien à remplacer.
-- ══════════════════════════════════════════════════════════════
UPDATE achat_paliers
   SET signataires = (
       SELECT jsonb_agg(CASE WHEN elem = '"lecteur"'::jsonb THEN '"dg"'::jsonb ELSE elem END)
       FROM jsonb_array_elements(signataires) AS elem
   )
 WHERE signataires @> '["lecteur"]'::jsonb;

-- ══════════════════════════════════════════════════════════════
--  2. Socle du circuit sur feb — même jeu de colonnes que di_demandes,
--     volontairement : le moteur existant (di_can_validate/di_next_step,
--     includes/demandes.php) sait déjà les lire, il suffit de les lui
--     fournir sous la même forme.
--     feb.etape_rejet et feb.motif_rejet séparent explicitement l'étape
--     et le motif de rejet (di_demandes ne garde le motif que dans le
--     JSON signatures/historique) : demandé tel quel pour rester
--     interrogeable sans décodage JSON depuis les écrans Achats.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE feb ADD COLUMN IF NOT EXISTS etape_actuelle smallint NOT NULL DEFAULT -1;
ALTER TABLE feb ADD COLUMN IF NOT EXISTS signatures     jsonb NOT NULL DEFAULT '[]';
ALTER TABLE feb ADD COLUMN IF NOT EXISTS historique     jsonb NOT NULL DEFAULT '[]';
ALTER TABLE feb ADD COLUMN IF NOT EXISTS etape_rejet    smallint DEFAULT NULL;
ALTER TABLE feb ADD COLUMN IF NOT EXISTS motif_rejet    text DEFAULT NULL;

-- feb.workflow_snapshot existe depuis migration_achats_01_schema.sql et
-- reste NULL jusqu'au lancement de la validation (ach_lancer_validation,
-- includes/achats.php) : c'est voulu, aucune colonne supplémentaire.

-- ══════════════════════════════════════════════════════════════
--  3. Droit de visa pour le rôle ERP "directeur_general" — migration_achats_03
--     l'accordait à raf/daf/lecteur (le PDG) mais pas à directeur_general,
--     alors que di_user_roles() mappe les deux vers le même code d'étape
--     "dg". Sans ce droit, un vrai DG (par opposition au PDG) se verrait
--     bloqué par require_permission() avant même d'atteindre
--     di_can_validate() sur l'étape qu'il porte pourtant.
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'achats', 1, 0, 1, 0, 0
FROM roles r
WHERE r.slug = 'directeur_general'
ON CONFLICT (role_id, module) DO UPDATE SET can_read=1, can_update=1;

COMMIT;
