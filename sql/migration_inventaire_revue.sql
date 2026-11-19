-- ============================================================
--  Revue Inventaire (19/11/2026) — PostgreSQL
-- ============================================================
--  1. inventaire_details_rivets / _pmma : stock_reference (stock théorique de
--     fin de journée noté à la saisie) et saisi_at. À la validation, l'écart
--     compté s'applique au stock actuel : une entrée faite entre la saisie et
--     la validation n'est plus effacée.
--  2. inventaire_details_bobines.saisi_at : une bobine comptée à 0 dont le
--     stock était 0 restait « non saisie ».
--  3. Droit « Valider » par type d'inventaire (option « Modifier » des
--     modules inventaire_*_valider dans la matrice), accordé aux rôles qui
--     validaient jusqu'ici (liste écrite dans le code). Une ligne déjà réglée
--     n'est jamais écrasée. Rejouable.
-- ============================================================
ALTER TABLE inventaire_details_rivets ADD COLUMN IF NOT EXISTS stock_reference integer;
ALTER TABLE inventaire_details_rivets ADD COLUMN IF NOT EXISTS saisi_at timestamp;
ALTER TABLE inventaire_details_pmma ADD COLUMN IF NOT EXISTS stock_reference integer;
ALTER TABLE inventaire_details_pmma ADD COLUMN IF NOT EXISTS saisi_at timestamp;
ALTER TABLE inventaire_details_bobines ADD COLUMN IF NOT EXISTS saisi_at timestamp;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT id, 'inventaire_bobines_valider', 0, 0, CASE WHEN slug IN ('superviseur_operation','gestionnaire_stock_bobines','gestionnaire_stock') THEN 1 ELSE 0 END, 0, 0 FROM roles
ON CONFLICT (role_id, module) DO NOTHING;
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT id, 'inventaire_rivets_valider', 0, 0, CASE WHEN slug IN ('superviseur_operation','gestionnaire_stock_bobines','gestionnaire_stock') THEN 1 ELSE 0 END, 0, 0 FROM roles
ON CONFLICT (role_id, module) DO NOTHING;
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT id, 'inventaire_pmma_valider', 0, 0, CASE WHEN slug IN ('superviseur_operation','gestionnaire_stock_bobines','gestionnaire_stock') THEN 1 ELSE 0 END, 0, 0 FROM roles
ON CONFLICT (role_id, module) DO NOTHING;
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT id, 'inventaire_equipements_valider', 0, 0, CASE WHEN slug IN ('superviseur_operation','gestionnaire_stock') THEN 1 ELSE 0 END, 0, 0 FROM roles
ON CONFLICT (role_id, module) DO NOTHING;
