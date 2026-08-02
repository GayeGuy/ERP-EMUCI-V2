-- ============================================================
--  Migration : département lié à un rôle de validation demandes
--  (variante MySQL)
-- ============================================================
--  MySQL n'accepte pas REFERENCES en ligne sur ADD COLUMN (accepté par le
--  parseur mais silencieusement ignoré, sans créer la contrainte) : la clé
--  étrangère est donc posée dans une instruction séparée.

ALTER TABLE di_roles ADD COLUMN departement_id INT UNSIGNED DEFAULT NULL;

ALTER TABLE di_roles ADD CONSTRAINT fk_di_roles_departement
    FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE SET NULL;
