-- ============================================================
--  Réparation : contraintes et index seuls
--  ERP EMUCI — base de recette Achats
-- ============================================================
--
--  À utiliser SI le script principal a été collé de façon incomplète :
--  dans un pg_dump, les contraintes se déclarent tout à la fin (ligne 8850
--  sur 8857). Un collage tronqué laisse donc les tables et les données en
--  place, mais sans clés primaires ni index — et le moindre ON CONFLICT
--  échoue, ce qui avorte la transaction et fait remonter un message
--  trompeur (« current transaction is aborted »).
--
--  Diagnostic préalable, à coller dans Neon :
--
--    SELECT
--      (SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
--        WHERE n.nspname='public' AND c.relkind='r')                        AS tables,
--      (SELECT count(*) FROM pg_constraint c JOIN pg_class t ON t.oid=c.conrelid
--        JOIN pg_namespace n ON n.oid=t.relnamespace WHERE n.nspname='public') AS contraintes,
--      (SELECT count(*) FROM pg_indexes WHERE schemaname='public')          AS index,
--      (SELECT count(*) FROM users)                                          AS utilisateurs;
--
--  Attendu : 90 tables, 292 contraintes, 280 index, 17 utilisateurs.
--  Si les contraintes sont bien en dessous de 292, exécutez ce fichier.
-- ============================================================
--
-- PostgreSQL database dump
--


-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

--
-- Name: achat_paliers achat_paliers_libelle_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_paliers
    ADD CONSTRAINT achat_paliers_libelle_key UNIQUE (libelle);


--
-- Name: achat_paliers achat_paliers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_paliers
    ADD CONSTRAINT achat_paliers_pkey PRIMARY KEY (id);


--
-- Name: achat_parametres achat_parametres_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_parametres
    ADD CONSTRAINT achat_parametres_pkey PRIMARY KEY (cle);


--
-- Name: achat_types achat_types_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_types
    ADD CONSTRAINT achat_types_code_key UNIQUE (code);


--
-- Name: achat_types achat_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_types
    ADD CONSTRAINT achat_types_pkey PRIMARY KEY (id);


--
-- Name: affectations_equipements affectations_equipements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affectations_equipements
    ADD CONSTRAINT affectations_equipements_pkey PRIMARY KEY (id);


--
-- Name: agents agents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_pkey PRIMARY KEY (id);


--
-- Name: articles articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT articles_pkey PRIMARY KEY (id);


--
-- Name: articles articles_uk_article_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT articles_uk_article_code UNIQUE (code);


--
-- Name: audit_log audit_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_log
    ADD CONSTRAINT audit_log_pkey PRIMARY KEY (id);


--
-- Name: bilans_mensuels_bobines bilans_mensuels_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bilans_mensuels_bobines
    ADD CONSTRAINT bilans_mensuels_bobines_pkey PRIMARY KEY (id);


--
-- Name: bilans_mensuels_bobines bilans_mensuels_bobines_uq_site_mois; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bilans_mensuels_bobines
    ADD CONSTRAINT bilans_mensuels_bobines_uq_site_mois UNIQUE (site_id, mois);


--
-- Name: budget_validations budget_validations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.budget_validations
    ADD CONSTRAINT budget_validations_pkey PRIMARY KEY (id);


--
-- Name: budget_validations budget_validations_uk_dept_exercice; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.budget_validations
    ADD CONSTRAINT budget_validations_uk_dept_exercice UNIQUE (departement_id, exercice);


--
-- Name: commande_lignes commande_lignes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commande_lignes
    ADD CONSTRAINT commande_lignes_pkey PRIMARY KEY (id);


--
-- Name: commandes_bobines_lignes commandes_bobines_lignes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes_bobines_lignes
    ADD CONSTRAINT commandes_bobines_lignes_pkey PRIMARY KEY (id);


--
-- Name: commandes_bobines commandes_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes_bobines
    ADD CONSTRAINT commandes_bobines_pkey PRIMARY KEY (id);


--
-- Name: commandes_bobines commandes_bobines_uk_numero; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes_bobines
    ADD CONSTRAINT commandes_bobines_uk_numero UNIQUE (numero);


--
-- Name: commandes commandes_numero_commande; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes
    ADD CONSTRAINT commandes_numero_commande UNIQUE (numero_commande);


--
-- Name: commandes commandes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes
    ADD CONSTRAINT commandes_pkey PRIMARY KEY (id);


--
-- Name: comparaisons_stock comparaisons_stock_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comparaisons_stock
    ADD CONSTRAINT comparaisons_stock_pkey PRIMARY KEY (id);


--
-- Name: comparaisons_stock comparaisons_stock_uq_site_date; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comparaisons_stock
    ADD CONSTRAINT comparaisons_stock_uq_site_date UNIQUE (site_id, date_comparaison);


--
-- Name: config_postes_composants config_postes_composants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_composants
    ADD CONSTRAINT config_postes_composants_pkey PRIMARY KEY (id);


--
-- Name: config_postes_composants config_postes_composants_uq_poste_nom; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_composants
    ADD CONSTRAINT config_postes_composants_uq_poste_nom UNIQUE (poste_id, nomenclature_id);


--
-- Name: config_postes_types config_postes_types_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_types
    ADD CONSTRAINT config_postes_types_code UNIQUE (code);


--
-- Name: config_postes_types config_postes_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_types
    ADD CONSTRAINT config_postes_types_pkey PRIMARY KEY (id);


--
-- Name: configurations_site configurations_site_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configurations_site
    ADD CONSTRAINT configurations_site_pkey PRIMARY KEY (id);


--
-- Name: configurations_site configurations_site_uq_type_nom; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configurations_site
    ADD CONSTRAINT configurations_site_uq_type_nom UNIQUE (type_site, nomenclature_id);


--
-- Name: consommables consommables_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommables
    ADD CONSTRAINT consommables_code UNIQUE (code);


--
-- Name: consommables consommables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommables
    ADD CONSTRAINT consommables_pkey PRIMARY KEY (id);


--
-- Name: consommations_bobines consommations_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommations_bobines
    ADD CONSTRAINT consommations_bobines_pkey PRIMARY KEY (id);


--
-- Name: corrections_bobines corrections_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corrections_bobines
    ADD CONSTRAINT corrections_bobines_pkey PRIMARY KEY (id);


--
-- Name: delegations delegations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delegations
    ADD CONSTRAINT delegations_pkey PRIMARY KEY (id);


--
-- Name: delegations delegations_uq_sup_gest_module; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delegations
    ADD CONSTRAINT delegations_uq_sup_gest_module UNIQUE (superviseur_id, gestionnaire_id, module);


--
-- Name: demandes_bobines demandes_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_bobines
    ADD CONSTRAINT demandes_bobines_pkey PRIMARY KEY (id);


--
-- Name: demandes_correction_saisie demandes_correction_saisie_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_correction_saisie
    ADD CONSTRAINT demandes_correction_saisie_pkey PRIMARY KEY (id);


--
-- Name: departements departements_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departements
    ADD CONSTRAINT departements_code_key UNIQUE (code);


--
-- Name: departements departements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departements
    ADD CONSTRAINT departements_pkey PRIMARY KEY (id);


--
-- Name: di_demandes di_demandes_numero_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes
    ADD CONSTRAINT di_demandes_numero_key UNIQUE (numero);


--
-- Name: di_demandes di_demandes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes
    ADD CONSTRAINT di_demandes_pkey PRIMARY KEY (id);


--
-- Name: di_etapes di_etapes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_etapes
    ADD CONSTRAINT di_etapes_pkey PRIMARY KEY (id);


--
-- Name: di_plateformes di_plateformes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_plateformes
    ADD CONSTRAINT di_plateformes_pkey PRIMARY KEY (code);


--
-- Name: di_roles di_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_roles
    ADD CONSTRAINT di_roles_pkey PRIMARY KEY (code);


--
-- Name: di_types di_types_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_types
    ADD CONSTRAINT di_types_code_key UNIQUE (code);


--
-- Name: di_types di_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_types
    ADD CONSTRAINT di_types_pkey PRIMARY KEY (id);


--
-- Name: di_user_roles di_user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_user_roles
    ADD CONSTRAINT di_user_roles_pkey PRIMARY KEY (user_id, role_code);


--
-- Name: distribution_lignes distribution_lignes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.distribution_lignes
    ADD CONSTRAINT distribution_lignes_pkey PRIMARY KEY (id);


