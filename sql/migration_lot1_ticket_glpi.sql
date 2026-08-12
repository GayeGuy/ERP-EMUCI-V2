-- ============================================================
--  n° 06 — numéro du ticket GLPI sur le traitement IT
-- ============================================================
--
--  Quand une demande interne exige une intervention de l'équipe IT, celle-ci
--  ouvre un ticket dans GLPI. Ce numéro n'était consigné nulle part : le
--  demandeur voyait « traitement terminé » sans pouvoir relier sa demande au
--  ticket qui l'a réellement traitée.
--
--  Le numéro est saisi à l'étape de déclaration de traitement, à côté des
--  colonnes traite_it / traite_par / traite_date déjà présentes.

ALTER TABLE di_demandes ADD COLUMN IF NOT EXISTS ticket_glpi varchar(50);

COMMENT ON COLUMN di_demandes.ticket_glpi IS
  'N° du ticket GLPI ouvert par l''IT pour traiter la demande (saisi à la clôture du traitement)';
