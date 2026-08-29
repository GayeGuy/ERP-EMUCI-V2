-- ============================================================
--  Corrige op_bobines.serie pour les bobines Reservoir/Pare-brise :
--  les actions create/import_lot de pages/operations/bobines.php
--  dérivaient jusqu'ici la série avec substr($type_code,0,1) / le
--  premier caractère du code, correct pour A/B/C/D (1 caractère) mais
--  faux pour TL/WSL (préfixes de 2-3 caractères) — 'TL001' donnait
--  serie='T' et 'WSL001' donnait serie='W' au lieu de 'TL'/'WSL'.
--
--  Trouvé en ajoutant le filtre par catégorie (Bobine vs Vignette,
--  2026-08-28) : la vue Vignette (serie IN ('TL','WSL')) ne trouvait
--  aucune des 167 bobines Réservoir/Pare-brise existantes, toutes
--  mal étiquetées 'T'/'W'. Le bug de derivation est corrigé dans le
--  code (lookup du catalogue op_types_bobines au lieu du premier
--  caractère) ; cette migration aligne les lignes déjà en base.
--
--  op_bobines.serie était en plus déclarée character(1) — trop
--  étroite pour 'TL'/'WSL' (constatée en tentant la correction :
--  "value too long for type character(1)"). Élargie à varchar(4),
--  comme op_types_bobines.serie, avant de corriger les valeurs.
-- ============================================================
ALTER TABLE op_bobines ALTER COLUMN serie TYPE VARCHAR(4);

UPDATE op_bobines SET serie = 'TL'  WHERE serie = 'T' AND type_code LIKE 'TL%';
UPDATE op_bobines SET serie = 'WSL' WHERE serie = 'W' AND type_code LIKE 'WSL%';