--
-- Name: distributions_site distributions_site_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.distributions_site
    ADD CONSTRAINT distributions_site_pkey PRIMARY KEY (id);


--
-- Name: distributions_site distributions_site_uk_num_distribution; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.distributions_site
    ADD CONSTRAINT distributions_site_uk_num_distribution UNIQUE (numero_distribution);


--
-- Name: ecarts_bobines ecarts_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ecarts_bobines
    ADD CONSTRAINT ecarts_bobines_pkey PRIMARY KEY (id);


--
-- Name: emuci_sites_inconnus emuci_sites_inconnus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.emuci_sites_inconnus
    ADD CONSTRAINT emuci_sites_inconnus_pkey PRIMARY KEY (id);


--
-- Name: emuci_sites_inconnus emuci_sites_inconnus_uk_nom_type; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.emuci_sites_inconnus
    ADD CONSTRAINT emuci_sites_inconnus_uk_nom_type UNIQUE (nom_emuci, type_import);


--
-- Name: equipements equipements_numero_serie_interne; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipements
    ADD CONSTRAINT equipements_numero_serie_interne UNIQUE (numero_serie_interne);


--
-- Name: equipements equipements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipements
    ADD CONSTRAINT equipements_pkey PRIMARY KEY (id);


--
-- Name: familles_achat familles_achat_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.familles_achat
    ADD CONSTRAINT familles_achat_code_key UNIQUE (code);


--
-- Name: familles_achat familles_achat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.familles_achat
    ADD CONSTRAINT familles_achat_pkey PRIMARY KEY (id);


--
-- Name: feb_compteurs feb_compteurs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_compteurs
    ADD CONSTRAINT feb_compteurs_pkey PRIMARY KEY (exercice);


--
-- Name: feb_lignes feb_lignes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_lignes
    ADD CONSTRAINT feb_lignes_pkey PRIMARY KEY (id);


--
-- Name: feb feb_numero_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb
    ADD CONSTRAINT feb_numero_key UNIQUE (numero);


--
-- Name: feb_offres feb_offres_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_offres
    ADD CONSTRAINT feb_offres_pkey PRIMARY KEY (id);


--
-- Name: feb_pieces_jointes feb_pieces_jointes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_pieces_jointes
    ADD CONSTRAINT feb_pieces_jointes_pkey PRIMARY KEY (id);


--
-- Name: feb feb_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb
    ADD CONSTRAINT feb_pkey PRIMARY KEY (id);


--
-- Name: feb_receptions feb_receptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_receptions
    ADD CONSTRAINT feb_receptions_pkey PRIMARY KEY (id);


--
-- Name: feb_suivi feb_suivi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_suivi
    ADD CONSTRAINT feb_suivi_pkey PRIMARY KEY (id);


--
-- Name: fournisseurs fournisseurs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fournisseurs
    ADD CONSTRAINT fournisseurs_pkey PRIMARY KEY (id);


--
-- Name: import_optoplate import_optoplate_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optoplate
    ADD CONSTRAINT import_optoplate_pkey PRIMARY KEY (id);


--
-- Name: import_optotrace import_optotrace_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optotrace
    ADD CONSTRAINT import_optotrace_pkey PRIMARY KEY (id);


--
-- Name: import_sessions_emuci import_sessions_emuci_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_sessions_emuci
    ADD CONSTRAINT import_sessions_emuci_pkey PRIMARY KEY (id);


--
-- Name: interventions_maintenance interventions_maintenance_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interventions_maintenance
    ADD CONSTRAINT interventions_maintenance_pkey PRIMARY KEY (id);


--
-- Name: inventaire_corrections inventaire_corrections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_pkey PRIMARY KEY (id);


--
-- Name: inventaire_details_bobines inventaire_details_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_details_bobines
    ADD CONSTRAINT inventaire_details_bobines_pkey PRIMARY KEY (id);


--
-- Name: inventaire_details_bobines inventaire_details_bobines_uq_inv_bobine; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_details_bobines
    ADD CONSTRAINT inventaire_details_bobines_uq_inv_bobine UNIQUE (inventaire_id, bobine_id);


--
-- Name: inventaire_session_sites inventaire_session_sites_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_session_sites
    ADD CONSTRAINT inventaire_session_sites_pkey PRIMARY KEY (session_id, site_id);


--
-- Name: inventaire_sessions inventaire_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_sessions
    ADD CONSTRAINT inventaire_sessions_pkey PRIMARY KEY (id);


--
-- Name: inventaires_bobines inventaires_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaires_bobines
    ADD CONSTRAINT inventaires_bobines_pkey PRIMARY KEY (id);


--
-- Name: lignes_budgetaires lignes_budgetaires_dept_famille_exercice_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lignes_budgetaires
    ADD CONSTRAINT lignes_budgetaires_dept_famille_exercice_key UNIQUE (departement_id, famille_id, exercice);


--
-- Name: lignes_budgetaires lignes_budgetaires_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lignes_budgetaires
    ADD CONSTRAINT lignes_budgetaires_pkey PRIMARY KEY (id);


--
-- Name: litige_messages litige_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.litige_messages
    ADD CONSTRAINT litige_messages_pkey PRIMARY KEY (id);


--
-- Name: livraisons_consommables livraisons_consommables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.livraisons_consommables
    ADD CONSTRAINT livraisons_consommables_pkey PRIMARY KEY (id);


--
-- Name: mouvements_bobines mouvements_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_bobines
    ADD CONSTRAINT mouvements_bobines_pkey PRIMARY KEY (id);


--
-- Name: mouvements_equipements mouvements_equipements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_equipements
    ADD CONSTRAINT mouvements_equipements_pkey PRIMARY KEY (id);


--
-- Name: mouvements_stock mouvements_stock_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_stock
    ADD CONSTRAINT mouvements_stock_pkey PRIMARY KEY (id);


--
-- Name: nomenclature_liens nomenclature_liens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclature_liens
    ADD CONSTRAINT nomenclature_liens_pkey PRIMARY KEY (id);


--
-- Name: nomenclature_liens nomenclature_liens_uq_parent_enfant; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclature_liens
    ADD CONSTRAINT nomenclature_liens_uq_parent_enfant UNIQUE (parent_id, enfant_id);


--
-- Name: nomenclatures nomenclatures_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclatures
    ADD CONSTRAINT nomenclatures_code UNIQUE (code);


--
-- Name: nomenclatures nomenclatures_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclatures
    ADD CONSTRAINT nomenclatures_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: op_bobines op_bobines_numero; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_bobines
    ADD CONSTRAINT op_bobines_numero UNIQUE (numero);


--
-- Name: op_bobines op_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_bobines
    ADD CONSTRAINT op_bobines_pkey PRIMARY KEY (id);


--
-- Name: op_films_utilises op_films_utilises_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_films_utilises
    ADD CONSTRAINT op_films_utilises_pkey PRIMARY KEY (id);


--
-- Name: op_pmma_utilises op_pmma_utilises_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_pmma_utilises
    ADD CONSTRAINT op_pmma_utilises_pkey PRIMARY KEY (id);


--
-- Name: op_points_journaliers op_points_journaliers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_points_journaliers
    ADD CONSTRAINT op_points_journaliers_pkey PRIMARY KEY (id);


--
-- Name: op_points_journaliers op_points_journaliers_uq_site_date_type; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_points_journaliers
    ADD CONSTRAINT op_points_journaliers_uq_site_date_type UNIQUE (site_id, date_point, type_point);


--
-- Name: op_stock_rivets op_stock_rivets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_stock_rivets
    ADD CONSTRAINT op_stock_rivets_pkey PRIMARY KEY (id);


--
-- Name: op_stock_rivets op_stock_rivets_uq_site_type; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_stock_rivets
    ADD CONSTRAINT op_stock_rivets_uq_site_type UNIQUE (site_id, type_rivet);


--
-- Name: op_types_bobines op_types_bobines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_types_bobines
    ADD CONSTRAINT op_types_bobines_pkey PRIMARY KEY (id);


--
-- Name: op_types_bobines op_types_bobines_uk_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_types_bobines
    ADD CONSTRAINT op_types_bobines_uk_code UNIQUE (code);


--
-- Name: op_types_vehicule op_types_vehicule_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_types_vehicule
    ADD CONSTRAINT op_types_vehicule_code UNIQUE (code);


