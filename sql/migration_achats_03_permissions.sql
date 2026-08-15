-- ============================================================
--  Migration : module Achats — permissions (4 nouveaux modules)
--  Compatible PostgreSQL / Neon — idempotente (ON CONFLICT DO UPDATE).
--  Modele : migration_permissions_27modules.sql.
--
--  Modules declares : achats, achats_suivi, achats_param, achats_dashboard.
--  Ne touche a AUCUN module preexistant — verifie par comparaison
--  avant/apres de la matrice complete (228 lignes avant ce lot, sur 16
--  roles reels — la reference a "20 roles existants" ne correspond pas
--  au compte reel, meme ecart d'estimation que le nombre de tables du
--  Bloc 1 ; le controle porte sur les 16 roles reellement en base).
--
--  Ordre volontaire, du plus general au plus specifique : chaque etape
--  ulterieure ne fait qu'AJOUTER des droits (jamais en retirer) sur le
--  meme role, pour ne jamais ecraser un droit deja accorde par une etape
--  precedente avec une valeur plus faible.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. TOUT LE MONDE — lecture sur `achats` pour voir ses propres FEB
--     (filtrage par demandeur_id fait cote code, pas ici)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'achats', 1, 0, 0, 0, 0
FROM roles r
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read = 1;  -- ne touche a aucun autre champ : ne retire jamais un droit deja accorde

-- ══════════════════════════════════════════════════════════════
--  2. ADMIN & SUPERADMIN — tous droits sur les 4 modules
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 1, 1, 1, 1
FROM roles r
CROSS JOIN (VALUES ('achats'), ('achats_suivi'), ('achats_param'), ('achats_dashboard')) AS m(module)
WHERE r.slug IN ('admin', 'superadmin')
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=1, can_create=1, can_update=1, can_delete=1, can_export=1;

-- ══════════════════════════════════════════════════════════════
--  3. SUPERVISEUR ACHAT — ecriture complete sur les 4 modules
--     (role deja existant, id=17, proprietaire fonctionnel du module)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 1, 1, 1, 1
FROM roles r
CROSS JOIN (VALUES ('achats'), ('achats_suivi'), ('achats_param'), ('achats_dashboard')) AS m(module)
WHERE r.slug = 'superviseur_achat'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=1, can_create=1, can_update=1, can_delete=1, can_export=1;

-- ══════════════════════════════════════════════════════════════
--  4. RAF / DAF / PDG — lecture + visa (validation par palier)
--     Slug technique du role PDG : 'lecteur' (libelle affiche seul a
--     change, cf. migration_lot2_role_pdg.sql). Visa = can_update sur
--     `achats` uniquement (l'etape de validation elle-meme) ; lecture
--     sur le suivi et le tableau de bord pour le contexte, pas sur le
--     parametrage (fournisseurs/paliers/budget — hors de leur usage).
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'achats', 1, 0, 1, 0, 0
FROM roles r
WHERE r.slug IN ('raf', 'daf', 'lecteur')
ON CONFLICT (role_id, module) DO UPDATE SET can_read=1, can_update=1;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 0, 0, 0, 0
FROM roles r
CROSS JOIN (VALUES ('achats_suivi'), ('achats_dashboard')) AS m(module)
WHERE r.slug IN ('raf', 'daf', 'lecteur')
ON CONFLICT (role_id, module) DO UPDATE SET can_read=1;

-- ══════════════════════════════════════════════════════════════
--  5. GESTIONNAIRE STOCK — sur les receptions (feb_suivi/feb_receptions,
--     couvertes par le module achats_suivi)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'achats_suivi', 1, 1, 1, 0, 0
FROM roles r
WHERE r.slug = 'gestionnaire_stock'
ON CONFLICT (role_id, module) DO UPDATE SET can_read=1, can_create=1, can_update=1;

COMMIT;

-- ══════════════════════════════════════════════════════════════
--  VÉRIFICATION — permissions sur les 4 nouveaux modules par rôle
-- ══════════════════════════════════════════════════════════════
SELECT r.slug AS role, p.module, p.can_read, p.can_create, p.can_update, p.can_delete, p.can_export
FROM permissions p
JOIN roles r ON r.id = p.role_id
WHERE p.module IN ('achats', 'achats_suivi', 'achats_param', 'achats_dashboard')
ORDER BY p.module, r.slug;