--
-- Name: op_types_vehicule op_types_vehicule_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_types_vehicule
    ADD CONSTRAINT op_types_vehicule_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_uq_role_module; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_uq_role_module UNIQUE (role_id, module);


--
-- Name: points_emuci points_emuci_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_emuci
    ADD CONSTRAINT points_emuci_pkey PRIMARY KEY (id);


--
-- Name: points_emuci points_emuci_uq_site_date; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_emuci
    ADD CONSTRAINT points_emuci_uq_site_date UNIQUE (site_id, date_point);


--
-- Name: points_journaliers_info points_journaliers_info_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_journaliers_info
    ADD CONSTRAINT points_journaliers_info_pkey PRIMARY KEY (id);


--
-- Name: rapports_journaliers_info rapports_journaliers_info_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rapports_journaliers_info
    ADD CONSTRAINT rapports_journaliers_info_pkey PRIMARY KEY (id);


--
-- Name: rapports_journaliers_info rapports_journaliers_info_uq_tech_site_date; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rapports_journaliers_info
    ADD CONSTRAINT rapports_journaliers_info_uq_tech_site_date UNIQUE (technicien_id, site_id, date_rapport);


--
-- Name: reception_lignes reception_lignes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reception_lignes
    ADD CONSTRAINT reception_lignes_pkey PRIMARY KEY (id);


--
-- Name: receptions_consommables receptions_consommables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_consommables
    ADD CONSTRAINT receptions_consommables_pkey PRIMARY KEY (id);


--
-- Name: receptions_fournisseur receptions_fournisseur_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_fournisseur
    ADD CONSTRAINT receptions_fournisseur_pkey PRIMARY KEY (id);


--
-- Name: receptions_fournisseur receptions_fournisseur_uk_num_reception; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_fournisseur
    ADD CONSTRAINT receptions_fournisseur_uk_num_reception UNIQUE (numero_reception);


--
-- Name: receptions_site receptions_site_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_site
    ADD CONSTRAINT receptions_site_pkey PRIMARY KEY (id);


--
-- Name: roles roles_nom; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_nom UNIQUE (nom);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_slug; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_slug UNIQUE (slug);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sites sites_code; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_code UNIQUE (code);


--
-- Name: sites sites_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_pkey PRIMARY KEY (id);


--
-- Name: stock_consommables_site stock_consommables_site_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_consommables_site
    ADD CONSTRAINT stock_consommables_site_pkey PRIMARY KEY (id);


--
-- Name: stock_consommables_site stock_consommables_site_uq_conso_site; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_consommables_site
    ADD CONSTRAINT stock_consommables_site_uq_conso_site UNIQUE (consommable_id, site_id);


--
-- Name: stock_departement stock_departement_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_departement
    ADD CONSTRAINT stock_departement_pkey PRIMARY KEY (id);


--
-- Name: stock_departement stock_departement_uk_article_dept; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_departement
    ADD CONSTRAINT stock_departement_uk_article_dept UNIQUE (article_id, departement_id);


--
-- Name: stock_fin_mois stock_fin_mois_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_fin_mois
    ADD CONSTRAINT stock_fin_mois_pkey PRIMARY KEY (id);


--
-- Name: stock_pmma stock_pmma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_pmma
    ADD CONSTRAINT stock_pmma_pkey PRIMARY KEY (id);


--
-- Name: stock_pmma_site stock_pmma_site_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_pmma_site
    ADD CONSTRAINT stock_pmma_site_pkey PRIMARY KEY (id);


--
-- Name: stock_pmma_site stock_pmma_site_uq_site_type; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_pmma_site
    ADD CONSTRAINT stock_pmma_site_uq_site_type UNIQUE (site_id, type_pmma);


--
-- Name: stock_site stock_site_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_site
    ADD CONSTRAINT stock_site_pkey PRIMARY KEY (id);


--
-- Name: stock_site stock_site_uk_article_site; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_site
    ADD CONSTRAINT stock_site_uk_article_site UNIQUE (article_id, site_id);


--
-- Name: support_it_roles support_it_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_it_roles
    ADD CONSTRAINT support_it_roles_pkey PRIMARY KEY (id);


--
-- Name: support_it_roles support_it_roles_uq_user_sousrole; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_it_roles
    ADD CONSTRAINT support_it_roles_uq_user_sousrole UNIQUE (user_id, sous_role);


--
-- Name: user_departements user_departements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_departements
    ADD CONSTRAINT user_departements_pkey PRIMARY KEY (user_id, departement_id);


--
-- Name: users users_email; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: validations_stock_matin validations_stock_matin_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.validations_stock_matin
    ADD CONSTRAINT validations_stock_matin_pkey PRIMARY KEY (id);


--
-- Name: validations_stock_matin validations_stock_matin_uq_site_date; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.validations_stock_matin
    ADD CONSTRAINT validations_stock_matin_uq_site_date UNIQUE (site_id, date_validation);


--
-- Name: affectations_equipements_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX affectations_equipements_created_by ON public.affectations_equipements USING btree (created_by);


--
-- Name: affectations_equipements_equipement_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX affectations_equipements_equipement_id ON public.affectations_equipements USING btree (equipement_id);


--
-- Name: affectations_equipements_site_dest_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX affectations_equipements_site_dest_id ON public.affectations_equipements USING btree (site_dest_id);


--
-- Name: affectations_equipements_user_dest_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX affectations_equipements_user_dest_id ON public.affectations_equipements USING btree (user_dest_id);


--
-- Name: affectations_equipements_valide_n1_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX affectations_equipements_valide_n1_by ON public.affectations_equipements USING btree (valide_n1_by);


--
-- Name: agents_matricule_uidx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX agents_matricule_uidx ON public.agents USING btree (matricule) WHERE ((matricule IS NOT NULL) AND ((matricule)::text <> ''::text));


--
-- Name: agents_nom_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agents_nom_idx ON public.agents USING btree (nom, prenom);


--
-- Name: audit_log_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_log_idx_date ON public.audit_log USING btree (created_at);


--
-- Name: audit_log_idx_module; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_log_idx_module ON public.audit_log USING btree (module);


--
-- Name: audit_log_idx_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audit_log_idx_user ON public.audit_log USING btree (user_id);


--
-- Name: bilans_mensuels_bobines_inventaire_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bilans_mensuels_bobines_inventaire_id ON public.bilans_mensuels_bobines USING btree (inventaire_id);


--
-- Name: bilans_mensuels_bobines_valide_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bilans_mensuels_bobines_valide_par ON public.bilans_mensuels_bobines USING btree (valide_par);


--
-- Name: commande_lignes_commande_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX commande_lignes_commande_id ON public.commande_lignes USING btree (commande_id);


--
-- Name: commandes_bobines_lignes_idx_bobine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX commandes_bobines_lignes_idx_bobine ON public.commandes_bobines_lignes USING btree (bobine_id);


--
-- Name: commandes_bobines_lignes_idx_commande; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX commandes_bobines_lignes_idx_commande ON public.commandes_bobines_lignes USING btree (commande_id);


--
-- Name: commandes_feb_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX commandes_feb_idx ON public.commandes USING btree (feb_id);


--
-- Name: commandes_idx_site_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX commandes_idx_site_statut ON public.commandes USING btree (site_id, statut);


--
-- Name: comparaisons_stock_ajuste_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX comparaisons_stock_ajuste_par ON public.comparaisons_stock USING btree (ajuste_par);


--
-- Name: config_postes_composants_nomenclature_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX config_postes_composants_nomenclature_id ON public.config_postes_composants USING btree (nomenclature_id);


--
-- Name: consommations_bobines_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX consommations_bobines_created_by ON public.consommations_bobines USING btree (created_by);


--
-- Name: consommations_bobines_idx_bobine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX consommations_bobines_idx_bobine ON public.consommations_bobines USING btree (bobine_id);


--
-- Name: consommations_bobines_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX consommations_bobines_idx_date ON public.consommations_bobines USING btree (date_conso);


--
-- Name: consommations_bobines_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX consommations_bobines_idx_site ON public.consommations_bobines USING btree (site_id);


--
-- Name: corrections_bobines_idx_bobine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX corrections_bobines_idx_bobine ON public.corrections_bobines USING btree (bobine_id);


--
-- Name: corrections_bobines_idx_gsb; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX corrections_bobines_idx_gsb ON public.corrections_bobines USING btree (gsb_id);


--
-- Name: corrections_bobines_idx_point; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX corrections_bobines_idx_point ON public.corrections_bobines USING btree (point_id);


--
-- Name: corrections_bobines_idx_site_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX corrections_bobines_idx_site_statut ON public.corrections_bobines USING btree (site_id, statut);


--
-- Name: delegations_idx_gestionnaire; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX delegations_idx_gestionnaire ON public.delegations USING btree (gestionnaire_id);


--
-- Name: demandes_bobines_bobine_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demandes_bobines_bobine_id ON public.demandes_bobines USING btree (bobine_id);


--
-- Name: demandes_bobines_demande_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demandes_bobines_demande_par ON public.demandes_bobines USING btree (demande_par);


--
-- Name: demandes_bobines_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demandes_bobines_idx_site ON public.demandes_bobines USING btree (site_id);


--
-- Name: demandes_bobines_idx_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demandes_bobines_idx_statut ON public.demandes_bobines USING btree (statut);


--
-- Name: demandes_bobines_traite_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX demandes_bobines_traite_par ON public.demandes_bobines USING btree (traite_par);


--
-- Name: di_demandes_demandeur_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX di_demandes_demandeur_idx ON public.di_demandes USING btree (demandeur_id);


--
-- Name: di_demandes_statut_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX di_demandes_statut_idx ON public.di_demandes USING btree (statut);


--
-- Name: di_demandes_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX di_demandes_type_idx ON public.di_demandes USING btree (type_code);


--
-- Name: di_etapes_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX di_etapes_type_idx ON public.di_etapes USING btree (type_id, ordre);


--
-- Name: distribution_lignes_idx_distribution; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX distribution_lignes_idx_distribution ON public.distribution_lignes USING btree (distribution_id);


--
-- Name: ecarts_bobines_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ecarts_bobines_created_by ON public.ecarts_bobines USING btree (created_by);


--
-- Name: ecarts_bobines_idx_bobine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ecarts_bobines_idx_bobine ON public.ecarts_bobines USING btree (bobine_id);


--
-- Name: ecarts_bobines_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ecarts_bobines_idx_date ON public.ecarts_bobines USING btree (date_constat);


--
-- Name: ecarts_bobines_idx_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ecarts_bobines_idx_statut ON public.ecarts_bobines USING btree (statut);


--
-- Name: ecarts_bobines_inventaire_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ecarts_bobines_inventaire_id ON public.ecarts_bobines USING btree (inventaire_id);


--
-- Name: ecarts_bobines_resolu_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ecarts_bobines_resolu_par ON public.ecarts_bobines USING btree (resolu_par);


--
-- Name: equipements_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipements_created_by ON public.equipements USING btree (created_by);


--
-- Name: equipements_idx_date_fin_cycle; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipements_idx_date_fin_cycle ON public.equipements USING btree (date_fin_cycle);


--
-- Name: equipements_idx_etat; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipements_idx_etat ON public.equipements USING btree (etat);


--
-- Name: equipements_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipements_idx_site ON public.equipements USING btree (site_id);


--
-- Name: equipements_nomenclature_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipements_nomenclature_id ON public.equipements USING btree (nomenclature_id);


--
-- Name: equipements_utilisateur_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipements_utilisateur_id ON public.equipements USING btree (utilisateur_id);


--
-- Name: feb_acheteur_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_acheteur_idx ON public.feb USING btree (acheteur_id);


--
-- Name: feb_demandeur_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_demandeur_idx ON public.feb USING btree (demandeur_id);


--
-- Name: feb_lignes_feb_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_lignes_feb_idx ON public.feb_lignes USING btree (feb_id);


--
-- Name: feb_offres_feb_lot_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_offres_feb_lot_idx ON public.feb_offres USING btree (feb_id, lot);


--
-- Name: feb_pieces_jointes_feb_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_pieces_jointes_feb_idx ON public.feb_pieces_jointes USING btree (feb_id);


--
-- Name: feb_receptions_suivi_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_receptions_suivi_idx ON public.feb_receptions USING btree (feb_suivi_id);


--
-- Name: feb_statut_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_statut_idx ON public.feb USING btree (statut);


--
-- Name: feb_suivi_feb_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_suivi_feb_idx ON public.feb_suivi USING btree (feb_id);


--
-- Name: feb_suivi_numero_da_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_suivi_numero_da_idx ON public.feb_suivi USING btree (numero_da);


--
-- Name: feb_suivi_statut_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX feb_suivi_statut_idx ON public.feb_suivi USING btree (statut);


--
-- Name: idx_articles_site_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_articles_site_id ON public.articles USING btree (site_id);


--
-- Name: idx_di_demandes_site_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_di_demandes_site_id ON public.di_demandes USING btree (site_id);


--
-- Name: import_optoplate_idx_bobine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optoplate_idx_bobine ON public.import_optoplate USING btree (num_bobine);


--
-- Name: import_optoplate_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optoplate_idx_date ON public.import_optoplate USING btree (date_import);


--
-- Name: import_optoplate_idx_immat; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optoplate_idx_immat ON public.import_optoplate USING btree (immatriculation);


--
-- Name: import_optoplate_idx_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optoplate_idx_session ON public.import_optoplate USING btree (import_session_id);


--
-- Name: import_optoplate_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optoplate_idx_site ON public.import_optoplate USING btree (site_id);


--
-- Name: import_optoplate_idx_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optoplate_idx_statut ON public.import_optoplate USING btree (statut_plaque);


--
-- Name: import_optoplate_importe_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optoplate_importe_par ON public.import_optoplate USING btree (importe_par);


--
-- Name: import_optotrace_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optotrace_idx_date ON public.import_optotrace USING btree (date_import);


--
-- Name: import_optotrace_idx_plate; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optotrace_idx_plate ON public.import_optotrace USING btree (plate_number);


--
-- Name: import_optotrace_idx_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optotrace_idx_session ON public.import_optotrace USING btree (import_session_id);


--
-- Name: import_optotrace_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optotrace_idx_site ON public.import_optotrace USING btree (site_id);


--
-- Name: import_optotrace_importe_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_optotrace_importe_par ON public.import_optotrace USING btree (importe_par);


--
-- Name: import_sessions_emuci_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_sessions_emuci_idx_date ON public.import_sessions_emuci USING btree (date_import);


--
-- Name: import_sessions_emuci_importe_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX import_sessions_emuci_importe_par ON public.import_sessions_emuci USING btree (importe_par);


--
-- Name: interventions_maintenance_equipement_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX interventions_maintenance_equipement_id ON public.interventions_maintenance USING btree (equipement_id);


--
-- Name: interventions_maintenance_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX interventions_maintenance_idx_date ON public.interventions_maintenance USING btree (date_intervention);


--
-- Name: interventions_maintenance_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX interventions_maintenance_idx_site ON public.interventions_maintenance USING btree (site_id);


--
-- Name: interventions_maintenance_idx_technicien; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX interventions_maintenance_idx_technicien ON public.interventions_maintenance USING btree (technicien_id);


--
-- Name: inventaire_corrections_detail_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaire_corrections_detail_idx ON public.inventaire_corrections USING btree (detail_id);


--
-- Name: inventaire_corrections_site_statut_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaire_corrections_site_statut_idx ON public.inventaire_corrections USING btree (site_id, statut);


--
-- Name: inventaire_details_bobines_bobine_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaire_details_bobines_bobine_id ON public.inventaire_details_bobines USING btree (bobine_id);


--
-- Name: inventaires_bobines_cree_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaires_bobines_cree_par ON public.inventaires_bobines USING btree (cree_par);


--
-- Name: inventaires_bobines_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaires_bobines_idx_date ON public.inventaires_bobines USING btree (date_inventaire);


--
-- Name: inventaires_bobines_idx_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaires_bobines_idx_statut ON public.inventaires_bobines USING btree (statut);


--
-- Name: inventaires_bobines_session_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaires_bobines_session_idx ON public.inventaires_bobines USING btree (session_id);


--
-- Name: inventaires_bobines_site_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaires_bobines_site_id ON public.inventaires_bobines USING btree (site_id);


--
-- Name: inventaires_bobines_valide_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventaires_bobines_valide_par ON public.inventaires_bobines USING btree (valide_par);


--
-- Name: litige_messages_idx_reception; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX litige_messages_idx_reception ON public.litige_messages USING btree (reception_id);


--
-- Name: litige_messages_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX litige_messages_user_id ON public.litige_messages USING btree (user_id);


--
-- Name: livraisons_consommables_consommable_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX livraisons_consommables_consommable_id ON public.livraisons_consommables USING btree (consommable_id);


--
-- Name: livraisons_consommables_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX livraisons_consommables_created_by ON public.livraisons_consommables USING btree (created_by);


--
-- Name: livraisons_consommables_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX livraisons_consommables_idx_date ON public.livraisons_consommables USING btree (date_livraison);


--
-- Name: livraisons_consommables_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX livraisons_consommables_idx_site ON public.livraisons_consommables USING btree (site_id);


--
-- Name: mouvements_bobines_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_bobines_created_by ON public.mouvements_bobines USING btree (created_by);


--
-- Name: mouvements_bobines_idx_bobine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_bobines_idx_bobine ON public.mouvements_bobines USING btree (bobine_id);


--
-- Name: mouvements_bobines_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_bobines_idx_date ON public.mouvements_bobines USING btree (created_at);


--
-- Name: mouvements_bobines_idx_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_bobines_idx_type ON public.mouvements_bobines USING btree (type);


--
-- Name: mouvements_equipements_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_equipements_created_by ON public.mouvements_equipements USING btree (created_by);


--
-- Name: mouvements_equipements_equipement_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_equipements_equipement_id ON public.mouvements_equipements USING btree (equipement_id);


--
-- Name: mouvements_equipements_site_dest_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_equipements_site_dest_id ON public.mouvements_equipements USING btree (site_dest_id);


--
-- Name: mouvements_equipements_site_source_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_equipements_site_source_id ON public.mouvements_equipements USING btree (site_source_id);


--
-- Name: mouvements_stock_idx_article; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_stock_idx_article ON public.mouvements_stock USING btree (article_id);


--
-- Name: mouvements_stock_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_stock_idx_date ON public.mouvements_stock USING btree (created_at);


--
-- Name: mouvements_stock_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mouvements_stock_idx_site ON public.mouvements_stock USING btree (site_id);


--
-- Name: nomenclature_liens_enfant_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX nomenclature_liens_enfant_id ON public.nomenclature_liens USING btree (enfant_id);


--
-- Name: notifications_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_user_id ON public.notifications USING btree (user_id);


--
-- Name: op_bobines_idx_serie; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_bobines_idx_serie ON public.op_bobines USING btree (serie);


--
-- Name: op_bobines_idx_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_bobines_idx_statut ON public.op_bobines USING btree (statut);


--
-- Name: op_bobines_site_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_bobines_site_id ON public.op_bobines USING btree (site_id);


--
-- Name: op_bobines_type_vehicule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_bobines_type_vehicule_id ON public.op_bobines USING btree (type_vehicule_id);


--
-- Name: op_films_utilises_bobine_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_films_utilises_bobine_id ON public.op_films_utilises USING btree (bobine_id);


--
-- Name: op_films_utilises_point_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_films_utilises_point_id ON public.op_films_utilises USING btree (point_id);


--
-- Name: op_films_utilises_type_vehicule_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_films_utilises_type_vehicule_id ON public.op_films_utilises USING btree (type_vehicule_id);


--
-- Name: op_pmma_utilises_idx_point_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_pmma_utilises_idx_point_id ON public.op_pmma_utilises USING btree (point_id);


--
-- Name: op_points_journaliers_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_points_journaliers_created_by ON public.op_points_journaliers USING btree (created_by);


--
-- Name: op_points_journaliers_validated_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX op_points_journaliers_validated_by ON public.op_points_journaliers USING btree (validated_by);


--
-- Name: points_emuci_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX points_emuci_idx_date ON public.points_emuci USING btree (date_point);


--
-- Name: points_emuci_idx_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX points_emuci_idx_statut ON public.points_emuci USING btree (statut);


--
-- Name: points_emuci_saisi_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX points_emuci_saisi_par ON public.points_emuci USING btree (saisi_par);


--
-- Name: points_emuci_valide_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX points_emuci_valide_par ON public.points_emuci USING btree (valide_par);


--
-- Name: points_journaliers_info_idx_site_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX points_journaliers_info_idx_site_date ON public.points_journaliers_info USING btree (site_id, date_point);


--
-- Name: points_journaliers_info_idx_technicien; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX points_journaliers_info_idx_technicien ON public.points_journaliers_info USING btree (technicien_id);


--
-- Name: points_journaliers_info_valide_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX points_journaliers_info_valide_par ON public.points_journaliers_info USING btree (valide_par);


--
-- Name: rapports_journaliers_info_idx_site_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rapports_journaliers_info_idx_site_date ON public.rapports_journaliers_info USING btree (site_id, date_rapport);


--
-- Name: rapports_journaliers_info_valide_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX rapports_journaliers_info_valide_par ON public.rapports_journaliers_info USING btree (valide_par);


--
-- Name: reception_lignes_idx_reception; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reception_lignes_idx_reception ON public.reception_lignes USING btree (reception_id);


--
-- Name: receptions_consommables_consommable_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_consommables_consommable_id ON public.receptions_consommables USING btree (consommable_id);


--
-- Name: receptions_consommables_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_consommables_created_by ON public.receptions_consommables USING btree (created_by);


--
-- Name: receptions_consommables_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_consommables_idx_date ON public.receptions_consommables USING btree (date_reception);


--
-- Name: receptions_site_consommable_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_site_consommable_id ON public.receptions_site USING btree (consommable_id);


--
-- Name: receptions_site_created_by; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_site_created_by ON public.receptions_site USING btree (created_by);


--
-- Name: receptions_site_equipement_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_site_equipement_id ON public.receptions_site USING btree (equipement_id);


--
-- Name: receptions_site_idx_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_site_idx_date ON public.receptions_site USING btree (date_reception);


--
-- Name: receptions_site_idx_site; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_site_idx_site ON public.receptions_site USING btree (site_id);


--
-- Name: receptions_site_idx_statut; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX receptions_site_idx_statut ON public.receptions_site USING btree (statut);


--
-- Name: sessions_idx_last_activity; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_idx_last_activity ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id ON public.sessions USING btree (user_id);


--
-- Name: sites_responsable_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sites_responsable_id ON public.sites USING btree (responsable_id);


--
-- Name: stock_consommables_site_site_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_consommables_site_site_id ON public.stock_consommables_site USING btree (site_id);


--
-- Name: stock_pmma_idx_site_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_pmma_idx_site_date ON public.stock_pmma USING btree (site_id, created_at);


--
-- Name: support_it_roles_affecte_par; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX support_it_roles_affecte_par ON public.support_it_roles USING btree (affecte_par);


--
-- Name: support_it_roles_idx_sous_role; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX support_it_roles_idx_sous_role ON public.support_it_roles USING btree (sous_role);


--
-- Name: users_role_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_role_id ON public.users USING btree (role_id);


--
-- Name: validations_stock_matin_gsb_user_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX validations_stock_matin_gsb_user_id ON public.validations_stock_matin USING btree (gsb_user_id);


--
-- Name: achat_parametres achat_parametres_modifie_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_parametres
    ADD CONSTRAINT achat_parametres_modifie_par_fkey FOREIGN KEY (modifie_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: affectations_equipements affectations_equipements_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affectations_equipements
    ADD CONSTRAINT affectations_equipements_ibfk_1 FOREIGN KEY (equipement_id) REFERENCES public.equipements(id);


--
-- Name: affectations_equipements affectations_equipements_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affectations_equipements
    ADD CONSTRAINT affectations_equipements_ibfk_2 FOREIGN KEY (site_dest_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: affectations_equipements affectations_equipements_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affectations_equipements
    ADD CONSTRAINT affectations_equipements_ibfk_3 FOREIGN KEY (user_dest_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: affectations_equipements affectations_equipements_ibfk_4; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affectations_equipements
    ADD CONSTRAINT affectations_equipements_ibfk_4 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: affectations_equipements affectations_equipements_ibfk_5; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affectations_equipements
    ADD CONSTRAINT affectations_equipements_ibfk_5 FOREIGN KEY (valide_n1_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: articles articles_famille_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT articles_famille_id_fkey FOREIGN KEY (famille_id) REFERENCES public.familles_achat(id) ON DELETE SET NULL;


--
-- Name: articles articles_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT articles_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: audit_log audit_log_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_log
    ADD CONSTRAINT audit_log_ibfk_1 FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: bilans_mensuels_bobines bilans_mensuels_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bilans_mensuels_bobines
    ADD CONSTRAINT bilans_mensuels_bobines_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: bilans_mensuels_bobines bilans_mensuels_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bilans_mensuels_bobines
    ADD CONSTRAINT bilans_mensuels_bobines_ibfk_2 FOREIGN KEY (inventaire_id) REFERENCES public.inventaires_bobines(id) ON DELETE SET NULL;


--
-- Name: bilans_mensuels_bobines bilans_mensuels_bobines_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bilans_mensuels_bobines
    ADD CONSTRAINT bilans_mensuels_bobines_ibfk_3 FOREIGN KEY (valide_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: budget_validations budget_validations_departement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.budget_validations
    ADD CONSTRAINT budget_validations_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES public.departements(id) ON DELETE CASCADE;


--
-- Name: budget_validations budget_validations_rejete_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.budget_validations
    ADD CONSTRAINT budget_validations_rejete_par_fkey FOREIGN KEY (rejete_par) REFERENCES public.users(id);


--
-- Name: budget_validations budget_validations_soumis_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.budget_validations
    ADD CONSTRAINT budget_validations_soumis_par_fkey FOREIGN KEY (soumis_par) REFERENCES public.users(id);


--
-- Name: budget_validations budget_validations_valide_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.budget_validations
    ADD CONSTRAINT budget_validations_valide_par_fkey FOREIGN KEY (valide_par) REFERENCES public.users(id);


--
-- Name: commande_lignes commande_lignes_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commande_lignes
    ADD CONSTRAINT commande_lignes_ibfk_1 FOREIGN KEY (commande_id) REFERENCES public.commandes(id) ON DELETE CASCADE;


--
-- Name: commandes commandes_feb_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes
    ADD CONSTRAINT commandes_feb_id_fkey FOREIGN KEY (feb_id) REFERENCES public.feb(id) ON DELETE SET NULL;


--
-- Name: commandes commandes_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes
    ADD CONSTRAINT commandes_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE CASCADE;


--
-- Name: comparaisons_stock comparaisons_stock_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comparaisons_stock
    ADD CONSTRAINT comparaisons_stock_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: comparaisons_stock comparaisons_stock_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comparaisons_stock
    ADD CONSTRAINT comparaisons_stock_ibfk_2 FOREIGN KEY (ajuste_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: config_postes_composants config_postes_composants_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_composants
    ADD CONSTRAINT config_postes_composants_ibfk_1 FOREIGN KEY (poste_id) REFERENCES public.config_postes_types(id) ON DELETE CASCADE;


--
-- Name: config_postes_composants config_postes_composants_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_composants
    ADD CONSTRAINT config_postes_composants_ibfk_2 FOREIGN KEY (nomenclature_id) REFERENCES public.nomenclatures(id);


--
-- Name: consommations_bobines consommations_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommations_bobines
    ADD CONSTRAINT consommations_bobines_ibfk_1 FOREIGN KEY (bobine_id) REFERENCES public.op_bobines(id) ON DELETE CASCADE;


--
-- Name: consommations_bobines consommations_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommations_bobines
    ADD CONSTRAINT consommations_bobines_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: consommations_bobines consommations_bobines_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommations_bobines
    ADD CONSTRAINT consommations_bobines_ibfk_3 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: delegations delegations_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delegations
    ADD CONSTRAINT delegations_ibfk_1 FOREIGN KEY (superviseur_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: delegations delegations_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delegations
    ADD CONSTRAINT delegations_ibfk_2 FOREIGN KEY (gestionnaire_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: demandes_bobines demandes_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_bobines
    ADD CONSTRAINT demandes_bobines_ibfk_1 FOREIGN KEY (bobine_id) REFERENCES public.op_bobines(id) ON DELETE CASCADE;


--
-- Name: demandes_bobines demandes_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_bobines
    ADD CONSTRAINT demandes_bobines_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: demandes_bobines demandes_bobines_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_bobines
    ADD CONSTRAINT demandes_bobines_ibfk_3 FOREIGN KEY (demande_par) REFERENCES public.users(id);


--
-- Name: demandes_bobines demandes_bobines_ibfk_4; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_bobines
    ADD CONSTRAINT demandes_bobines_ibfk_4 FOREIGN KEY (traite_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: di_demandes di_demandes_demandeur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes
    ADD CONSTRAINT di_demandes_demandeur_id_fkey FOREIGN KEY (demandeur_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: di_demandes di_demandes_n1_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes
    ADD CONSTRAINT di_demandes_n1_user_id_fkey FOREIGN KEY (n1_user_id) REFERENCES public.users(id);


--
-- Name: di_demandes di_demandes_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes
    ADD CONSTRAINT di_demandes_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: di_demandes di_demandes_traite_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes
    ADD CONSTRAINT di_demandes_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES public.users(id);


--
-- Name: di_demandes di_demandes_type_code_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes
    ADD CONSTRAINT di_demandes_type_code_fkey FOREIGN KEY (type_code) REFERENCES public.di_types(code);


--
-- Name: di_etapes di_etapes_role_code_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_etapes
    ADD CONSTRAINT di_etapes_role_code_fkey FOREIGN KEY (role_code) REFERENCES public.di_roles(code);


--
-- Name: di_etapes di_etapes_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_etapes
    ADD CONSTRAINT di_etapes_type_id_fkey FOREIGN KEY (type_id) REFERENCES public.di_types(id) ON DELETE CASCADE;


--
-- Name: di_roles di_roles_departement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_roles
    ADD CONSTRAINT di_roles_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES public.departements(id) ON DELETE SET NULL;


--
-- Name: di_user_roles di_user_roles_role_code_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_user_roles
    ADD CONSTRAINT di_user_roles_role_code_fkey FOREIGN KEY (role_code) REFERENCES public.di_roles(code) ON DELETE CASCADE;


--
-- Name: di_user_roles di_user_roles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_user_roles
    ADD CONSTRAINT di_user_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ecarts_bobines ecarts_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ecarts_bobines
    ADD CONSTRAINT ecarts_bobines_ibfk_1 FOREIGN KEY (bobine_id) REFERENCES public.op_bobines(id) ON DELETE CASCADE;


--
-- Name: ecarts_bobines ecarts_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ecarts_bobines
    ADD CONSTRAINT ecarts_bobines_ibfk_2 FOREIGN KEY (inventaire_id) REFERENCES public.inventaires_bobines(id) ON DELETE SET NULL;


--
-- Name: ecarts_bobines ecarts_bobines_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ecarts_bobines
    ADD CONSTRAINT ecarts_bobines_ibfk_3 FOREIGN KEY (resolu_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: ecarts_bobines ecarts_bobines_ibfk_4; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ecarts_bobines
    ADD CONSTRAINT ecarts_bobines_ibfk_4 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipements equipements_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipements
    ADD CONSTRAINT equipements_ibfk_1 FOREIGN KEY (nomenclature_id) REFERENCES public.nomenclatures(id);


--
-- Name: equipements equipements_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipements
    ADD CONSTRAINT equipements_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: equipements equipements_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipements
    ADD CONSTRAINT equipements_ibfk_3 FOREIGN KEY (utilisateur_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipements equipements_ibfk_4; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipements
    ADD CONSTRAINT equipements_ibfk_4 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: feb feb_acheteur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb
    ADD CONSTRAINT feb_acheteur_id_fkey FOREIGN KEY (acheteur_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: feb feb_demandeur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb
    ADD CONSTRAINT feb_demandeur_id_fkey FOREIGN KEY (demandeur_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: feb feb_departement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb
    ADD CONSTRAINT feb_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES public.departements(id) ON DELETE SET NULL;


--
-- Name: feb_lignes feb_lignes_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_lignes
    ADD CONSTRAINT feb_lignes_article_id_fkey FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE SET NULL;


--
-- Name: feb_lignes feb_lignes_famille_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_lignes
    ADD CONSTRAINT feb_lignes_famille_id_fkey FOREIGN KEY (famille_id) REFERENCES public.familles_achat(id);


--
-- Name: feb_lignes feb_lignes_feb_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_lignes
    ADD CONSTRAINT feb_lignes_feb_id_fkey FOREIGN KEY (feb_id) REFERENCES public.feb(id) ON DELETE CASCADE;


--
-- Name: feb_lignes feb_lignes_fournisseur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_lignes
    ADD CONSTRAINT feb_lignes_fournisseur_id_fkey FOREIGN KEY (fournisseur_id) REFERENCES public.fournisseurs(id);


--
-- Name: feb_lignes feb_lignes_type_achat_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_lignes
    ADD CONSTRAINT feb_lignes_type_achat_fkey FOREIGN KEY (type_achat) REFERENCES public.achat_types(code);


--
-- Name: feb_offres feb_offres_feb_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_offres
    ADD CONSTRAINT feb_offres_feb_id_fkey FOREIGN KEY (feb_id) REFERENCES public.feb(id) ON DELETE CASCADE;


--
-- Name: feb_offres feb_offres_fournisseur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_offres
    ADD CONSTRAINT feb_offres_fournisseur_id_fkey FOREIGN KEY (fournisseur_id) REFERENCES public.fournisseurs(id);


--
-- Name: feb_pieces_jointes feb_pieces_jointes_deposee_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_pieces_jointes
    ADD CONSTRAINT feb_pieces_jointes_deposee_par_fkey FOREIGN KEY (deposee_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: feb_pieces_jointes feb_pieces_jointes_feb_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_pieces_jointes
    ADD CONSTRAINT feb_pieces_jointes_feb_id_fkey FOREIGN KEY (feb_id) REFERENCES public.feb(id) ON DELETE CASCADE;


--
-- Name: feb_receptions feb_receptions_feb_suivi_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_receptions
    ADD CONSTRAINT feb_receptions_feb_suivi_id_fkey FOREIGN KEY (feb_suivi_id) REFERENCES public.feb_suivi(id) ON DELETE CASCADE;


--
-- Name: feb_receptions feb_receptions_reception_fournisseur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_receptions
    ADD CONSTRAINT feb_receptions_reception_fournisseur_id_fkey FOREIGN KEY (reception_fournisseur_id) REFERENCES public.fournisseurs(id);


--
-- Name: feb_receptions feb_receptions_recu_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_receptions
    ADD CONSTRAINT feb_receptions_recu_par_fkey FOREIGN KEY (recu_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: feb feb_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb
    ADD CONSTRAINT feb_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: feb_suivi feb_suivi_feb_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_suivi
    ADD CONSTRAINT feb_suivi_feb_id_fkey FOREIGN KEY (feb_id) REFERENCES public.feb(id) ON DELETE CASCADE;


--
-- Name: feb_suivi feb_suivi_feb_ligne_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_suivi
    ADD CONSTRAINT feb_suivi_feb_ligne_id_fkey FOREIGN KEY (feb_ligne_id) REFERENCES public.feb_lignes(id) ON DELETE CASCADE;


--
-- Name: feb_suivi feb_suivi_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_suivi
    ADD CONSTRAINT feb_suivi_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: fournisseurs fournisseurs_cree_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fournisseurs
    ADD CONSTRAINT fournisseurs_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: import_optoplate import_optoplate_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optoplate
    ADD CONSTRAINT import_optoplate_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: import_optoplate import_optoplate_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optoplate
    ADD CONSTRAINT import_optoplate_ibfk_2 FOREIGN KEY (importe_par) REFERENCES public.users(id);


--
-- Name: import_optotrace import_optotrace_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optotrace
    ADD CONSTRAINT import_optotrace_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: import_optotrace import_optotrace_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optotrace
    ADD CONSTRAINT import_optotrace_ibfk_2 FOREIGN KEY (importe_par) REFERENCES public.users(id);


--
-- Name: import_sessions_emuci import_sessions_emuci_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_sessions_emuci
    ADD CONSTRAINT import_sessions_emuci_ibfk_1 FOREIGN KEY (importe_par) REFERENCES public.users(id);


--
-- Name: interventions_maintenance interventions_maintenance_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interventions_maintenance
    ADD CONSTRAINT interventions_maintenance_ibfk_1 FOREIGN KEY (technicien_id) REFERENCES public.users(id);


--
-- Name: interventions_maintenance interventions_maintenance_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interventions_maintenance
    ADD CONSTRAINT interventions_maintenance_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: interventions_maintenance interventions_maintenance_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interventions_maintenance
    ADD CONSTRAINT interventions_maintenance_ibfk_3 FOREIGN KEY (equipement_id) REFERENCES public.equipements(id) ON DELETE SET NULL;


--
-- Name: inventaire_corrections inventaire_corrections_autorise_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_autorise_par_fkey FOREIGN KEY (autorise_par) REFERENCES public.users(id);


--
-- Name: inventaire_corrections inventaire_corrections_bobine_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_bobine_id_fkey FOREIGN KEY (bobine_id) REFERENCES public.op_bobines(id);


--
-- Name: inventaire_corrections inventaire_corrections_demandeur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_demandeur_id_fkey FOREIGN KEY (demandeur_id) REFERENCES public.users(id);


--
-- Name: inventaire_corrections inventaire_corrections_detail_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_detail_id_fkey FOREIGN KEY (detail_id) REFERENCES public.inventaire_details_bobines(id) ON DELETE CASCADE;


--
-- Name: inventaire_corrections inventaire_corrections_inventaire_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_inventaire_id_fkey FOREIGN KEY (inventaire_id) REFERENCES public.inventaires_bobines(id) ON DELETE CASCADE;


--
-- Name: inventaire_corrections inventaire_corrections_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: inventaire_corrections inventaire_corrections_traite_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections
    ADD CONSTRAINT inventaire_corrections_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES public.users(id);


--
-- Name: inventaire_details_bobines inventaire_details_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_details_bobines
    ADD CONSTRAINT inventaire_details_bobines_ibfk_1 FOREIGN KEY (inventaire_id) REFERENCES public.inventaires_bobines(id) ON DELETE CASCADE;


--
-- Name: inventaire_details_bobines inventaire_details_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_details_bobines
    ADD CONSTRAINT inventaire_details_bobines_ibfk_2 FOREIGN KEY (bobine_id) REFERENCES public.op_bobines(id) ON DELETE CASCADE;


--
-- Name: inventaire_session_sites inventaire_session_sites_session_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_session_sites
    ADD CONSTRAINT inventaire_session_sites_session_id_fkey FOREIGN KEY (session_id) REFERENCES public.inventaire_sessions(id) ON DELETE CASCADE;


--
-- Name: inventaire_session_sites inventaire_session_sites_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_session_sites
    ADD CONSTRAINT inventaire_session_sites_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE CASCADE;


--
-- Name: inventaire_sessions inventaire_sessions_cloturee_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_sessions
    ADD CONSTRAINT inventaire_sessions_cloturee_par_fkey FOREIGN KEY (cloturee_par) REFERENCES public.users(id);


--
-- Name: inventaire_sessions inventaire_sessions_ouverte_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_sessions
    ADD CONSTRAINT inventaire_sessions_ouverte_par_fkey FOREIGN KEY (ouverte_par) REFERENCES public.users(id);


--
-- Name: inventaires_bobines inventaires_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaires_bobines
    ADD CONSTRAINT inventaires_bobines_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: inventaires_bobines inventaires_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaires_bobines
    ADD CONSTRAINT inventaires_bobines_ibfk_2 FOREIGN KEY (cree_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventaires_bobines inventaires_bobines_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaires_bobines
    ADD CONSTRAINT inventaires_bobines_ibfk_3 FOREIGN KEY (valide_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: inventaires_bobines inventaires_bobines_session_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaires_bobines
    ADD CONSTRAINT inventaires_bobines_session_id_fkey FOREIGN KEY (session_id) REFERENCES public.inventaire_sessions(id);


--
-- Name: lignes_budgetaires lignes_budgetaires_departement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lignes_budgetaires
    ADD CONSTRAINT lignes_budgetaires_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES public.departements(id) ON DELETE CASCADE;


--
-- Name: lignes_budgetaires lignes_budgetaires_famille_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lignes_budgetaires
    ADD CONSTRAINT lignes_budgetaires_famille_id_fkey FOREIGN KEY (famille_id) REFERENCES public.familles_achat(id);


--
-- Name: litige_messages litige_messages_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.litige_messages
    ADD CONSTRAINT litige_messages_ibfk_1 FOREIGN KEY (reception_id) REFERENCES public.receptions_site(id) ON DELETE CASCADE;


--
-- Name: litige_messages litige_messages_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.litige_messages
    ADD CONSTRAINT litige_messages_ibfk_2 FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: livraisons_consommables livraisons_consommables_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.livraisons_consommables
    ADD CONSTRAINT livraisons_consommables_ibfk_1 FOREIGN KEY (consommable_id) REFERENCES public.consommables(id);


--
-- Name: livraisons_consommables livraisons_consommables_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.livraisons_consommables
    ADD CONSTRAINT livraisons_consommables_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: livraisons_consommables livraisons_consommables_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.livraisons_consommables
    ADD CONSTRAINT livraisons_consommables_ibfk_3 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: mouvements_bobines mouvements_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_bobines
    ADD CONSTRAINT mouvements_bobines_ibfk_1 FOREIGN KEY (bobine_id) REFERENCES public.op_bobines(id) ON DELETE CASCADE;


--
-- Name: mouvements_bobines mouvements_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_bobines
    ADD CONSTRAINT mouvements_bobines_ibfk_2 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: mouvements_equipements mouvements_equipements_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_equipements
    ADD CONSTRAINT mouvements_equipements_ibfk_1 FOREIGN KEY (equipement_id) REFERENCES public.equipements(id);


--
-- Name: mouvements_equipements mouvements_equipements_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_equipements
    ADD CONSTRAINT mouvements_equipements_ibfk_2 FOREIGN KEY (site_source_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: mouvements_equipements mouvements_equipements_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_equipements
    ADD CONSTRAINT mouvements_equipements_ibfk_3 FOREIGN KEY (site_dest_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: mouvements_equipements mouvements_equipements_ibfk_4; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_equipements
    ADD CONSTRAINT mouvements_equipements_ibfk_4 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: nomenclature_liens nomenclature_liens_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclature_liens
    ADD CONSTRAINT nomenclature_liens_ibfk_1 FOREIGN KEY (parent_id) REFERENCES public.nomenclatures(id) ON DELETE CASCADE;


--
-- Name: nomenclature_liens nomenclature_liens_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclature_liens
    ADD CONSTRAINT nomenclature_liens_ibfk_2 FOREIGN KEY (enfant_id) REFERENCES public.nomenclatures(id) ON DELETE CASCADE;


--
-- Name: notifications notifications_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: op_bobines op_bobines_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_bobines
    ADD CONSTRAINT op_bobines_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE SET NULL;


--
-- Name: op_bobines op_bobines_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_bobines
    ADD CONSTRAINT op_bobines_ibfk_2 FOREIGN KEY (type_vehicule_id) REFERENCES public.op_types_vehicule(id) ON DELETE SET NULL;


--
-- Name: op_films_utilises op_films_utilises_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_films_utilises
    ADD CONSTRAINT op_films_utilises_ibfk_1 FOREIGN KEY (point_id) REFERENCES public.op_points_journaliers(id) ON DELETE CASCADE;


--
-- Name: op_films_utilises op_films_utilises_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_films_utilises
    ADD CONSTRAINT op_films_utilises_ibfk_2 FOREIGN KEY (bobine_id) REFERENCES public.op_bobines(id);


--
-- Name: op_films_utilises op_films_utilises_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_films_utilises
    ADD CONSTRAINT op_films_utilises_ibfk_3 FOREIGN KEY (type_vehicule_id) REFERENCES public.op_types_vehicule(id);


--
-- Name: op_points_journaliers op_points_journaliers_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_points_journaliers
    ADD CONSTRAINT op_points_journaliers_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: op_points_journaliers op_points_journaliers_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_points_journaliers
    ADD CONSTRAINT op_points_journaliers_ibfk_2 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: op_points_journaliers op_points_journaliers_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_points_journaliers
    ADD CONSTRAINT op_points_journaliers_ibfk_3 FOREIGN KEY (validated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: op_stock_rivets op_stock_rivets_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_stock_rivets
    ADD CONSTRAINT op_stock_rivets_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE CASCADE;


--
-- Name: permissions permissions_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_ibfk_1 FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: points_emuci points_emuci_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_emuci
    ADD CONSTRAINT points_emuci_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: points_emuci points_emuci_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_emuci
    ADD CONSTRAINT points_emuci_ibfk_2 FOREIGN KEY (saisi_par) REFERENCES public.users(id);


--
-- Name: points_emuci points_emuci_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_emuci
    ADD CONSTRAINT points_emuci_ibfk_3 FOREIGN KEY (valide_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: points_journaliers_info points_journaliers_info_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_journaliers_info
    ADD CONSTRAINT points_journaliers_info_ibfk_1 FOREIGN KEY (technicien_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: points_journaliers_info points_journaliers_info_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_journaliers_info
    ADD CONSTRAINT points_journaliers_info_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: points_journaliers_info points_journaliers_info_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_journaliers_info
    ADD CONSTRAINT points_journaliers_info_ibfk_3 FOREIGN KEY (valide_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: rapports_journaliers_info rapports_journaliers_info_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rapports_journaliers_info
    ADD CONSTRAINT rapports_journaliers_info_ibfk_1 FOREIGN KEY (technicien_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: rapports_journaliers_info rapports_journaliers_info_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rapports_journaliers_info
    ADD CONSTRAINT rapports_journaliers_info_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: rapports_journaliers_info rapports_journaliers_info_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rapports_journaliers_info
    ADD CONSTRAINT rapports_journaliers_info_ibfk_3 FOREIGN KEY (valide_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: receptions_consommables receptions_consommables_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_consommables
    ADD CONSTRAINT receptions_consommables_ibfk_1 FOREIGN KEY (consommable_id) REFERENCES public.consommables(id);


--
-- Name: receptions_consommables receptions_consommables_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_consommables
    ADD CONSTRAINT receptions_consommables_ibfk_2 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: receptions_site receptions_site_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_site
    ADD CONSTRAINT receptions_site_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: receptions_site receptions_site_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_site
    ADD CONSTRAINT receptions_site_ibfk_2 FOREIGN KEY (equipement_id) REFERENCES public.equipements(id) ON DELETE SET NULL;


--
-- Name: receptions_site receptions_site_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_site
    ADD CONSTRAINT receptions_site_ibfk_3 FOREIGN KEY (consommable_id) REFERENCES public.consommables(id) ON DELETE SET NULL;


--
-- Name: receptions_site receptions_site_ibfk_4; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_site
    ADD CONSTRAINT receptions_site_ibfk_4 FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sessions sessions_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_ibfk_1 FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: sites sites_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_ibfk_1 FOREIGN KEY (responsable_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: stock_consommables_site stock_consommables_site_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_consommables_site
    ADD CONSTRAINT stock_consommables_site_ibfk_1 FOREIGN KEY (consommable_id) REFERENCES public.consommables(id) ON DELETE CASCADE;


--
-- Name: stock_consommables_site stock_consommables_site_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_consommables_site
    ADD CONSTRAINT stock_consommables_site_ibfk_2 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE CASCADE;


--
-- Name: stock_departement stock_departement_article_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_departement
    ADD CONSTRAINT stock_departement_article_id_fkey FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: stock_departement stock_departement_departement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_departement
    ADD CONSTRAINT stock_departement_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES public.departements(id) ON DELETE CASCADE;


--
-- Name: stock_pmma stock_pmma_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_pmma
    ADD CONSTRAINT stock_pmma_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE CASCADE;


--
-- Name: stock_pmma_site stock_pmma_site_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_pmma_site
    ADD CONSTRAINT stock_pmma_site_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id) ON DELETE CASCADE;


--
-- Name: support_it_roles support_it_roles_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_it_roles
    ADD CONSTRAINT support_it_roles_ibfk_1 FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: support_it_roles support_it_roles_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_it_roles
    ADD CONSTRAINT support_it_roles_ibfk_2 FOREIGN KEY (affecte_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: user_departements user_departements_departement_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_departements
    ADD CONSTRAINT user_departements_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES public.departements(id) ON DELETE CASCADE;


--
-- Name: user_departements user_departements_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_departements
    ADD CONSTRAINT user_departements_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_ibfk_1 FOREIGN KEY (role_id) REFERENCES public.roles(id);


--
-- Name: validations_stock_matin validations_stock_matin_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.validations_stock_matin
    ADD CONSTRAINT validations_stock_matin_ibfk_1 FOREIGN KEY (site_id) REFERENCES public.sites(id);


--
-- Name: validations_stock_matin validations_stock_matin_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.validations_stock_matin
    ADD CONSTRAINT validations_stock_matin_ibfk_2 FOREIGN KEY (gsb_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--


