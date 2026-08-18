-- ============================================================
--  Base de recette Achats — ERP EMUCI
--  À coller dans l'éditeur SQL de Neon, sur une base VIDE.
-- ============================================================
--
--  Contenu : la structure complète (90 tables) et les seuls référentiels
--  utiles à la recette du module Achats. L'historique d'exploitation du
--  dump de juin — bobines, imports, points journaliers — a été retiré :
--  il pesait 1,9 Mo sur 2 Mo et n'apporte rien ici.
--
--  Comptes créés, tous avec le mot de passe  Recette@2026
--
--     achat@recette.local     Acheteur   — prend en charge, arbitre, négocie
--     raf@recette.local       RAF        — 1er visa
--     daf@recette.local       DAF        — 2e visa au-delà de 500 000 XOF
--     pdg@recette.local       PDG        — 3e visa au-delà de 5 000 000 XOF
--     magasin@recette.local   Magasin    — expédie les commandes internes
--     testoperation@gmail.com Demandeur  — crée les FEB
--
--  Les 11 autres comptes du dump partagent ce mot de passe.
--  C'est une instance d'essai : n'y mettez aucune donnée réelle.
--
--  Deux fournisseurs (DMD, INFOSOLUCES) sont créés SANS pièces de
--  conformité. C'est volontaire : depuis le 18/08, un marché ne peut être
--  attribué qu'à un fournisseur portant ses RCCM, DFE, RIB et PIRL.
--  Les déposer fait partie de la recette.
--
--  Le magasin central est approvisionné (500 rivets, 80 paquets de papier,
--  etc.) : sans stock, toute demande partirait intégralement en achat et
--  l'arbitrage ne se verrait pas.
-- ============================================================

--
-- PostgreSQL database dump
--

\restrict K4FtUqLZP901jgd6SvFhoCvC1ZZMgAi8WIUebZ6b2gnWkcaNmJ4hyafOO3iZrHi

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

SET default_table_access_method = heap;

--
-- Name: achat_paliers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.achat_paliers (
    id integer NOT NULL,
    borne_min bigint DEFAULT 0 NOT NULL,
    borne_max bigint,
    libelle character varying(150) NOT NULL,
    signataires jsonb DEFAULT '[]'::jsonb NOT NULL,
    ordre integer DEFAULT 0 NOT NULL,
    actif smallint DEFAULT 1 NOT NULL
);


--
-- Name: achat_paliers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.achat_paliers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: achat_paliers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.achat_paliers_id_seq OWNED BY public.achat_paliers.id;


--
-- Name: achat_parametres; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.achat_parametres (
    cle character varying(80) NOT NULL,
    valeur text NOT NULL,
    libelle character varying(200) NOT NULL,
    type character varying(20) DEFAULT 'texte'::character varying NOT NULL,
    options text,
    modifie_par integer,
    modifie_le timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: achat_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.achat_types (
    id integer NOT NULL,
    code character varying(10) NOT NULL,
    libelle character varying(150) NOT NULL,
    actif smallint DEFAULT 1 NOT NULL
);


--
-- Name: achat_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.achat_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: achat_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.achat_types_id_seq OWNED BY public.achat_types.id;


--
-- Name: affectations_equipements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.affectations_equipements (
    id integer NOT NULL,
    equipement_id integer NOT NULL,
    site_dest_id integer,
    user_dest_id integer,
    statut text DEFAULT 'en_attente'::text NOT NULL,
    pdf_path character varying(255) DEFAULT NULL::character varying,
    pdf_signe_n1 character varying(255) DEFAULT NULL::character varying,
    pdf_signe_site character varying(255) DEFAULT NULL::character varying,
    notes text,
    created_by integer,
    valide_n1_by integer,
    valide_n1_at timestamp without time zone,
    recu_by integer,
    recu_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: affectations_equipements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.affectations_equipements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: affectations_equipements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.affectations_equipements_id_seq OWNED BY public.affectations_equipements.id;


--
-- Name: agents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agents (
    id integer NOT NULL,
    matricule character varying(50),
    nom character varying(100) NOT NULL,
    prenom character varying(100) DEFAULT ''::character varying NOT NULL,
    email character varying(150),
    telephone character varying(30),
    fonction character varying(150),
    departement character varying(150),
    direction character varying(150),
    site character varying(150),
    grade character varying(100),
    statut character varying(20) DEFAULT 'actif'::character varying NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: agents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agents_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agents_id_seq OWNED BY public.agents.id;


--
-- Name: articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.articles (
    id integer NOT NULL,
    code character varying(30) NOT NULL,
    libelle character varying(200) NOT NULL,
    type_article text DEFAULT 'autre'::text,
    unite character varying(20) DEFAULT 'unite'::character varying,
    description text,
    seuil_alerte integer DEFAULT 10,
    prix_unitaire integer DEFAULT 0,
    stock_global integer DEFAULT 0,
    actif smallint DEFAULT 1,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    site_id integer,
    famille_id integer
);


--
-- Name: articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.articles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.articles_id_seq OWNED BY public.articles.id;


--
-- Name: audit_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_log (
    id bigint NOT NULL,
    user_id integer,
    action text NOT NULL,
    module character varying(60) NOT NULL,
    entite_id integer,
    description text NOT NULL,
    ancienne_valeur text,
    nouvelle_valeur text,
    ip_address character varying(45) DEFAULT NULL::character varying,
    user_agent character varying(255) DEFAULT NULL::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: audit_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_log_id_seq OWNED BY public.audit_log.id;


--
-- Name: bilans_mensuels_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bilans_mensuels_bobines (
    id integer NOT NULL,
    site_id integer NOT NULL,
    mois character varying(7) NOT NULL,
    inventaire_id integer,
    stock_debut_mois integer DEFAULT 0,
    stock_fin_mois integer DEFAULT 0,
    total_films_consommes integer DEFAULT 0,
    total_films_emuci integer DEFAULT 0,
    ecart_mois integer DEFAULT 0,
    nb_inventaires_journaliers integer DEFAULT 0,
    nb_ajustements integer DEFAULT 0,
    statut text DEFAULT 'en_cours'::text NOT NULL,
    valide_par integer,
    valide_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: bilans_mensuels_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bilans_mensuels_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bilans_mensuels_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bilans_mensuels_bobines_id_seq OWNED BY public.bilans_mensuels_bobines.id;


--
-- Name: budget_validations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.budget_validations (
    id integer NOT NULL,
    departement_id integer NOT NULL,
    exercice integer NOT NULL,
    statut character varying(20) DEFAULT 'brouillon'::character varying NOT NULL,
    soumis_par integer,
    soumis_le timestamp without time zone,
    valide_par integer,
    valide_le timestamp without time zone,
    motif_rejet text,
    rejete_par integer,
    rejete_le timestamp without time zone,
    CONSTRAINT budget_validations_statut_check CHECK (((statut)::text = ANY ((ARRAY['brouillon'::character varying, 'soumis'::character varying, 'valide'::character varying, 'rejete'::character varying])::text[])))
);


--
-- Name: budget_validations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.budget_validations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: budget_validations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.budget_validations_id_seq OWNED BY public.budget_validations.id;


--
-- Name: commande_lignes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.commande_lignes (
    id integer NOT NULL,
    commande_id integer NOT NULL,
    type_article text DEFAULT 'article'::text NOT NULL,
    article_id integer,
    libelle character varying(255) NOT NULL,
    quantite integer DEFAULT 1 NOT NULL,
    unite character varying(30) DEFAULT 'unité'::character varying,
    prix_unitaire numeric(15,2) DEFAULT 0.00,
    quantite_livree integer,
    motif_ecart text,
    statut_ligne text DEFAULT 'en_attente'::text NOT NULL,
    motif_rejet text
);


--
-- Name: commande_lignes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.commande_lignes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: commande_lignes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.commande_lignes_id_seq OWNED BY public.commande_lignes.id;


--
-- Name: commandes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.commandes (
    id integer NOT NULL,
    numero_commande character varying(30) NOT NULL,
    site_id integer NOT NULL,
    statut text DEFAULT 'en_attente'::text NOT NULL,
    notes text,
    notes_livraison text,
    livraison_par integer,
    livraison_at timestamp without time zone,
    recu_par integer,
    recu_at timestamp without time zone,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    valide_par integer,
    valide_at timestamp without time zone,
    motif_rejet_global text,
    feb_id integer
);


--
-- Name: commandes_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.commandes_bobines (
    id integer NOT NULL,
    numero character varying(30) NOT NULL,
    site_id integer NOT NULL,
    type_bobine character varying(20) NOT NULL,
    libelle_type character varying(150) NOT NULL,
    statut text DEFAULT 'en_attente'::text NOT NULL,
    notes text,
    demande_par integer NOT NULL,
    superviseur_id integer,
    superviseur_at timestamp without time zone,
    motif_rejet text,
    gsb_id integer,
    gsb_at timestamp without time zone,
    recu_par integer,
    recu_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: commandes_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.commandes_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: commandes_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.commandes_bobines_id_seq OWNED BY public.commandes_bobines.id;


--
-- Name: commandes_bobines_lignes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.commandes_bobines_lignes (
    id integer NOT NULL,
    commande_id integer NOT NULL,
    bobine_id integer NOT NULL,
    numero_bobine character varying(50) NOT NULL,
    statut text DEFAULT 'assigne'::text
);


--
-- Name: commandes_bobines_lignes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.commandes_bobines_lignes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: commandes_bobines_lignes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.commandes_bobines_lignes_id_seq OWNED BY public.commandes_bobines_lignes.id;


--
-- Name: commandes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.commandes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: commandes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.commandes_id_seq OWNED BY public.commandes.id;


--
-- Name: comparaisons_stock; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.comparaisons_stock (
    id integer NOT NULL,
    site_id integer NOT NULL,
    date_comparaison date NOT NULL,
    films_emuci integer DEFAULT 0 NOT NULL,
    films_digistock integer DEFAULT 0 NOT NULL,
    ecart integer GENERATED ALWAYS AS ((films_emuci - films_digistock)) STORED,
    statut_ecart text GENERATED ALWAYS AS (
CASE
    WHEN (abs((films_emuci - films_digistock)) = 0) THEN 'ok'::text
    WHEN (abs((films_emuci - films_digistock)) <= 5) THEN 'mineur'::text
    ELSE 'majeur'::text
END) STORED,
    ajuste smallint DEFAULT 0,
    ajuste_par integer,
    ajuste_at timestamp without time zone,
    notes_ajustement text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: comparaisons_stock_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.comparaisons_stock_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: comparaisons_stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.comparaisons_stock_id_seq OWNED BY public.comparaisons_stock.id;


--
-- Name: config_postes_composants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.config_postes_composants (
    id integer NOT NULL,
    poste_id integer NOT NULL,
    nomenclature_id integer NOT NULL,
    quantite integer DEFAULT 1 NOT NULL
);


--
-- Name: config_postes_composants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.config_postes_composants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: config_postes_composants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.config_postes_composants_id_seq OWNED BY public.config_postes_composants.id;


--
-- Name: config_postes_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.config_postes_types (
    id integer NOT NULL,
    code character varying(30) NOT NULL,
    libelle character varying(100) NOT NULL,
    description text
);


--
-- Name: config_postes_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.config_postes_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: config_postes_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.config_postes_types_id_seq OWNED BY public.config_postes_types.id;


--
-- Name: configurations_site; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configurations_site (
    id integer NOT NULL,
    type_site text NOT NULL,
    nomenclature_id integer NOT NULL,
    quantite integer DEFAULT 1 NOT NULL,
    optionnel smallint DEFAULT 0
);


--
-- Name: configurations_site_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configurations_site_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configurations_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configurations_site_id_seq OWNED BY public.configurations_site.id;


--
-- Name: consommables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.consommables (
    id integer NOT NULL,
    code character varying(30) NOT NULL,
    libelle character varying(150) NOT NULL,
    unite text DEFAULT 'unite'::text NOT NULL,
    description text,
    seuil_alerte numeric(10,2) DEFAULT 10.00,
    prix_unitaire numeric(12,2) DEFAULT 0.00,
    stock_global numeric(10,2) DEFAULT 0.00,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    categorie character varying(100) DEFAULT NULL::character varying
);


--
-- Name: consommables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.consommables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: consommables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.consommables_id_seq OWNED BY public.consommables.id;


--
-- Name: consommations_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.consommations_bobines (
    id integer NOT NULL,
    bobine_id integer NOT NULL,
    site_id integer NOT NULL,
    date_conso date NOT NULL,
    quantite integer NOT NULL,
    stock_avant integer NOT NULL,
    stock_apres integer NOT NULL,
    notes text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: consommations_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.consommations_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: consommations_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.consommations_bobines_id_seq OWNED BY public.consommations_bobines.id;


--
-- Name: corrections_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corrections_bobines (
    id integer NOT NULL,
    point_id integer NOT NULL,
    bobine_id integer NOT NULL,
    site_id integer NOT NULL,
    date_point date NOT NULL,
    films_original integer NOT NULL,
    films_proposes integer NOT NULL,
    motif_gsb text NOT NULL,
    gsb_id integer NOT NULL,
    statut text DEFAULT 'en_attente'::text NOT NULL,
    coord_id integer,
    reponse_coord text,
    films_final integer,
    traite_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: corrections_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.corrections_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: corrections_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.corrections_bobines_id_seq OWNED BY public.corrections_bobines.id;


--
-- Name: delegations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.delegations (
    id integer NOT NULL,
    superviseur_id integer NOT NULL,
    gestionnaire_id integer NOT NULL,
    module character varying(50) NOT NULL,
    libelle character varying(100) NOT NULL,
    actif smallint DEFAULT 1,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: delegations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.delegations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: delegations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.delegations_id_seq OWNED BY public.delegations.id;


--
-- Name: demandes_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.demandes_bobines (
    id integer NOT NULL,
    bobine_id integer NOT NULL,
    site_id integer NOT NULL,
    demande_par integer NOT NULL,
    motif text NOT NULL,
    statut text DEFAULT 'en_attente'::text NOT NULL,
    traite_par integer,
    traite_at timestamp without time zone,
    motif_reponse text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: demandes_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.demandes_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: demandes_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.demandes_bobines_id_seq OWNED BY public.demandes_bobines.id;


--
-- Name: demandes_correction_saisie; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.demandes_correction_saisie (
    id integer NOT NULL,
    point_id integer NOT NULL,
    demande_par integer NOT NULL,
    motif text NOT NULL,
    statut text DEFAULT 'en_attente'::text NOT NULL,
    traite_par integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: demandes_correction_saisie_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.demandes_correction_saisie_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: demandes_correction_saisie_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.demandes_correction_saisie_id_seq OWNED BY public.demandes_correction_saisie.id;


--
-- Name: departements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.departements (
    id integer NOT NULL,
    code character varying(50) NOT NULL,
    label character varying(100) NOT NULL,
    ordre integer DEFAULT 0 NOT NULL,
    actif smallint DEFAULT 1 NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: departements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.departements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: departements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.departements_id_seq OWNED BY public.departements.id;


--
-- Name: di_demandes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.di_demandes (
    id integer NOT NULL,
    numero character varying(30) NOT NULL,
    type_code character varying(50) NOT NULL,
    statut character varying(30) DEFAULT 'brouillon'::character varying NOT NULL,
    etape_actuelle integer DEFAULT 0 NOT NULL,
    etape_rejet integer,
    demandeur_id integer NOT NULL,
    champs text DEFAULT '{}'::text NOT NULL,
    historique text DEFAULT '[]'::text NOT NULL,
    signatures text DEFAULT '[]'::text NOT NULL,
    workflow_snapshot text DEFAULT '[]'::text NOT NULL,
    priorite character varying(20) DEFAULT 'normal'::character varying NOT NULL,
    traite_it smallint DEFAULT 0 NOT NULL,
    traite_par integer,
    traite_date timestamp without time zone,
    submitted_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    n1_user_id integer,
    site_id integer,
    ticket_glpi character varying(50)
);


--
-- Name: di_demandes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.di_demandes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: di_demandes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.di_demandes_id_seq OWNED BY public.di_demandes.id;


--
-- Name: di_etapes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.di_etapes (
    id integer NOT NULL,
    type_id integer NOT NULL,
    role_code character varying(30) NOT NULL,
    label character varying(150) NOT NULL,
    ordre integer NOT NULL
);


--
-- Name: di_etapes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.di_etapes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: di_etapes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.di_etapes_id_seq OWNED BY public.di_etapes.id;


--
-- Name: di_plateformes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.di_plateformes (
    code character varying(50) NOT NULL,
    label character varying(100) NOT NULL,
    ordre integer DEFAULT 0 NOT NULL,
    actif smallint DEFAULT 1 NOT NULL
);


--
-- Name: di_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.di_roles (
    code character varying(30) NOT NULL,
    label character varying(100) NOT NULL,
    ordre integer DEFAULT 0 NOT NULL,
    departement_id integer
);


--
-- Name: di_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.di_types (
    id integer NOT NULL,
    code character varying(50) NOT NULL,
    label character varying(150) NOT NULL,
    description text DEFAULT ''::text,
    actif smallint DEFAULT 1 NOT NULL,
    traitement_it smallint DEFAULT 0 NOT NULL,
    date_auto_jours integer,
    ordre integer DEFAULT 0 NOT NULL
);


--
-- Name: di_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.di_types_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: di_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.di_types_id_seq OWNED BY public.di_types.id;


--
-- Name: di_user_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.di_user_roles (
    user_id integer NOT NULL,
    role_code character varying(30) NOT NULL
);


--
-- Name: distribution_lignes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.distribution_lignes (
    id integer NOT NULL,
    distribution_id integer NOT NULL,
    article_id integer,
    commande_ligne_id integer,
    libelle character varying(200) DEFAULT NULL::character varying,
    quantite_envoyee integer DEFAULT 0,
    quantite_recue integer DEFAULT 0,
    unite character varying(20) DEFAULT 'unite'::character varying,
    statut text DEFAULT 'en_cours_livraison'::text
);


--
-- Name: distribution_lignes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.distribution_lignes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: distribution_lignes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.distribution_lignes_id_seq OWNED BY public.distribution_lignes.id;


--
-- Name: distributions_site; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.distributions_site (
    id integer NOT NULL,
    numero_distribution character varying(50) NOT NULL,
    commande_id integer,
    site_id integer NOT NULL,
    date_distribution date NOT NULL,
    statut text DEFAULT 'en_cours_livraison'::text,
    notes text,
    fichier_bl character varying(255) DEFAULT NULL::character varying,
    created_by integer,
    expedie_at timestamp without time zone,
    recu_at timestamp without time zone,
    recu_par integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: distributions_site_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.distributions_site_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: distributions_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.distributions_site_id_seq OWNED BY public.distributions_site.id;


--
-- Name: ecarts_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ecarts_bobines (
    id integer NOT NULL,
    bobine_id integer NOT NULL,
    date_constat date NOT NULL,
    stock_systeme integer NOT NULL,
    stock_physique integer NOT NULL,
    ecart integer NOT NULL,
    motif text,
    source text DEFAULT 'manuel'::text NOT NULL,
    inventaire_id integer,
    statut text DEFAULT 'ouvert'::text NOT NULL,
    resolu_at timestamp without time zone,
    resolu_par integer,
    resolution_notes text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ecarts_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ecarts_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ecarts_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ecarts_bobines_id_seq OWNED BY public.ecarts_bobines.id;


--
-- Name: emuci_sites_inconnus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.emuci_sites_inconnus (
    id integer NOT NULL,
    nom_emuci character varying(150) NOT NULL,
    type_import text NOT NULL,
    nb_occurrences integer DEFAULT 1,
    premiere_apparition timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    derniere_apparition timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    statut text DEFAULT 'en_attente'::text,
    site_id_lie integer,
    traite_par integer,
    traite_at timestamp without time zone
);


--
-- Name: emuci_sites_inconnus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.emuci_sites_inconnus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: emuci_sites_inconnus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.emuci_sites_inconnus_id_seq OWNED BY public.emuci_sites_inconnus.id;


--
-- Name: equipements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipements (
    id integer NOT NULL,
    numero_serie_interne character varying(50) NOT NULL,
    numero_serie_origine character varying(100) DEFAULT NULL::character varying,
    numero_chrono integer NOT NULL,
    nomenclature_id integer NOT NULL,
    categorie text DEFAULT 'informatique'::text NOT NULL,
    site_id integer,
    utilisateur_id integer,
    etat text DEFAULT 'neuf'::text NOT NULL,
    date_acquisition date,
    date_mise_en_service date,
    date_fin_cycle date,
    marque character varying(100) DEFAULT NULL::character varying,
    modele character varying(100) DEFAULT NULL::character varying,
    observations text,
    actif smallint DEFAULT 1,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    duree_amortissement_mois integer,
    prix_achat numeric(15,2) DEFAULT 0.00,
    statut_stock text DEFAULT 'affecte'::text NOT NULL
);


--
-- Name: equipements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipements_id_seq OWNED BY public.equipements.id;


--
-- Name: familles_achat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.familles_achat (
    id integer NOT NULL,
    code character varying(50) NOT NULL,
    libelle character varying(150) NOT NULL,
    actif smallint DEFAULT 1 NOT NULL,
    compte_comptable character varying(10)
);


--
-- Name: familles_achat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.familles_achat_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: familles_achat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.familles_achat_id_seq OWNED BY public.familles_achat.id;


--
-- Name: feb; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feb (
    id integer NOT NULL,
    numero character varying(30),
    exercice integer NOT NULL,
    demandeur_id integer,
    site_id integer,
    departement_id integer,
    fonction character varying(150) DEFAULT NULL::character varying,
    urgence smallint DEFAULT 0 NOT NULL,
    objet character varying(255) NOT NULL,
    statut character varying(30) DEFAULT 'brouillon'::character varying NOT NULL,
    acheteur_id integer,
    montant_total bigint DEFAULT 0 NOT NULL,
    workflow_snapshot jsonb,
    date_creation timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    date_soumission timestamp without time zone,
    date_prise_charge timestamp without time zone,
    date_lancement_validation timestamp without time zone,
    date_confirmation timestamp without time zone,
    date_cloture timestamp without time zone,
    etape_actuelle smallint DEFAULT '-1'::integer NOT NULL,
    signatures jsonb DEFAULT '[]'::jsonb NOT NULL,
    historique jsonb DEFAULT '[]'::jsonb NOT NULL,
    etape_rejet smallint,
    motif_rejet text,
    fiche_validation_path character varying(255) DEFAULT NULL::character varying
);


--
-- Name: feb_compteurs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feb_compteurs (
    exercice integer NOT NULL,
    dernier_numero integer DEFAULT 0 NOT NULL
);


--
-- Name: feb_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feb_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feb_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feb_id_seq OWNED BY public.feb.id;


--
-- Name: feb_lignes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feb_lignes (
    id integer NOT NULL,
    feb_id integer NOT NULL,
    numero_ligne integer NOT NULL,
    designation character varying(255) NOT NULL,
    article_id integer,
    quantite integer DEFAULT 1 NOT NULL,
    unite character varying(30) DEFAULT NULL::character varying,
    famille_id integer,
    code_analytique character varying(50) DEFAULT NULL::character varying,
    type_achat character varying(10),
    lot character varying(50) DEFAULT NULL::character varying,
    fournisseur_id integer,
    montant_ttc bigint DEFAULT 0 NOT NULL,
    observation text,
    arbitrage character varying(10) DEFAULT 'achat'::character varying NOT NULL,
    fournisseur_derogation smallint DEFAULT 0 NOT NULL,
    CONSTRAINT feb_lignes_arbitrage_check CHECK (((arbitrage)::text = ANY ((ARRAY['achat'::character varying, 'stock'::character varying])::text[])))
);


--
-- Name: feb_lignes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feb_lignes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feb_lignes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feb_lignes_id_seq OWNED BY public.feb_lignes.id;


--
-- Name: feb_offres; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feb_offres (
    id integer NOT NULL,
    feb_id integer NOT NULL,
    lot character varying(50) DEFAULT NULL::character varying,
    fournisseur_id integer,
    delai_annonce integer,
    conditions_paiement character varying(200) DEFAULT NULL::character varying,
    montant_ttc bigint DEFAULT 0 NOT NULL,
    prix_initial bigint,
    observation text,
    retenue smallint DEFAULT 0 NOT NULL
);


--
-- Name: feb_offres_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feb_offres_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feb_offres_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feb_offres_id_seq OWNED BY public.feb_offres.id;


--
-- Name: feb_pieces_jointes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feb_pieces_jointes (
    id integer NOT NULL,
    feb_id integer NOT NULL,
    fichier character varying(255) NOT NULL,
    nom_origine character varying(255) NOT NULL,
    taille integer,
    mime character varying(100),
    deposee_par integer,
    deposee_le timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: feb_pieces_jointes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feb_pieces_jointes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feb_pieces_jointes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feb_pieces_jointes_id_seq OWNED BY public.feb_pieces_jointes.id;


--
-- Name: feb_receptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feb_receptions (
    id integer NOT NULL,
    feb_suivi_id integer NOT NULL,
    reception_fournisseur_id integer,
    quantite_recue integer DEFAULT 0 NOT NULL,
    date_reception date DEFAULT CURRENT_DATE NOT NULL,
    bon_livraison character varying(100) DEFAULT NULL::character varying,
    ecart integer DEFAULT 0 NOT NULL,
    motif_ecart text,
    recu_par integer,
    observation text
);


--
-- Name: feb_receptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feb_receptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feb_receptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feb_receptions_id_seq OWNED BY public.feb_receptions.id;


--
-- Name: feb_suivi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.feb_suivi (
    id integer NOT NULL,
    feb_id integer NOT NULL,
    feb_ligne_id integer,
    numero_da character varying(50) DEFAULT NULL::character varying,
    date_da date,
    numero_bc character varying(50) DEFAULT NULL::character varying,
    date_bc date,
    date_livraison_prevue date,
    date_livraison_reelle date,
    quantite_commandee integer,
    quantite_recue integer DEFAULT 0 NOT NULL,
    statut character varying(30) DEFAULT 'en_attente'::character varying NOT NULL,
    site_id integer,
    cloture_reliquat smallint DEFAULT 0 NOT NULL
);


--
-- Name: feb_suivi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.feb_suivi_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: feb_suivi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.feb_suivi_id_seq OWNED BY public.feb_suivi.id;


--
-- Name: fournisseurs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fournisseurs (
    id integer NOT NULL,
    raison_sociale character varying(200) NOT NULL,
    contact_nom character varying(150) DEFAULT NULL::character varying,
    telephone character varying(30) DEFAULT NULL::character varying,
    email character varying(180) DEFAULT NULL::character varying,
    adresse text,
    conditions_paiement character varying(200) DEFAULT NULL::character varying,
    actif smallint DEFAULT 1 NOT NULL,
    cree_par integer,
    cree_le timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    modifie_le timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    numero_rccm character varying(100) DEFAULT NULL::character varying,
    numero_dfe character varying(100) DEFAULT NULL::character varying,
    numero_rib character varying(100) DEFAULT NULL::character varying,
    coordonnees text,
    doc_rccm character varying(255) DEFAULT NULL::character varying,
    doc_idu character varying(255) DEFAULT NULL::character varying,
    doc_dfe character varying(255) DEFAULT NULL::character varying,
    doc_arf character varying(255) DEFAULT NULL::character varying,
    doc_cnps character varying(255) DEFAULT NULL::character varying,
    doc_rib character varying(255) DEFAULT NULL::character varying,
    doc_pirl character varying(255) DEFAULT NULL::character varying
);


--
-- Name: fournisseurs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fournisseurs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fournisseurs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fournisseurs_id_seq OWNED BY public.fournisseurs.id;


--
-- Name: import_optoplate; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_optoplate (
    id integer NOT NULL,
    import_session_id character varying(36) NOT NULL,
    date_import date NOT NULL,
    date_installation timestamp without time zone,
    numero_dossier character varying(50) DEFAULT NULL::character varying,
    immatriculation character varying(30) NOT NULL,
    vin character varying(20) DEFAULT NULL::character varying,
    type_plaque character varying(60) DEFAULT NULL::character varying,
    statut_plaque character varying(30) NOT NULL,
    "position" character varying(10) DEFAULT NULL::character varying,
    num_consommable character varying(30) DEFAULT NULL::character varying,
    num_bobine character varying(50) DEFAULT NULL::character varying,
    site_id_emuci character varying(20) DEFAULT NULL::character varying,
    site_nom_emuci character varying(100) DEFAULT NULL::character varying,
    site_id integer,
    importe_par integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: import_optoplate_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.import_optoplate_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: import_optoplate_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.import_optoplate_id_seq OWNED BY public.import_optoplate.id;


--
-- Name: import_optotrace; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_optotrace (
    id integer NOT NULL,
    import_session_id character varying(36) NOT NULL,
    date_import date NOT NULL,
    plate_number character varying(30),
    vin character varying(20) DEFAULT NULL::character varying,
    case_number character varying(30) DEFAULT NULL::character varying,
    category character varying(60) DEFAULT NULL::character varying,
    site_nom_emuci character varying(100) DEFAULT NULL::character varying,
    site_id integer,
    installation_date timestamp without time zone,
    is_last smallint DEFAULT 0,
    is_deleted smallint DEFAULT 0,
    importe_par integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    keyname character varying(50) DEFAULT NULL::character varying,
    batch character varying(100) DEFAULT NULL::character varying,
    project character varying(100) DEFAULT NULL::character varying,
    article character varying(100) DEFAULT NULL::character varying,
    format character varying(50) DEFAULT NULL::character varying,
    box character varying(50) DEFAULT NULL::character varying,
    quantity integer DEFAULT 0,
    type_trace character varying(50) DEFAULT NULL::character varying,
    state integer DEFAULT 0,
    first_use timestamp without time zone,
    last_use timestamp without time zone,
    sended_on timestamp without time zone,
    received_on timestamp without time zone,
    canceled_on timestamp without time zone
);


--
-- Name: import_optotrace_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.import_optotrace_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: import_optotrace_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.import_optotrace_id_seq OWNED BY public.import_optotrace.id;


--
-- Name: import_sessions_emuci; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_sessions_emuci (
    id character varying(36) NOT NULL,
    date_import date NOT NULL,
    type_import text NOT NULL,
    nb_lignes_optoplate integer DEFAULT 0,
    nb_lignes_optotrace integer DEFAULT 0,
    nb_erreurs integer DEFAULT 0,
    statut text DEFAULT 'en_cours'::text,
    importe_par integer NOT NULL,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: interventions_maintenance; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.interventions_maintenance (
    id integer NOT NULL,
    technicien_id integer NOT NULL,
    site_id integer NOT NULL,
    equipement_id integer,
    date_intervention date NOT NULL,
    type_action text DEFAULT 'maintenance_corrective'::text NOT NULL,
    description text NOT NULL,
    probleme_signale text,
    solution_apportee text,
    statut_apres text DEFAULT 'resolu'::text NOT NULL,
    duree_minutes integer,
    pieces_changees text,
    rapport_fichier character varying(255) DEFAULT NULL::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: interventions_maintenance_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.interventions_maintenance_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: interventions_maintenance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.interventions_maintenance_id_seq OWNED BY public.interventions_maintenance.id;


--
-- Name: inventaire_corrections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventaire_corrections (
    id integer NOT NULL,
    detail_id integer NOT NULL,
    inventaire_id integer NOT NULL,
    bobine_id integer NOT NULL,
    site_id integer NOT NULL,
    stock_physique_actuel integer NOT NULL,
    valeur_proposee integer,
    motif text NOT NULL,
    demandeur_id integer NOT NULL,
    statut character varying(20) DEFAULT 'en_attente'::character varying NOT NULL,
    valeur_finale integer,
    reponse text,
    traite_par integer,
    traite_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    type character varying(20) DEFAULT 'demande_site'::character varying NOT NULL,
    autorise_par integer,
    autorise_at timestamp without time zone,
    CONSTRAINT inventaire_corrections_statut_chk CHECK (((statut)::text = ANY ((ARRAY['en_attente'::character varying, 'autorise'::character varying, 'refuse'::character varying, 'traite'::character varying])::text[]))),
    CONSTRAINT inventaire_corrections_type_chk CHECK (((type)::text = ANY ((ARRAY['demande_site'::character varying, 'demande_autorisation'::character varying])::text[])))
);


--
-- Name: inventaire_corrections_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventaire_corrections_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventaire_corrections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventaire_corrections_id_seq OWNED BY public.inventaire_corrections.id;


--
-- Name: inventaire_details_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventaire_details_bobines (
    id integer NOT NULL,
    inventaire_id integer NOT NULL,
    bobine_id integer NOT NULL,
    stock_systeme integer NOT NULL,
    stock_physique integer NOT NULL,
    ecart integer NOT NULL,
    conso_quotidienne_moy numeric(8,2) DEFAULT NULL::numeric,
    jours_restants_systeme integer,
    jours_restants_physique integer,
    date_epuisement_estime date,
    notes text,
    qte_temps_reel integer,
    ecart_connu_avant integer,
    films_emuci_jour integer,
    ecart_emuci_digi integer
);


--
-- Name: inventaire_details_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventaire_details_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventaire_details_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventaire_details_bobines_id_seq OWNED BY public.inventaire_details_bobines.id;


--
-- Name: inventaire_session_sites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventaire_session_sites (
    session_id integer NOT NULL,
    site_id integer NOT NULL
);


--
-- Name: inventaire_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventaire_sessions (
    id integer NOT NULL,
    libelle character varying(150),
    date_debut date NOT NULL,
    date_fin date NOT NULL,
    statut character varying(20) DEFAULT 'ouverte'::character varying NOT NULL,
    notes text,
    ouverte_par integer,
    ouverte_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    cloturee_par integer,
    cloturee_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    type_periode character varying(20) DEFAULT 'mensuel'::character varying NOT NULL,
    CONSTRAINT inventaire_sessions_periode_chk CHECK ((date_fin >= date_debut)),
    CONSTRAINT inventaire_sessions_type_periode_chk CHECK (((type_periode)::text = ANY ((ARRAY['mensuel'::character varying, 'trimestriel'::character varying, 'semestriel'::character varying, 'annuel'::character varying])::text[])))
);


--
-- Name: inventaire_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventaire_sessions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventaire_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventaire_sessions_id_seq OWNED BY public.inventaire_sessions.id;


--
-- Name: inventaires_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventaires_bobines (
    id integer NOT NULL,
    site_id integer,
    date_inventaire date NOT NULL,
    statut text DEFAULT 'brouillon'::text NOT NULL,
    nb_bobines integer DEFAULT 0,
    nb_ecarts integer DEFAULT 0,
    notes text,
    cree_par integer,
    valide_par integer,
    valide_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    type_inventaire text DEFAULT 'journalier'::text NOT NULL,
    total_films_systeme integer DEFAULT 0,
    total_films_physique integer DEFAULT 0,
    total_films_emuci integer DEFAULT 0,
    ecart_digistock_emuci integer DEFAULT 0,
    session_id integer
);


--
-- Name: inventaires_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventaires_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventaires_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventaires_bobines_id_seq OWNED BY public.inventaires_bobines.id;


--
-- Name: lignes_budgetaires; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lignes_budgetaires (
    id integer NOT NULL,
    code_comptable character varying(50) NOT NULL,
    designation character varying(200) NOT NULL,
    exercice integer NOT NULL,
    enveloppe bigint,
    comportement character varying(20) DEFAULT 'aucun'::character varying NOT NULL,
    famille_id integer,
    actif smallint DEFAULT 1 NOT NULL,
    departement_id integer,
    CONSTRAINT lignes_budgetaires_comportement_check CHECK (((comportement)::text = ANY ((ARRAY['aucun'::character varying, 'alerte'::character varying, 'blocage'::character varying])::text[])))
);


--
-- Name: lignes_budgetaires_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lignes_budgetaires_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lignes_budgetaires_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lignes_budgetaires_id_seq OWNED BY public.lignes_budgetaires.id;


--
-- Name: litige_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.litige_messages (
    id integer NOT NULL,
    reception_id integer NOT NULL,
    user_id integer NOT NULL,
    message text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: litige_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.litige_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: litige_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.litige_messages_id_seq OWNED BY public.litige_messages.id;


--
-- Name: livraisons_consommables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.livraisons_consommables (
    id integer NOT NULL,
    consommable_id integer NOT NULL,
    site_id integer NOT NULL,
    type_mouvement text DEFAULT 'distribution'::text,
    quantite numeric(10,2) NOT NULL,
    prix_unitaire numeric(12,2) DEFAULT 0.00,
    prix_total numeric(12,2) DEFAULT 0.00,
    date_livraison date NOT NULL,
    bon_livraison character varying(100) DEFAULT NULL::character varying,
    fichier_bl character varying(255) DEFAULT NULL::character varying,
    notes text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: livraisons_consommables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.livraisons_consommables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: livraisons_consommables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.livraisons_consommables_id_seq OWNED BY public.livraisons_consommables.id;


--
-- Name: mouvements_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mouvements_bobines (
    id integer NOT NULL,
    bobine_id integer NOT NULL,
    type text NOT NULL,
    quantite integer NOT NULL,
    stock_avant integer NOT NULL,
    stock_apres integer NOT NULL,
    motif character varying(255) DEFAULT NULL::character varying,
    ref_id integer,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mouvements_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mouvements_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mouvements_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mouvements_bobines_id_seq OWNED BY public.mouvements_bobines.id;


--
-- Name: mouvements_equipements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mouvements_equipements (
    id integer NOT NULL,
    equipement_id integer NOT NULL,
    type text NOT NULL,
    site_source_id integer,
    site_dest_id integer,
    user_dest_id integer,
    notes text,
    fichier_bl character varying(255) DEFAULT NULL::character varying,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mouvements_equipements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mouvements_equipements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mouvements_equipements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mouvements_equipements_id_seq OWNED BY public.mouvements_equipements.id;


--
-- Name: mouvements_stock; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mouvements_stock (
    id integer NOT NULL,
    article_id integer,
    site_id integer,
    type_mouvement text NOT NULL,
    quantite integer NOT NULL,
    solde_apres integer DEFAULT 0,
    reference character varying(100) DEFAULT NULL::character varying,
    notes text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: mouvements_stock_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mouvements_stock_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mouvements_stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mouvements_stock_id_seq OWNED BY public.mouvements_stock.id;


--
-- Name: nomenclature_liens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nomenclature_liens (
    id integer NOT NULL,
    parent_id integer NOT NULL,
    enfant_id integer NOT NULL,
    quantite integer DEFAULT 1 NOT NULL
);


--
-- Name: nomenclature_liens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nomenclature_liens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nomenclature_liens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nomenclature_liens_id_seq OWNED BY public.nomenclature_liens.id;


--
-- Name: nomenclatures; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nomenclatures (
    id integer NOT NULL,
    code character varying(10) NOT NULL,
    categorie text DEFAULT 'informatique'::text NOT NULL,
    libelle character varying(150) NOT NULL,
    description text,
    duree_vie_mois integer,
    seuil_alerte integer DEFAULT 5,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    duree_amortissement_mois integer
);


--
-- Name: nomenclatures_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nomenclatures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nomenclatures_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nomenclatures_id_seq OWNED BY public.nomenclatures.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id integer NOT NULL,
    user_id integer,
    type text NOT NULL,
    titre character varying(200) NOT NULL,
    message text NOT NULL,
    lien character varying(255) DEFAULT NULL::character varying,
    lu smallint DEFAULT 0,
    email_envoye smallint DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- Name: op_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.op_bobines (
    id integer NOT NULL,
    numero character varying(50) NOT NULL,
    type_code character varying(10) NOT NULL,
    serie character(1) NOT NULL,
    type_vehicule_id integer,
    films_total integer DEFAULT 500 NOT NULL,
    films_utilises integer DEFAULT 0 NOT NULL,
    films_endommages integer DEFAULT 0 NOT NULL,
    films_restants integer DEFAULT 500 NOT NULL,
    site_id integer,
    statut text DEFAULT 'en_stock'::text NOT NULL,
    date_ouverture date,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    qte_initiale integer DEFAULT 500 NOT NULL,
    stock_systeme integer DEFAULT 500 NOT NULL,
    stock_physique integer,
    dernier_inventaire_id integer,
    date_creation date DEFAULT CURRENT_DATE,
    format character varying(50) DEFAULT NULL::character varying,
    notes_perte text
);


--
-- Name: op_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.op_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: op_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.op_bobines_id_seq OWNED BY public.op_bobines.id;


--
-- Name: op_films_utilises; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.op_films_utilises (
    id integer NOT NULL,
    point_id integer NOT NULL,
    bobine_id integer NOT NULL,
    type_vehicule_id integer NOT NULL,
    films_utilises integer DEFAULT 0 NOT NULL,
    films_endommages integer DEFAULT 0 NOT NULL
);


--
-- Name: op_films_utilises_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.op_films_utilises_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: op_films_utilises_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.op_films_utilises_id_seq OWNED BY public.op_films_utilises.id;


--
-- Name: op_pmma_utilises; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.op_pmma_utilises (
    id integer NOT NULL,
    point_id integer NOT NULL,
    type_pmma character varying(50) NOT NULL,
    utilises integer DEFAULT 0 NOT NULL,
    endommages integer DEFAULT 0 NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: op_pmma_utilises_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.op_pmma_utilises_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: op_pmma_utilises_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.op_pmma_utilises_id_seq OWNED BY public.op_pmma_utilises.id;


--
-- Name: op_points_journaliers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.op_points_journaliers (
    id integer NOT NULL,
    site_id integer NOT NULL,
    date_point date NOT NULL,
    type_point text DEFAULT 'point_18h'::text NOT NULL,
    statut text DEFAULT 'brouillon'::text NOT NULL,
    nb_vp integer DEFAULT 0,
    nb_camion integer DEFAULT 0,
    nb_semi integer DEFAULT 0,
    nb_moto integer DEFAULT 0,
    total_engins integer DEFAULT 0,
    total_plaques integer DEFAULT 0,
    moyenne_prod numeric(6,2) DEFAULT 0.00,
    rivets_utilises integer DEFAULT 0,
    rivets_endommages integer DEFAULT 0,
    non_poses_concessionnaires integer DEFAULT 0,
    non_poses_usagers integer DEFAULT 0,
    nb_heures_travail numeric(4,1) DEFAULT 8.0,
    observations text,
    created_by integer,
    validated_by integer,
    validated_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    correction_gp integer,
    motif_correction_gp character varying(500) DEFAULT NULL::character varying,
    corrected_by_gp integer,
    corrected_at timestamp without time zone,
    rivets_gonflables integer DEFAULT 0 NOT NULL,
    rivets_eclates integer DEFAULT 0 NOT NULL,
    motif_rejet text
);


--
-- Name: op_points_journaliers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.op_points_journaliers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: op_points_journaliers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.op_points_journaliers_id_seq OWNED BY public.op_points_journaliers.id;


--
-- Name: op_stock_rivets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.op_stock_rivets (
    id integer NOT NULL,
    site_id integer NOT NULL,
    quantite integer DEFAULT 0 NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    type_rivet character varying(20) DEFAULT 'gonflable'::character varying NOT NULL
);


--
-- Name: op_stock_rivets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.op_stock_rivets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: op_stock_rivets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.op_stock_rivets_id_seq OWNED BY public.op_stock_rivets.id;


--
-- Name: op_types_bobines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.op_types_bobines (
    id integer NOT NULL,
    code character varying(20) NOT NULL,
    libelle character varying(150) NOT NULL,
    serie character(4) NOT NULL,
    actif smallint DEFAULT 1
);


--
-- Name: op_types_bobines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.op_types_bobines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: op_types_bobines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.op_types_bobines_id_seq OWNED BY public.op_types_bobines.id;


--
-- Name: op_types_vehicule; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.op_types_vehicule (
    id integer NOT NULL,
    code character varying(20) NOT NULL,
    libelle character varying(100) NOT NULL,
    nb_plaques smallint DEFAULT 2 NOT NULL,
    nb_rivets smallint DEFAULT 4 NOT NULL,
    serie_bobine character(1) NOT NULL,
    ordre smallint DEFAULT 1
);


--
-- Name: op_types_vehicule_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.op_types_vehicule_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: op_types_vehicule_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.op_types_vehicule_id_seq OWNED BY public.op_types_vehicule.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id integer NOT NULL,
    role_id integer NOT NULL,
    module character varying(60) NOT NULL,
    can_create smallint DEFAULT 0,
    can_read smallint DEFAULT 1,
    can_update smallint DEFAULT 0,
    can_delete smallint DEFAULT 0,
    can_export smallint DEFAULT 0
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: points_emuci; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.points_emuci (
    id integer NOT NULL,
    site_id integer NOT NULL,
    date_point date NOT NULL,
    plaques_posees integer DEFAULT 0 NOT NULL,
    plaques_reservees integer DEFAULT 0 NOT NULL,
    total_films_deduits integer GENERATED ALWAYS AS ((plaques_posees + plaques_reservees)) STORED,
    notes text,
    statut text DEFAULT 'brouillon'::text NOT NULL,
    saisi_par integer NOT NULL,
    valide_par integer,
    valide_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: points_emuci_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.points_emuci_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: points_emuci_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.points_emuci_id_seq OWNED BY public.points_emuci.id;


--
-- Name: points_journaliers_info; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.points_journaliers_info (
    id integer NOT NULL,
    technicien_id integer NOT NULL,
    site_id integer NOT NULL,
    date_point date NOT NULL,
    nb_equip_ok integer DEFAULT 0,
    nb_equip_hs integer DEFAULT 0,
    nb_interventions integer DEFAULT 0,
    observations text,
    actions_preventives text,
    statut text DEFAULT 'brouillon'::text NOT NULL,
    valide_par integer,
    valide_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: points_journaliers_info_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.points_journaliers_info_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: points_journaliers_info_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.points_journaliers_info_id_seq OWNED BY public.points_journaliers_info.id;


--
-- Name: rapports_journaliers_info; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rapports_journaliers_info (
    id integer NOT NULL,
    technicien_id integer NOT NULL,
    site_id integer NOT NULL,
    date_rapport date NOT NULL,
    nb_equip_ok integer DEFAULT 0,
    nb_equip_hs integer DEFAULT 0,
    nb_equip_maintenance integer DEFAULT 0,
    nb_interventions integer DEFAULT 0,
    observations text,
    actions_preventives text,
    statut text DEFAULT 'brouillon'::text NOT NULL,
    valide_par integer,
    valide_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: rapports_journaliers_info_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rapports_journaliers_info_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rapports_journaliers_info_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rapports_journaliers_info_id_seq OWNED BY public.rapports_journaliers_info.id;


--
-- Name: reception_lignes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reception_lignes (
    id integer NOT NULL,
    reception_id integer NOT NULL,
    article_id integer,
    libelle character varying(200) DEFAULT NULL::character varying,
    quantite_attendue integer DEFAULT 0,
    quantite_recue integer DEFAULT 0,
    unite character varying(20) DEFAULT 'unite'::character varying,
    prix_unitaire integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: reception_lignes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reception_lignes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reception_lignes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reception_lignes_id_seq OWNED BY public.reception_lignes.id;


--
-- Name: receptions_consommables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.receptions_consommables (
    id integer NOT NULL,
    consommable_id integer NOT NULL,
    quantite numeric(10,2) NOT NULL,
    prix_unitaire numeric(12,2) DEFAULT 0.00,
    prix_total numeric(12,2) DEFAULT 0.00,
    date_reception date NOT NULL,
    fournisseur character varying(150) DEFAULT NULL::character varying,
    numero_bon character varying(100) DEFAULT NULL::character varying,
    notes text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: receptions_consommables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.receptions_consommables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: receptions_consommables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.receptions_consommables_id_seq OWNED BY public.receptions_consommables.id;


--
-- Name: receptions_fournisseur; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.receptions_fournisseur (
    id integer NOT NULL,
    numero_reception character varying(50) NOT NULL,
    fournisseur character varying(200) DEFAULT NULL::character varying,
    date_reception date NOT NULL,
    statut text DEFAULT 'en_attente'::text,
    notes text,
    fichier_bl character varying(255) DEFAULT NULL::character varying,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: receptions_fournisseur_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.receptions_fournisseur_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: receptions_fournisseur_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.receptions_fournisseur_id_seq OWNED BY public.receptions_fournisseur.id;


--
-- Name: receptions_site; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.receptions_site (
    id integer NOT NULL,
    site_id integer NOT NULL,
    type_reception text NOT NULL,
    equipement_id integer,
    consommable_id integer,
    quantite numeric(10,2) DEFAULT NULL::numeric,
    livraison_ref_id integer,
    mouvement_ref_id integer,
    date_reception date NOT NULL,
    fichier_fiche character varying(255) DEFAULT NULL::character varying,
    notes text,
    statut text DEFAULT 'en_attente'::text NOT NULL,
    litige_motif text,
    litige_traite_by integer,
    litige_traite_at timestamp without time zone,
    remplacement_id integer,
    remplacement_notes text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: receptions_site_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.receptions_site_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: receptions_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.receptions_site_id_seq OWNED BY public.receptions_site.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id integer NOT NULL,
    nom character varying(80) NOT NULL,
    slug character varying(80) NOT NULL,
    description text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(128) NOT NULL,
    user_id integer NOT NULL,
    ip_address character varying(45) DEFAULT NULL::character varying,
    user_agent text,
    payload text,
    last_activity integer NOT NULL
);


--
-- Name: sites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sites (
    id integer NOT NULL,
    code character varying(30) NOT NULL,
    nom character varying(150) NOT NULL,
    type text DEFAULT 'saisie'::text NOT NULL,
    option_caisse smallint DEFAULT 0,
    adresse text,
    ville character varying(100) DEFAULT NULL::character varying,
    pays character varying(100) DEFAULT 'Côte d''Ivoire'::character varying,
    responsable_id integer,
    actif smallint DEFAULT 1,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    mobile smallint DEFAULT 0,
    date_debut_mission date,
    date_fin_mission date,
    latitude numeric(10,7) DEFAULT NULL::numeric,
    longitude numeric(10,7) DEFAULT NULL::numeric,
    nom_emuci character varying(100) DEFAULT NULL::character varying
);


--
-- Name: sites_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sites_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sites_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sites_id_seq OWNED BY public.sites.id;


--
-- Name: stock_consommables_site; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_consommables_site (
    id integer NOT NULL,
    consommable_id integer NOT NULL,
    site_id integer NOT NULL,
    quantite numeric(10,2) DEFAULT 0.00,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: stock_consommables_site_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_consommables_site_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_consommables_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_consommables_site_id_seq OWNED BY public.stock_consommables_site.id;


--
-- Name: stock_departement; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_departement (
    id integer NOT NULL,
    article_id integer NOT NULL,
    departement_id integer NOT NULL,
    quantite integer DEFAULT 0 NOT NULL,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: stock_departement_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_departement_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_departement_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_departement_id_seq OWNED BY public.stock_departement.id;


--
-- Name: stock_fin_mois; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_fin_mois (
    id integer NOT NULL,
    article_id integer NOT NULL,
    site_id integer,
    annee integer NOT NULL,
    mois integer NOT NULL,
    quantite integer DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: stock_fin_mois_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_fin_mois_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_fin_mois_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_fin_mois_id_seq OWNED BY public.stock_fin_mois.id;


--
-- Name: stock_pmma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_pmma (
    id integer NOT NULL,
    site_id integer NOT NULL,
    type_pmma character varying(50) DEFAULT 'Standard'::character varying,
    quantite integer NOT NULL,
    type_mouvement text NOT NULL,
    bobine_id integer,
    notes character varying(255) DEFAULT NULL::character varying,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: stock_pmma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_pmma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_pmma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_pmma_id_seq OWNED BY public.stock_pmma.id;


--
-- Name: stock_pmma_site; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_pmma_site (
    id integer NOT NULL,
    site_id integer NOT NULL,
    type_pmma character varying(50) DEFAULT 'Standard'::character varying,
    quantite integer DEFAULT 0 NOT NULL,
    seuil_alerte integer DEFAULT 10,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: stock_pmma_site_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_pmma_site_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_pmma_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_pmma_site_id_seq OWNED BY public.stock_pmma_site.id;


--
-- Name: stock_site; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_site (
    id integer NOT NULL,
    article_id integer NOT NULL,
    site_id integer NOT NULL,
    quantite integer DEFAULT 0,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: stock_site_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_site_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_site_id_seq OWNED BY public.stock_site.id;


--
-- Name: support_it_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.support_it_roles (
    id integer NOT NULL,
    user_id integer NOT NULL,
    sous_role text NOT NULL,
    actif smallint DEFAULT 1,
    affecte_par integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: support_it_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.support_it_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: support_it_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.support_it_roles_id_seq OWNED BY public.support_it_roles.id;


--
-- Name: user_departements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_departements (
    user_id integer NOT NULL,
    departement_id integer NOT NULL,
    is_n1 smallint DEFAULT 0 NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id integer NOT NULL,
    nom character varying(100) NOT NULL,
    prenom character varying(100) NOT NULL,
    email character varying(180) NOT NULL,
    password_hash character varying(255) NOT NULL,
    role_id integer NOT NULL,
    site_id integer,
    avatar character varying(255) DEFAULT NULL::character varying,
    telephone character varying(30) DEFAULT NULL::character varying,
    signature text,
    actif smallint DEFAULT 1,
    last_login timestamp without time zone,
    reset_token character varying(100) DEFAULT NULL::character varying,
    reset_token_expiry timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    support_it_sous_roles text
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: validations_stock_matin; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.validations_stock_matin (
    id integer NOT NULL,
    site_id integer NOT NULL,
    date_validation date NOT NULL,
    statut text NOT NULL,
    nb_ecarts integer DEFAULT 0,
    details_ecarts text,
    gsb_user_id integer,
    gsb_at timestamp without time zone,
    commentaire text,
    bobines_snapshot text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: validations_stock_matin_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.validations_stock_matin_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: validations_stock_matin_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.validations_stock_matin_id_seq OWNED BY public.validations_stock_matin.id;


--
-- Name: achat_paliers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_paliers ALTER COLUMN id SET DEFAULT nextval('public.achat_paliers_id_seq'::regclass);


--
-- Name: achat_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achat_types ALTER COLUMN id SET DEFAULT nextval('public.achat_types_id_seq'::regclass);


--
-- Name: affectations_equipements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affectations_equipements ALTER COLUMN id SET DEFAULT nextval('public.affectations_equipements_id_seq'::regclass);


--
-- Name: agents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agents ALTER COLUMN id SET DEFAULT nextval('public.agents_id_seq'::regclass);


--
-- Name: articles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles ALTER COLUMN id SET DEFAULT nextval('public.articles_id_seq'::regclass);


--
-- Name: audit_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_log ALTER COLUMN id SET DEFAULT nextval('public.audit_log_id_seq'::regclass);


--
-- Name: bilans_mensuels_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bilans_mensuels_bobines ALTER COLUMN id SET DEFAULT nextval('public.bilans_mensuels_bobines_id_seq'::regclass);


--
-- Name: budget_validations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.budget_validations ALTER COLUMN id SET DEFAULT nextval('public.budget_validations_id_seq'::regclass);


--
-- Name: commande_lignes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commande_lignes ALTER COLUMN id SET DEFAULT nextval('public.commande_lignes_id_seq'::regclass);


--
-- Name: commandes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes ALTER COLUMN id SET DEFAULT nextval('public.commandes_id_seq'::regclass);


--
-- Name: commandes_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes_bobines ALTER COLUMN id SET DEFAULT nextval('public.commandes_bobines_id_seq'::regclass);


--
-- Name: commandes_bobines_lignes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.commandes_bobines_lignes ALTER COLUMN id SET DEFAULT nextval('public.commandes_bobines_lignes_id_seq'::regclass);


--
-- Name: comparaisons_stock id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comparaisons_stock ALTER COLUMN id SET DEFAULT nextval('public.comparaisons_stock_id_seq'::regclass);


--
-- Name: config_postes_composants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_composants ALTER COLUMN id SET DEFAULT nextval('public.config_postes_composants_id_seq'::regclass);


--
-- Name: config_postes_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.config_postes_types ALTER COLUMN id SET DEFAULT nextval('public.config_postes_types_id_seq'::regclass);


--
-- Name: configurations_site id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configurations_site ALTER COLUMN id SET DEFAULT nextval('public.configurations_site_id_seq'::regclass);


--
-- Name: consommables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommables ALTER COLUMN id SET DEFAULT nextval('public.consommables_id_seq'::regclass);


--
-- Name: consommations_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consommations_bobines ALTER COLUMN id SET DEFAULT nextval('public.consommations_bobines_id_seq'::regclass);


--
-- Name: corrections_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corrections_bobines ALTER COLUMN id SET DEFAULT nextval('public.corrections_bobines_id_seq'::regclass);


--
-- Name: delegations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.delegations ALTER COLUMN id SET DEFAULT nextval('public.delegations_id_seq'::regclass);


--
-- Name: demandes_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_bobines ALTER COLUMN id SET DEFAULT nextval('public.demandes_bobines_id_seq'::regclass);


--
-- Name: demandes_correction_saisie id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.demandes_correction_saisie ALTER COLUMN id SET DEFAULT nextval('public.demandes_correction_saisie_id_seq'::regclass);


--
-- Name: departements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departements ALTER COLUMN id SET DEFAULT nextval('public.departements_id_seq'::regclass);


--
-- Name: di_demandes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_demandes ALTER COLUMN id SET DEFAULT nextval('public.di_demandes_id_seq'::regclass);


--
-- Name: di_etapes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_etapes ALTER COLUMN id SET DEFAULT nextval('public.di_etapes_id_seq'::regclass);


--
-- Name: di_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.di_types ALTER COLUMN id SET DEFAULT nextval('public.di_types_id_seq'::regclass);


--
-- Name: distribution_lignes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.distribution_lignes ALTER COLUMN id SET DEFAULT nextval('public.distribution_lignes_id_seq'::regclass);


--
-- Name: distributions_site id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.distributions_site ALTER COLUMN id SET DEFAULT nextval('public.distributions_site_id_seq'::regclass);


--
-- Name: ecarts_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ecarts_bobines ALTER COLUMN id SET DEFAULT nextval('public.ecarts_bobines_id_seq'::regclass);


--
-- Name: emuci_sites_inconnus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.emuci_sites_inconnus ALTER COLUMN id SET DEFAULT nextval('public.emuci_sites_inconnus_id_seq'::regclass);


--
-- Name: equipements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipements ALTER COLUMN id SET DEFAULT nextval('public.equipements_id_seq'::regclass);


--
-- Name: familles_achat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.familles_achat ALTER COLUMN id SET DEFAULT nextval('public.familles_achat_id_seq'::regclass);


--
-- Name: feb id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb ALTER COLUMN id SET DEFAULT nextval('public.feb_id_seq'::regclass);


--
-- Name: feb_lignes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_lignes ALTER COLUMN id SET DEFAULT nextval('public.feb_lignes_id_seq'::regclass);


--
-- Name: feb_offres id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_offres ALTER COLUMN id SET DEFAULT nextval('public.feb_offres_id_seq'::regclass);


--
-- Name: feb_pieces_jointes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_pieces_jointes ALTER COLUMN id SET DEFAULT nextval('public.feb_pieces_jointes_id_seq'::regclass);


--
-- Name: feb_receptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_receptions ALTER COLUMN id SET DEFAULT nextval('public.feb_receptions_id_seq'::regclass);


--
-- Name: feb_suivi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.feb_suivi ALTER COLUMN id SET DEFAULT nextval('public.feb_suivi_id_seq'::regclass);


--
-- Name: fournisseurs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fournisseurs ALTER COLUMN id SET DEFAULT nextval('public.fournisseurs_id_seq'::regclass);


--
-- Name: import_optoplate id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optoplate ALTER COLUMN id SET DEFAULT nextval('public.import_optoplate_id_seq'::regclass);


--
-- Name: import_optotrace id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_optotrace ALTER COLUMN id SET DEFAULT nextval('public.import_optotrace_id_seq'::regclass);


--
-- Name: interventions_maintenance id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.interventions_maintenance ALTER COLUMN id SET DEFAULT nextval('public.interventions_maintenance_id_seq'::regclass);


--
-- Name: inventaire_corrections id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_corrections ALTER COLUMN id SET DEFAULT nextval('public.inventaire_corrections_id_seq'::regclass);


--
-- Name: inventaire_details_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_details_bobines ALTER COLUMN id SET DEFAULT nextval('public.inventaire_details_bobines_id_seq'::regclass);


--
-- Name: inventaire_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaire_sessions ALTER COLUMN id SET DEFAULT nextval('public.inventaire_sessions_id_seq'::regclass);


--
-- Name: inventaires_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventaires_bobines ALTER COLUMN id SET DEFAULT nextval('public.inventaires_bobines_id_seq'::regclass);


--
-- Name: lignes_budgetaires id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lignes_budgetaires ALTER COLUMN id SET DEFAULT nextval('public.lignes_budgetaires_id_seq'::regclass);


--
-- Name: litige_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.litige_messages ALTER COLUMN id SET DEFAULT nextval('public.litige_messages_id_seq'::regclass);


--
-- Name: livraisons_consommables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.livraisons_consommables ALTER COLUMN id SET DEFAULT nextval('public.livraisons_consommables_id_seq'::regclass);


--
-- Name: mouvements_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_bobines ALTER COLUMN id SET DEFAULT nextval('public.mouvements_bobines_id_seq'::regclass);


--
-- Name: mouvements_equipements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_equipements ALTER COLUMN id SET DEFAULT nextval('public.mouvements_equipements_id_seq'::regclass);


--
-- Name: mouvements_stock id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mouvements_stock ALTER COLUMN id SET DEFAULT nextval('public.mouvements_stock_id_seq'::regclass);


--
-- Name: nomenclature_liens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclature_liens ALTER COLUMN id SET DEFAULT nextval('public.nomenclature_liens_id_seq'::regclass);


--
-- Name: nomenclatures id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomenclatures ALTER COLUMN id SET DEFAULT nextval('public.nomenclatures_id_seq'::regclass);


--
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- Name: op_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_bobines ALTER COLUMN id SET DEFAULT nextval('public.op_bobines_id_seq'::regclass);


--
-- Name: op_films_utilises id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_films_utilises ALTER COLUMN id SET DEFAULT nextval('public.op_films_utilises_id_seq'::regclass);


--
-- Name: op_pmma_utilises id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_pmma_utilises ALTER COLUMN id SET DEFAULT nextval('public.op_pmma_utilises_id_seq'::regclass);


--
-- Name: op_points_journaliers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_points_journaliers ALTER COLUMN id SET DEFAULT nextval('public.op_points_journaliers_id_seq'::regclass);


--
-- Name: op_stock_rivets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_stock_rivets ALTER COLUMN id SET DEFAULT nextval('public.op_stock_rivets_id_seq'::regclass);


--
-- Name: op_types_bobines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_types_bobines ALTER COLUMN id SET DEFAULT nextval('public.op_types_bobines_id_seq'::regclass);


--
-- Name: op_types_vehicule id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.op_types_vehicule ALTER COLUMN id SET DEFAULT nextval('public.op_types_vehicule_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: points_emuci id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_emuci ALTER COLUMN id SET DEFAULT nextval('public.points_emuci_id_seq'::regclass);


--
-- Name: points_journaliers_info id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.points_journaliers_info ALTER COLUMN id SET DEFAULT nextval('public.points_journaliers_info_id_seq'::regclass);


--
-- Name: rapports_journaliers_info id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rapports_journaliers_info ALTER COLUMN id SET DEFAULT nextval('public.rapports_journaliers_info_id_seq'::regclass);


--
-- Name: reception_lignes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reception_lignes ALTER COLUMN id SET DEFAULT nextval('public.reception_lignes_id_seq'::regclass);


--
-- Name: receptions_consommables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_consommables ALTER COLUMN id SET DEFAULT nextval('public.receptions_consommables_id_seq'::regclass);


--
-- Name: receptions_fournisseur id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_fournisseur ALTER COLUMN id SET DEFAULT nextval('public.receptions_fournisseur_id_seq'::regclass);


--
-- Name: receptions_site id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.receptions_site ALTER COLUMN id SET DEFAULT nextval('public.receptions_site_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: sites id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sites ALTER COLUMN id SET DEFAULT nextval('public.sites_id_seq'::regclass);


--
-- Name: stock_consommables_site id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_consommables_site ALTER COLUMN id SET DEFAULT nextval('public.stock_consommables_site_id_seq'::regclass);


--
-- Name: stock_departement id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_departement ALTER COLUMN id SET DEFAULT nextval('public.stock_departement_id_seq'::regclass);


--
-- Name: stock_fin_mois id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_fin_mois ALTER COLUMN id SET DEFAULT nextval('public.stock_fin_mois_id_seq'::regclass);


--
-- Name: stock_pmma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_pmma ALTER COLUMN id SET DEFAULT nextval('public.stock_pmma_id_seq'::regclass);


--
-- Name: stock_pmma_site id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_pmma_site ALTER COLUMN id SET DEFAULT nextval('public.stock_pmma_site_id_seq'::regclass);


--
-- Name: stock_site id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_site ALTER COLUMN id SET DEFAULT nextval('public.stock_site_id_seq'::regclass);


--
-- Name: support_it_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.support_it_roles ALTER COLUMN id SET DEFAULT nextval('public.support_it_roles_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: validations_stock_matin id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.validations_stock_matin ALTER COLUMN id SET DEFAULT nextval('public.validations_stock_matin_id_seq'::regclass);


--
-- Data for Name: achat_paliers; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.achat_paliers (id, borne_min, borne_max, libelle, signataires, ordre, actif) FROM stdin;
1	0	500000	RAF seul	["raf"]	1	1
2	500001	5000000	RAF + DAF	["raf", "daf"]	2	1
3	5000001	\N	RAF + DAF + PDG	["raf", "daf", "dg"]	3	1
\.


--
-- Data for Name: achat_parametres; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.achat_parametres (cle, valeur, libelle, type, options, modifie_par, modifie_le) FROM stdin;
devise	XOF	Devise	texte	\N	\N	2026-08-18 21:52:42.987466
comportement_budget_defaut	alerte	Comportement par défaut sur dépassement	liste	aucun,alerte,blocage	\N	2026-08-18 21:52:42.987466
delai_livraison_standard_jours	15	Délai de livraison standard (jours)	nombre	\N	\N	2026-08-18 21:52:42.987466
seuil_retard_jours	5	Seuil de retard de livraison (jours)	nombre	\N	\N	2026-08-18 21:52:42.987466
max_lignes_feb	14	Nombre maximum de lignes par FEB	nombre	\N	\N	2026-08-18 21:52:42.987466
seuil_retard_validation_jours	5	Seuil d'alerte — FEB en attente de validation depuis (jours)	nombre	\N	\N	2026-08-18 21:52:43.376415
\.


--
-- Data for Name: achat_types; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.achat_types (id, code, libelle, actif) FROM stdin;
1	DAF	Demande d'Achat Fournitures	1
2	DAI	Demande d'Achat Immobilisation	1
3	DAH	Demande d'Achat Hors-marché	1
\.


--
-- Data for Name: affectations_equipements; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.affectations_equipements (id, equipement_id, site_dest_id, user_dest_id, statut, pdf_path, pdf_signe_n1, pdf_signe_site, notes, created_by, valide_n1_by, valide_n1_at, recu_by, recu_at, created_at) FROM stdin;
\.


--
-- Data for Name: agents; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.agents (id, matricule, nom, prenom, email, telephone, fonction, departement, direction, site, grade, statut, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: articles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.articles (id, code, libelle, type_article, unite, description, seuil_alerte, prix_unitaire, stock_global, actif, created_at, updated_at, site_id, famille_id) FROM stdin;
1	CAF	Café Moulu	autre	boite	nescafé	2	562	48	1	2026-04-02 06:17:16	2026-06-04 16:08:41	\N	8
2	SCR	Sucre	autre	boite	Sucre pour le café ou le thé	3	3250	43	1	2026-04-02 09:22:25	2026-06-04 16:01:35	\N	8
4	THE	LIPTON	autre	boite		2	1325	31	1	2026-04-03 04:29:52	2026-06-04 16:01:35	\N	8
7	SAGE	huile	autre	litre		2	1000	25	1	2026-06-02 17:08:52	2026-06-04 16:01:35	\N	8
5	PH	Papier toilette	autre	paquet	Papiers toilette	5	2000	83	1	2026-04-03 04:37:52	2026-06-04 16:01:35	\N	9
6	LT	Lotus	autre	boite		5	1500	35	1	2026-04-03 04:56:14	2026-06-04 16:01:35	\N	9
3	RVT	rivets	autre	paquet	rivets gonflabe	2	4500	705	1	2026-04-02 14:19:54	2026-06-04 16:01:35	\N	7
\.


--
-- Data for Name: audit_log; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.audit_log (id, user_id, action, module, entite_id, description, ancienne_valeur, nouvelle_valeur, ip_address, user_agent, created_at) FROM stdin;
\.


--
-- Data for Name: bilans_mensuels_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.bilans_mensuels_bobines (id, site_id, mois, inventaire_id, stock_debut_mois, stock_fin_mois, total_films_consommes, total_films_emuci, ecart_mois, nb_inventaires_journaliers, nb_ajustements, statut, valide_par, valide_at, created_at) FROM stdin;
\.


--
-- Data for Name: budget_validations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.budget_validations (id, departement_id, exercice, statut, soumis_par, soumis_le, valide_par, valide_le, motif_rejet, rejete_par, rejete_le) FROM stdin;
\.


--
-- Data for Name: commande_lignes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.commande_lignes (id, commande_id, type_article, article_id, libelle, quantite, unite, prix_unitaire, quantite_livree, motif_ecart, statut_ligne, motif_rejet) FROM stdin;
\.


--
-- Data for Name: commandes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.commandes (id, numero_commande, site_id, statut, notes, notes_livraison, livraison_par, livraison_at, recu_par, recu_at, created_by, created_at, updated_at, valide_par, valide_at, motif_rejet_global, feb_id) FROM stdin;
\.


--
-- Data for Name: commandes_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.commandes_bobines (id, numero, site_id, type_bobine, libelle_type, statut, notes, demande_par, superviseur_id, superviseur_at, motif_rejet, gsb_id, gsb_at, recu_par, recu_at, created_at) FROM stdin;
\.


--
-- Data for Name: commandes_bobines_lignes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.commandes_bobines_lignes (id, commande_id, bobine_id, numero_bobine, statut) FROM stdin;
\.


--
-- Data for Name: comparaisons_stock; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.comparaisons_stock (id, site_id, date_comparaison, films_emuci, films_digistock, ajuste, ajuste_par, ajuste_at, notes_ajustement, created_at) FROM stdin;
\.


--
-- Data for Name: config_postes_composants; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.config_postes_composants (id, poste_id, nomenclature_id, quantite) FROM stdin;
\.


--
-- Data for Name: config_postes_types; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.config_postes_types (id, code, libelle, description) FROM stdin;
\.


--
-- Data for Name: configurations_site; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.configurations_site (id, type_site, nomenclature_id, quantite, optionnel) FROM stdin;
1	pose	1	1	0
2	pose	5	1	0
3	pose	6	1	0
4	pose	7	1	0
5	pose	2	1	0
6	pose	3	1	0
7	pose	9	1	0
8	saisie	1	1	0
9	saisie	6	1	0
10	saisie	7	1	0
11	saisie	3	1	0
12	saisie	4	1	0
13	mixte	1	1	0
14	mixte	5	1	0
15	mixte	6	1	0
16	mixte	2	1	0
17	mixte	8	1	0
18	mixte	7	1	0
19	mixte	9	1	0
20	mixte	3	1	0
21	mixte	4	1	0
\.


--
-- Data for Name: consommables; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.consommables (id, code, libelle, unite, description, seuil_alerte, prix_unitaire, stock_global, created_at, updated_at, categorie) FROM stdin;
1	CAF	Café Moulu	boite	nescafé	2.00	562.00	7.99	2026-04-02 06:17:16	2026-05-28 10:17:20	\N
2	SCR	Sucre	boite	Sucre pour le café ou le thé	3.00	3250.00	13.00	2026-04-02 09:22:25	2026-04-03 05:00:06	\N
3	RVT	rivets	paquet	rivets gonflabe	2.00	4500.00	1796.00	2026-04-02 14:19:54	2026-04-27 14:35:27	\N
4	THE	LIPTON	boite		2.00	1325.00	6.00	2026-04-03 04:29:52	2026-04-20 12:21:41	\N
5	PH	Papier toilette	paquet	Papiers toilette	5.00	2000.00	20.98	2026-04-03 04:37:52	2026-04-15 22:37:46	\N
6	LT	Lotus	boite		5.00	1500.00	15.00	2026-04-03 04:56:14	2026-04-03 04:57:38	\N
7	SAGE	huile	litre		2.00	1000.00	10.00	2026-06-02 17:08:52	2026-06-03 17:34:30	\N
\.


--
-- Data for Name: consommations_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.consommations_bobines (id, bobine_id, site_id, date_conso, quantite, stock_avant, stock_apres, notes, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: corrections_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.corrections_bobines (id, point_id, bobine_id, site_id, date_point, films_original, films_proposes, motif_gsb, gsb_id, statut, coord_id, reponse_coord, films_final, traite_at, created_at) FROM stdin;
\.


--
-- Data for Name: delegations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.delegations (id, superviseur_id, gestionnaire_id, module, libelle, actif, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: demandes_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.demandes_bobines (id, bobine_id, site_id, demande_par, motif, statut, traite_par, traite_at, motif_reponse, created_at) FROM stdin;
\.


--
-- Data for Name: demandes_correction_saisie; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.demandes_correction_saisie (id, point_id, demande_par, motif, statut, traite_par, created_at) FROM stdin;
\.


--
-- Data for Name: departements; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.departements (id, code, label, ordre, actif, created_at) FROM stdin;
1	OPERATION	Opérations	0	1	2026-08-18 21:52:43.327509
2	ADMINISTRATION	Administration	0	1	2026-08-18 21:52:43.327509
3	ACHAT	Achats	0	1	2026-08-18 21:52:43.327509
\.


--
-- Data for Name: di_demandes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.di_demandes (id, numero, type_code, statut, etape_actuelle, etape_rejet, demandeur_id, champs, historique, signatures, workflow_snapshot, priorite, traite_it, traite_par, traite_date, submitted_at, created_at, updated_at, n1_user_id, site_id, ticket_glpi) FROM stdin;
\.


--
-- Data for Name: di_etapes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.di_etapes (id, type_id, role_code, label, ordre) FROM stdin;
1	1	dg	Visa Direction Generale	2
2	1	gestionnaire	Visa Administration	1
3	1	n1	Visa N+1	0
4	2	dg	Visa Direction Generale	2
5	2	it	Visa Responsable IT	1
6	2	n1	Visa N+1	0
7	3	dg	Visa Direction Generale	2
8	3	it	Visa Responsable IT	1
9	3	n1	Visa N+1	0
10	4	it	Visa Service IT	1
11	4	n1	Visa Responsable Hierarchique	0
12	5	dg	Visa Direction Generale	3
13	5	daf	Visa DAF	2
14	5	raf	Visa RAF	1
15	5	n1	Visa Responsable Hierarchique (N+1)	0
16	6	dg	Visa Direction Generale	1
17	6	it	Visa Responsable IT	0
18	7	dg	Visa Direction Generale	1
19	7	it	Visa Responsable IT	0
20	8	dg	Validation PDG (si requise)	1
21	8	gestionnaire	Visa Service Imputant	0
22	9	dg	Visa Direction Generale	1
23	9	n1	Visa N+1	0
\.


--
-- Data for Name: di_plateformes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.di_plateformes (code, label, ordre, actif) FROM stdin;
brique_additionnelle	Brique Additionnelle	1	1
nsiiv_enrolement	NSIIV Enrolement	2	1
nsiiv_optoplate	NSIIV Optoplate	3	1
nsiiv_optotrace	NSIIV Optotrace	4	1
email_professionnel	E-mail professionnel	5	1
bus_post	Bus POST	6	1
\.


--
-- Data for Name: di_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.di_roles (code, label, ordre, departement_id) FROM stdin;
n1	Responsable N+1	1	\N
raf	RAF	2	\N
daf	DAF	3	\N
dg	Direction Generale	4	\N
it	Responsable IT	5	\N
gestionnaire	Service Administration	6	\N
\.


--
-- Data for Name: di_types; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.di_types (id, code, label, description, actif, traitement_it, date_auto_jours, ordre) FROM stdin;
1	autorisation_absence	Autorisation d'absence	Demander un conge	1	0	\N	0
2	creation_acces	Creation d'acces NSIIV	Demander la creation d'acces aux plateformes NSIIV pour un agent	1	1	0	1
3	basculement_acces	Basculement d'acces	Modifier les acces plateformes d'un agent	1	1	0	2
4	basculement_compte	Basculement de compte EMUCI	Demander le changement de poste sur un compte EMUCI existant	1	1	0	3
5	transfert_agent	Transfert d'agent	Demander le transfert d'un agent vers un autre site	1	1	7	4
6	creation_site	Creation de site	Demander l'enregistrement d'un nouveau site dans le systeme NSIIV	1	1	0	5
7	changement_geolocalisation	Changement de geolocalisation	Modifier les coordonnees GPS d'un site existant	1	1	0	6
8	imputation_courrier	Imputation courrier entrant	Imputer un courrier entrant a un service ou une personne	1	0	0	7
9	exceptionnel	Demande exceptionnelle	Demande hors cadre standard - motif obligatoire	1	0	\N	8
\.


--
-- Data for Name: di_user_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.di_user_roles (user_id, role_code) FROM stdin;
\.


--
-- Data for Name: distribution_lignes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.distribution_lignes (id, distribution_id, article_id, commande_ligne_id, libelle, quantite_envoyee, quantite_recue, unite, statut) FROM stdin;
\.


--
-- Data for Name: distributions_site; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.distributions_site (id, numero_distribution, commande_id, site_id, date_distribution, statut, notes, fichier_bl, created_by, expedie_at, recu_at, recu_par, created_at) FROM stdin;
\.


--
-- Data for Name: ecarts_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ecarts_bobines (id, bobine_id, date_constat, stock_systeme, stock_physique, ecart, motif, source, inventaire_id, statut, resolu_at, resolu_par, resolution_notes, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: emuci_sites_inconnus; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.emuci_sites_inconnus (id, nom_emuci, type_import, nb_occurrences, premiere_apparition, derniere_apparition, statut, site_id_lie, traite_par, traite_at) FROM stdin;
\.


--
-- Data for Name: equipements; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.equipements (id, numero_serie_interne, numero_serie_origine, numero_chrono, nomenclature_id, categorie, site_id, utilisateur_id, etat, date_acquisition, date_mise_en_service, date_fin_cycle, marque, modele, observations, actif, created_by, created_at, updated_at, duree_amortissement_mois, prix_achat, statut_stock) FROM stdin;
\.


--
-- Data for Name: familles_achat; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.familles_achat (id, code, libelle, actif, compte_comptable) FROM stdin;
1	FOURN_BUR	Fournitures de bureau	1	605
2	CONSO_IT	Consommables informatiques	1	604
3	EQUIP	Équipements	1	2442
4	PRESTA_SVC	Prestations de services	1	638
5	TRANSPORT	Transport / Logistique	1	611
6	MAINTENANCE	Maintenance	1	624
7	CONSO_OP	Consommables opérations (rivets)	1	604
8	RESTAURATION	Restauration	1	605
9	ENTRETIEN	Fournitures d'entretien	1	605
10	ACHAT_MARCH	Achats de marchandises	1	601
11	MATIERES_PREM	Achats de matières premières	1	602
12	EMBALLAGES	Achats d'emballages	1	608
13	TRANSPORT_VENTE	Transport sur ventes	1	612
14	TRANSPORT_TIERS	Transport pour compte de tiers	1	613
15	TRANSPORT_PERSO	Transport du personnel	1	614
16	SOUS_TRAITANCE	Sous-traitance générale	1	621
17	LOCATIONS	Locations et charges locatives	1	622
18	ASSURANCES	Primes d'assurance	1	625
19	ETUDES_DOC	Études, recherches et documentation	1	626
20	PUBLICITE	Publicité, publications, relations publiques	1	627
21	TELECOM	Frais de télécommunications	1	628
22	FRAIS_BANCAIRES	Frais bancaires	1	631
23	HONORAIRES	Rémunérations d'intermédiaires et honoraires	1	632
24	FORMATION	Frais de formation du personnel	1	633
25	LICENCES	Redevances pour brevets, licences, logiciels	1	634
26	PERSONNEL_EXT	Rémunérations de personnel extérieur	1	637
27	IMPOTS_DIRECTS	Impôts et taxes directs	1	641
28	IMPOTS_INDIRECTS	Impôts et taxes indirects	1	645
29	AUTRES_IMPOTS	Autres impôts et taxes	1	647
30	CHARGES_DIVERSES	Charges diverses	1	658
31	OUTILLAGE	Matériel et outillage industriel	1	241
32	MATERIEL_BUREAU	Matériel de bureau	1	2441
33	MOBILIER_BUREAU	Mobilier de bureau	1	2443
34	VEHICULES	Matériel de transport	1	245
35	AUTRE_MATERIEL	Autres matériels	1	248
\.


--
-- Data for Name: feb; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.feb (id, numero, exercice, demandeur_id, site_id, departement_id, fonction, urgence, objet, statut, acheteur_id, montant_total, workflow_snapshot, date_creation, date_soumission, date_prise_charge, date_lancement_validation, date_confirmation, date_cloture, etape_actuelle, signatures, historique, etape_rejet, motif_rejet, fiche_validation_path) FROM stdin;
\.


--
-- Data for Name: feb_compteurs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.feb_compteurs (exercice, dernier_numero) FROM stdin;
\.


--
-- Data for Name: feb_lignes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.feb_lignes (id, feb_id, numero_ligne, designation, article_id, quantite, unite, famille_id, code_analytique, type_achat, lot, fournisseur_id, montant_ttc, observation, arbitrage, fournisseur_derogation) FROM stdin;
\.


--
-- Data for Name: feb_offres; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.feb_offres (id, feb_id, lot, fournisseur_id, delai_annonce, conditions_paiement, montant_ttc, prix_initial, observation, retenue) FROM stdin;
\.


--
-- Data for Name: feb_pieces_jointes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.feb_pieces_jointes (id, feb_id, fichier, nom_origine, taille, mime, deposee_par, deposee_le) FROM stdin;
\.


--
-- Data for Name: feb_receptions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.feb_receptions (id, feb_suivi_id, reception_fournisseur_id, quantite_recue, date_reception, bon_livraison, ecart, motif_ecart, recu_par, observation) FROM stdin;
\.


--
-- Data for Name: feb_suivi; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.feb_suivi (id, feb_id, feb_ligne_id, numero_da, date_da, numero_bc, date_bc, date_livraison_prevue, date_livraison_reelle, quantite_commandee, quantite_recue, statut, site_id, cloture_reliquat) FROM stdin;
\.


--
-- Data for Name: fournisseurs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.fournisseurs (id, raison_sociale, contact_nom, telephone, email, adresse, conditions_paiement, actif, cree_par, cree_le, modifie_le, numero_rccm, numero_dfe, numero_rib, coordonnees, doc_rccm, doc_idu, doc_dfe, doc_arf, doc_cnps, doc_rib, doc_pirl) FROM stdin;
1	DMD	Service commercial	0102030405	contact@dmd.example	\N	\N	1	\N	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N
2	INFOSOLUCES	Service commercial	0607080910	contact@infosoluces.example	\N	\N	1	\N	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N
\.


--
-- Data for Name: import_optoplate; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.import_optoplate (id, import_session_id, date_import, date_installation, numero_dossier, immatriculation, vin, type_plaque, statut_plaque, "position", num_consommable, num_bobine, site_id_emuci, site_nom_emuci, site_id, importe_par, created_at) FROM stdin;
\.


--
-- Data for Name: import_optotrace; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.import_optotrace (id, import_session_id, date_import, plate_number, vin, case_number, category, site_nom_emuci, site_id, installation_date, is_last, is_deleted, importe_par, created_at, keyname, batch, project, article, format, box, quantity, type_trace, state, first_use, last_use, sended_on, received_on, canceled_on) FROM stdin;
\.


--
-- Data for Name: import_sessions_emuci; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.import_sessions_emuci (id, date_import, type_import, nb_lignes_optoplate, nb_lignes_optotrace, nb_erreurs, statut, importe_par, notes, created_at) FROM stdin;
\.


--
-- Data for Name: interventions_maintenance; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.interventions_maintenance (id, technicien_id, site_id, equipement_id, date_intervention, type_action, description, probleme_signale, solution_apportee, statut_apres, duree_minutes, pieces_changees, rapport_fichier, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: inventaire_corrections; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventaire_corrections (id, detail_id, inventaire_id, bobine_id, site_id, stock_physique_actuel, valeur_proposee, motif, demandeur_id, statut, valeur_finale, reponse, traite_par, traite_at, created_at, type, autorise_par, autorise_at) FROM stdin;
\.


--
-- Data for Name: inventaire_details_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventaire_details_bobines (id, inventaire_id, bobine_id, stock_systeme, stock_physique, ecart, conso_quotidienne_moy, jours_restants_systeme, jours_restants_physique, date_epuisement_estime, notes, qte_temps_reel, ecart_connu_avant, films_emuci_jour, ecart_emuci_digi) FROM stdin;
\.


--
-- Data for Name: inventaire_session_sites; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventaire_session_sites (session_id, site_id) FROM stdin;
\.


--
-- Data for Name: inventaire_sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventaire_sessions (id, libelle, date_debut, date_fin, statut, notes, ouverte_par, ouverte_at, cloturee_par, cloturee_at, created_at, type_periode) FROM stdin;
\.


--
-- Data for Name: inventaires_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventaires_bobines (id, site_id, date_inventaire, statut, nb_bobines, nb_ecarts, notes, cree_par, valide_par, valide_at, created_at, type_inventaire, total_films_systeme, total_films_physique, total_films_emuci, ecart_digistock_emuci, session_id) FROM stdin;
\.


--
-- Data for Name: lignes_budgetaires; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.lignes_budgetaires (id, code_comptable, designation, exercice, enveloppe, comportement, famille_id, actif, departement_id) FROM stdin;
1	6061	Fournitures de bureau	2026	\N	alerte	1	1	\N
2	6068	Consommables informatiques	2026	\N	alerte	2	1	\N
3	2183	Équipements informatiques	2026	\N	alerte	3	1	\N
4	6226	Prestations de services	2026	\N	alerte	4	1	\N
5	6241	Transport / Logistique	2026	\N	alerte	5	1	\N
6	6152	Maintenance	2026	\N	alerte	6	1	\N
\.


--
-- Data for Name: litige_messages; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.litige_messages (id, reception_id, user_id, message, created_at) FROM stdin;
\.


--
-- Data for Name: livraisons_consommables; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.livraisons_consommables (id, consommable_id, site_id, type_mouvement, quantite, prix_unitaire, prix_total, date_livraison, bon_livraison, fichier_bl, notes, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: mouvements_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.mouvements_bobines (id, bobine_id, type, quantite, stock_avant, stock_apres, motif, ref_id, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: mouvements_equipements; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.mouvements_equipements (id, equipement_id, type, site_source_id, site_dest_id, user_dest_id, notes, fichier_bl, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: mouvements_stock; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.mouvements_stock (id, article_id, site_id, type_mouvement, quantite, solde_apres, reference, notes, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: nomenclature_liens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.nomenclature_liens (id, parent_id, enfant_id, quantite) FROM stdin;
\.


--
-- Data for Name: nomenclatures; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.nomenclatures (id, code, categorie, libelle, description, duree_vie_mois, seuil_alerte, created_at, duree_amortissement_mois) FROM stdin;
1	CLV	informatique	Clavier	Les claviers des desktop	24	2	2026-04-02 06:11:05	36
2	IMP	informatique	Imprimantes		24	5	2026-04-02 06:11:53	36
3	SR	informatique	Souris		24	5	2026-04-02 09:16:25	36
4	UNT	informatique	Unité centrale		24	5	2026-04-02 09:17:00	36
5	CLD	informatique	Client lourd		24	5	2026-04-02 09:17:31	36
6	ECR	informatique	Ecran		24	5	2026-04-02 09:18:01	36
7	OND	informatique	Onduleurs		12	5	2026-04-02 09:18:21	36
8	LPT	informatique	Laptop		36	5	2026-04-02 09:19:11	36
9	PRS	informatique	Perceuse		36	5	2026-04-02 09:19:32	36
\.


--
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notifications (id, user_id, type, titre, message, lien, lu, email_envoye, created_at) FROM stdin;
\.


--
-- Data for Name: op_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.op_bobines (id, numero, type_code, serie, type_vehicule_id, films_total, films_utilises, films_endommages, films_restants, site_id, statut, date_ouverture, created_by, created_at, qte_initiale, stock_systeme, stock_physique, dernier_inventaire_id, date_creation, format, notes_perte) FROM stdin;
\.


--
-- Data for Name: op_films_utilises; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.op_films_utilises (id, point_id, bobine_id, type_vehicule_id, films_utilises, films_endommages) FROM stdin;
\.


--
-- Data for Name: op_pmma_utilises; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.op_pmma_utilises (id, point_id, type_pmma, utilises, endommages, created_at) FROM stdin;
\.


--
-- Data for Name: op_points_journaliers; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.op_points_journaliers (id, site_id, date_point, type_point, statut, nb_vp, nb_camion, nb_semi, nb_moto, total_engins, total_plaques, moyenne_prod, rivets_utilises, rivets_endommages, non_poses_concessionnaires, non_poses_usagers, nb_heures_travail, observations, created_by, validated_by, validated_at, created_at, updated_at, correction_gp, motif_correction_gp, corrected_by_gp, corrected_at, rivets_gonflables, rivets_eclates, motif_rejet) FROM stdin;
\.


--
-- Data for Name: op_stock_rivets; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.op_stock_rivets (id, site_id, quantite, updated_at, type_rivet) FROM stdin;
\.


--
-- Data for Name: op_types_bobines; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.op_types_bobines (id, code, libelle, serie, actif) FROM stdin;
29	A001	Format Auto, version Privee	A   	1
30	A002	Format Auto, version Transport Publique	A   	1
31	A003	Format Auto, version Institution Internationale	A   	1
32	A004	Format Auto, version Diplomatique	A   	1
33	A005	Format Auto, version Gouvernementale	A   	1
34	A006	Format Auto, version Temporaire	A   	1
35	B001	Format Carre, version Privee	B   	1
36	B002	Format Carre, version Transport Publique	B   	1
37	B003	Format Carre, version Institution Internationale	B   	1
38	B004	Format Carre, version Diplomatique	B   	1
39	B005	Format Carre, version Gouvernementale	B   	1
40	B006	Format Carre, version Temporaire	B   	1
41	C001	Format Moto, version Privee	C   	1
42	C002	Format Moto, version Transport Publique	C   	1
43	C003	Format Moto, version Institution Internationale	C   	1
44	C004	Format Moto, version Diplomatique	C   	1
45	C005	Format Moto, version Gouvernementale	C   	1
46	C006	Format Moto, version Temporaire	C   	1
47	D001	Format MotoII, version Privee	D   	1
48	D002	Format MotoII, version Transport Publique	D   	1
49	D003	Format MotoII, version Institution Internationale	D   	1
50	D004	Format MotoII, version Diplomatique	D   	1
51	D005	Format MotoII, version Gouvernementale	D   	1
52	D006	Format MotoII, version Temporaire	D   	1
53	WSL001	Version Pare-brise - Privee	WSL 	1
54	WSL002	Version Pare-brise - Transport Publique	WSL 	1
55	TL001	Version Reservoir - Privee	TL  	1
56	TL002	Version Reservoir - Transport Publique	TL  	1
\.


--
-- Data for Name: op_types_vehicule; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.op_types_vehicule (id, code, libelle, nb_plaques, nb_rivets, serie_bobine, ordre) FROM stdin;
1	VP	Véhicule Particulier	2	4	A	1
2	CAM	Camion	2	4	B	2
3	SEMI	Semi-remorque	1	2	C	3
4	MOTO	Moto	1	2	D	4
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, role_id, module, can_create, can_read, can_update, can_delete, can_export) FROM stdin;
462	2	equipements	1	1	1	1	1
463	1	equipements	1	1	1	1	1
464	2	sites	1	1	1	1	1
465	1	sites	1	1	1	1	1
466	2	affectations	1	1	1	1	1
467	1	affectations	1	1	1	1	1
468	2	receptions	1	1	1	1	1
469	1	receptions	1	1	1	1	1
470	2	bobines	1	1	1	1	1
471	1	bobines	1	1	1	1	1
472	2	inventaire_bobines	1	1	1	1	1
473	1	inventaire_bobines	1	1	1	1	1
474	2	consommables	1	1	1	1	1
475	1	consommables	1	1	1	1	1
476	2	rapports	1	1	1	1	1
477	1	rapports	1	1	1	1	1
478	2	interventions	1	1	1	1	1
479	1	interventions	1	1	1	1	1
480	2	point_emuci	1	1	1	1	1
481	1	point_emuci	1	1	1	1	1
482	2	import_emuci	1	1	1	1	1
483	1	import_emuci	1	1	1	1	1
484	2	nomenclatures	1	1	1	1	1
485	1	nomenclatures	1	1	1	1	1
486	2	users	1	1	1	1	1
487	1	users	1	1	1	1	1
488	2	audit	1	1	1	1	1
489	1	audit	1	1	1	1	1
490	2	delegations	1	1	1	1	1
491	1	delegations	1	1	1	1	1
492	2	rivets	1	1	1	1	1
493	1	rivets	1	1	1	1	1
525	8	equipements	0	1	0	0	1
526	8	sites	0	1	0	0	1
527	8	affectations	0	1	0	0	1
528	8	receptions	1	1	1	0	1
529	8	bobines	1	1	1	0	1
530	8	inventaire_bobines	1	1	1	0	1
531	8	consommables	0	1	0	0	1
532	8	rapports	0	1	0	0	1
534	8	point_emuci	1	1	1	0	1
535	8	import_emuci	0	1	0	0	1
537	8	delegations	1	1	1	1	0
538	8	rivets	1	1	1	0	1
540	6	equipements	0	1	0	0	0
541	6	bobines	1	1	1	0	1
542	6	inventaire_bobines	1	1	1	0	0
543	6	receptions	1	1	1	0	1
544	6	consommables	0	1	0	0	0
545	6	rapports	0	1	0	0	1
546	6	rivets	0	1	0	0	0
547	5	equipements	1	1	1	0	1
548	5	consommables	1	1	1	0	1
549	5	receptions	1	1	1	0	1
550	5	rapports	0	1	0	0	1
551	5	sites	0	1	0	0	0
552	2	pmma	1	1	1	0	1
553	2	commandes	1	1	1	0	1
554	1	pmma	1	1	1	0	1
555	1	commandes	1	1	1	0	1
556	15	pmma	1	1	1	0	1
557	15	commandes	1	1	1	0	1
558	8	pmma	1	1	1	0	1
559	8	commandes	1	1	1	0	1
567	6	pmma	1	1	1	0	0
568	6	commandes	1	1	0	0	0
570	5	commandes	0	1	1	0	1
571	5	pmma	1	1	1	0	1
572	17	commandes	0	1	1	0	1
573	17	pmma	1	1	1	0	1
588	14	commandes	0	1	0	0	0
589	18	rapports	0	1	0	0	1
590	19	rapports	0	1	0	0	1
591	18	equipements	0	1	0	0	1
592	19	equipements	0	1	0	0	1
593	18	consommables	0	1	0	0	1
594	19	consommables	0	1	0	0	1
595	20	rapports	0	1	0	0	1
598	1	operations	1	1	1	1	1
599	2	operations	1	1	1	1	1
600	1	validation_stock	1	1	1	1	1
601	2	validation_stock	1	1	1	1	1
602	1	demandes	1	1	1	1	1
603	2	demandes	1	1	1	1	1
604	1	commandes_bobines	1	1	1	1	1
605	2	commandes_bobines	1	1	1	1	1
606	1	rapports_gsb	1	1	1	1	1
607	2	rapports_gsb	1	1	1	1	1
608	1	stock_bobines	1	1	1	1	1
609	2	stock_bobines	1	1	1	1	1
610	1	rapport_journalier	1	1	1	1	1
611	2	rapport_journalier	1	1	1	1	1
612	1	departements	1	1	1	1	1
613	2	departements	1	1	1	1	1
614	1	affectations_it	1	1	1	1	1
615	2	affectations_it	1	1	1	1	1
617	4	sites	0	1	0	0	0
618	4	equipements	0	1	0	0	0
619	4	bobines	0	1	0	0	0
620	4	inventaire_bobines	0	1	0	0	0
621	4	operations	0	1	0	0	0
622	4	commandes	0	1	0	0	0
623	4	rivets	0	1	0	0	0
624	4	pmma	0	1	0	0	0
625	4	consommables	0	1	0	0	0
626	4	demandes	0	1	0	0	0
627	4	rapports	0	1	0	0	1
628	4	rapports_gsb	0	1	0	0	1
629	4	stock_bobines	0	1	0	0	0
630	4	validation_stock	0	1	0	0	0
631	5	affectations	0	1	0	0	0
632	5	interventions	0	1	0	0	0
633	5	bobines	1	1	1	0	1
634	5	inventaire_bobines	1	1	1	0	0
635	5	stock_bobines	0	1	0	0	1
636	5	commandes_bobines	0	1	0	0	0
637	5	rivets	0	1	0	0	0
638	5	operations	0	1	0	0	0
640	5	demandes	1	1	0	0	0
641	5	rapports_gsb	0	1	0	0	1
642	5	nomenclatures	0	1	0	0	0
643	6	sites	0	1	0	0	0
644	6	operations	1	1	1	0	1
645	6	validation_stock	0	1	0	0	0
646	6	stock_bobines	0	1	0	0	0
647	6	commandes_bobines	1	1	0	0	0
596	6	point_emuci	0	1	0	0	0
649	6	demandes	1	1	0	0	0
639	5	validation_stock	1	1	0	0	0
536	8	audit	0	1	0	0	0
650	7	equipements	0	1	1	0	1
651	7	sites	0	1	0	0	0
652	7	interventions	1	1	1	0	1
653	7	nomenclatures	0	1	0	0	0
654	7	affectations	0	1	0	0	0
655	7	rapport_journalier	1	1	1	0	1
656	7	demandes	1	1	0	0	0
657	8	operations	1	1	1	0	1
659	8	stock_bobines	0	1	0	0	1
661	8	rapports_gsb	0	1	0	0	1
662	8	demandes	1	1	1	0	0
663	8	rapport_journalier	0	0	0	0	0
664	8	departements	0	0	0	0	0
665	8	affectations_it	0	0	0	0	0
666	9	point_emuci	1	1	0	0	0
667	9	import_emuci	0	1	0	0	0
668	9	operations	1	1	0	0	0
669	9	equipements	0	1	0	0	0
670	9	sites	0	1	0	0	0
671	9	bobines	0	1	0	0	0
672	9	inventaire_bobines	0	1	0	0	0
673	9	rivets	0	1	0	0	0
674	9	stock_bobines	0	1	0	0	0
675	9	demandes	1	1	0	0	0
676	13	bobines	1	1	1	0	1
677	13	inventaire_bobines	1	1	1	0	1
678	13	validation_stock	1	1	1	0	1
679	13	rapports_gsb	0	1	0	0	1
680	13	stock_bobines	0	1	0	0	1
681	13	commandes_bobines	1	1	1	0	0
682	13	commandes	1	1	0	0	0
683	13	consommables	0	1	0	0	0
684	13	equipements	0	1	0	0	0
685	13	sites	0	1	0	0	0
686	13	point_emuci	0	1	0	0	0
687	13	operations	0	1	0	0	0
689	13	rivets	0	1	0	0	0
690	13	rapports	0	1	0	0	1
691	13	demandes	1	1	0	0	0
597	14	point_emuci	0	1	1	0	0
693	14	import_emuci	0	1	0	0	0
694	14	operations	1	1	1	0	0
695	14	equipements	0	1	0	0	0
696	14	sites	0	1	0	0	0
697	14	interventions	0	1	0	0	0
698	14	bobines	0	1	0	0	0
699	14	rivets	0	1	0	0	0
700	14	stock_bobines	0	1	0	0	0
701	14	validation_stock	0	1	0	0	0
702	14	rapports	0	1	0	0	0
703	14	demandes	1	1	0	0	0
704	15	equipements	0	1	1	0	1
705	15	sites	0	1	0	0	0
706	15	nomenclatures	0	1	0	0	0
707	15	affectations	1	1	1	0	0
708	15	rapport_journalier	0	1	0	0	1
709	15	affectations_it	1	1	1	1	0
710	15	users	0	1	0	0	0
711	15	audit	0	1	0	0	0
712	15	departements	0	1	0	0	0
713	15	demandes	1	1	0	0	0
714	16	equipements	0	1	0	0	0
715	16	sites	0	1	0	0	0
716	16	bobines	0	1	0	0	0
717	16	inventaire_bobines	0	1	0	0	0
718	16	rapport_journalier	1	1	0	0	0
719	16	demandes	1	1	0	0	0
720	17	consommables	0	1	0	0	1
721	17	equipements	0	1	0	0	0
722	17	sites	0	1	0	0	0
723	17	receptions	0	1	0	0	0
724	17	rapports	0	1	0	0	1
725	17	affectations	0	1	0	0	0
726	17	demandes	1	1	0	0	0
660	8	commandes_bobines	0	1	1	0	0
688	13	pmma	1	1	1	0	1
658	8	validation_stock	1	1	0	0	0
729	15	validation_stock	1	1	0	0	0
730	4	audit	0	1	0	0	0
731	5	audit	0	1	0	0	0
732	7	audit	0	1	0	0	0
734	9	audit	0	1	0	0	0
735	16	audit	0	1	0	0	0
736	17	audit	0	1	0	0	0
737	4	interventions	0	1	0	0	0
533	8	interventions	0	1	0	0	0
739	9	interventions	0	1	0	0	0
577	15	interventions	1	1	1	0	1
578	16	interventions	1	1	1	0	1
742	17	interventions	0	1	0	0	0
743	17	bobines	0	1	0	0	0
744	17	rivets	0	1	0	0	0
745	14	commandes_bobines	0	1	0	0	0
746	4	ecarts_bobines	0	1	0	0	1
747	5	ecarts_bobines	0	1	0	0	1
748	8	ecarts_bobines	0	1	0	0	1
749	9	ecarts_bobines	0	1	0	0	1
750	13	ecarts_bobines	0	1	0	0	1
764	20	achats	1	1	1	0	0
753	5	achats	1	1	0	0	0
754	6	achats	1	1	0	0	0
755	8	achats	1	1	0	0	0
756	9	achats	1	1	0	0	0
757	13	achats	1	1	0	0	0
758	14	achats	1	1	0	0	0
759	15	achats	1	1	0	0	0
760	16	achats	1	1	0	0	0
766	7	achats	1	1	0	0	0
751	1	achats	1	1	1	1	1
752	2	achats	1	1	1	1	1
784	1	achats_suivi	1	1	1	1	1
785	2	achats_suivi	1	1	1	1	1
786	1	achats_param	1	1	1	1	1
787	2	achats_param	1	1	1	1	1
788	1	achats_dashboard	1	1	1	1	1
789	2	achats_dashboard	1	1	1	1	1
761	17	achats	1	1	1	1	1
791	17	achats_suivi	1	1	1	1	1
792	17	achats_param	1	1	1	1	1
793	17	achats_dashboard	1	1	1	1	1
762	18	achats	1	1	1	0	0
763	19	achats	1	1	1	0	0
765	4	achats	0	1	1	0	0
797	18	achats_suivi	0	1	0	0	0
798	18	achats_dashboard	0	1	0	0	0
799	19	achats_suivi	0	1	0	0	0
800	19	achats_dashboard	0	1	0	0	0
801	4	achats_suivi	0	1	0	0	0
802	4	achats_dashboard	0	1	0	0	0
803	5	achats_suivi	1	1	1	0	0
805	6	achats_suivi	1	1	0	0	0
806	18	achats_param	0	1	1	0	0
807	19	achats_param	0	1	1	0	0
808	4	achats_param	0	1	0	0	0
\.


--
-- Data for Name: points_emuci; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.points_emuci (id, site_id, date_point, plaques_posees, plaques_reservees, notes, statut, saisi_par, valide_par, valide_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: points_journaliers_info; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.points_journaliers_info (id, technicien_id, site_id, date_point, nb_equip_ok, nb_equip_hs, nb_interventions, observations, actions_preventives, statut, valide_par, valide_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: rapports_journaliers_info; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.rapports_journaliers_info (id, technicien_id, site_id, date_rapport, nb_equip_ok, nb_equip_hs, nb_equip_maintenance, nb_interventions, observations, actions_preventives, statut, valide_par, valide_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: reception_lignes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.reception_lignes (id, reception_id, article_id, libelle, quantite_attendue, quantite_recue, unite, prix_unitaire, created_at) FROM stdin;
\.


--
-- Data for Name: receptions_consommables; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.receptions_consommables (id, consommable_id, quantite, prix_unitaire, prix_total, date_reception, fournisseur, numero_bon, notes, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: receptions_fournisseur; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.receptions_fournisseur (id, numero_reception, fournisseur, date_reception, statut, notes, fichier_bl, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: receptions_site; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.receptions_site (id, site_id, type_reception, equipement_id, consommable_id, quantite, livraison_ref_id, mouvement_ref_id, date_reception, fichier_fiche, notes, statut, litige_motif, litige_traite_by, litige_traite_at, remplacement_id, remplacement_notes, created_by, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, nom, slug, description, created_at) FROM stdin;
1	Super Administrateur	superadmin	Accès total, lecture de tous les audits	2026-04-01 11:43:26
2	Administrateur	admin	Gestion utilisateurs, équipements, sites	2026-04-01 11:43:26
5	Gestionnaire de Stock	gestionnaire_stock	Gère tout le stock central : entrées, sorties, livraisons sites	2026-04-19 07:49:10
6	Coordinateur de Site	coordinateur_site	Réceptionne les commandes sur son site, fait le point journalier	2026-04-19 07:49:10
8	Superviseur Opération	superviseur_operation	Supervise les actions des coordinateurs de site	2026-04-19 07:49:10
9	Contrôleur Production	controleur_production	Saisie quotidienne des plaques posées et réservées par site (données EMUCI)	2026-04-24 18:08:51
13	Gestionnaire Stock Bobines	gestionnaire_stock_bobines	Validation stock matin, gestion demandes bobines, réajustements	2026-04-29 15:39:36
14	Gestionnaire Opération	gestionnaire_operation	Second du Superviseur Opération — reçoit les tâches déléguées	2026-04-30 14:10:42
15	Superviseur IT	superviseur_it	Accès complet informatique + supervise les Support IT	2026-04-30 14:10:42
16	Support IT	support_it	Profil flexible — sous-rôles affectables : Maintenance, Contrôleur Production, Gestionnaire Bobines	2026-04-30 14:10:42
17	Superviseur Achat	superviseur_achat	Suivi consommables, équipements, consommation des sites	2026-04-30 14:10:42
18	Responsable Administratif et Financier	raf	Validation administrative et financière des demandes internes — étape RAF du circuit	2026-08-18 21:52:42.208876
19	Directeur Administratif et Financier	daf	Validation DG des demandes financières — étape DAF du circuit	2026-08-18 21:52:42.208876
20	Directeur General	directeur_general	Direction Generale — validation finale des demandes internes (etape DG du circuit)	2026-08-18 21:52:42.232549
4	PDG	lecteur	Direction générale — supervision en lecture seule et visa DG	2026-04-01 11:43:26
7	Maintenance Informatique	maintenance_info	Rattaché à Support IT (sous-rôle Maintenance) — conservé pour l'historique d'audit	2026-04-19 07:49:10
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: sites; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sites (id, code, nom, type, option_caisse, adresse, ville, pays, responsable_id, actif, created_at, updated_at, mobile, date_debut_mission, date_fin_mission, latitude, longitude, nom_emuci) FROM stdin;
1	ABJ-01	GUICHET UNIQUE ABIDJAN	pose	1	Guichet unique abidjan vridi	ABIDJAN	Côte d'Ivoire	7	1	2026-04-02 06:06:19	2026-06-05 11:36:11	0	\N	\N	\N	\N	GUICHET UNIQUE ABIDJAN
2	ABJ-02	STAR AUTO	mixte	0		ABIDJAN	Côte d'Ivoire	\N	1	2026-04-02 06:06:49	2026-06-05 11:36:11	0	\N	\N	\N	\N	STAR AUTO
3	ABJ-03	SITE CFAO	pose	0		ABIDJAN	Côte d'Ivoire	\N	1	2026-04-02 06:07:37	2026-06-05 11:36:11	0	\N	\N	\N	\N	SITE CFAO
4	KGO-01	GUICHET UNIQUE KORHOGO	pose	0		KORHOGOO	Côte d'Ivoire	\N	1	2026-04-02 06:08:13	2026-06-05 11:36:11	0	\N	\N	\N	\N	GUICHET UNIQUE KORHOGO
5	BKE-01	GUICHET UNIQUE BOUAKE	pose	0		BOUAKE	Côte d'Ivoire	\N	1	2026-04-02 06:08:34	2026-06-05 11:36:11	0	\N	\N	\N	\N	GUICHET UNIQUE BOUAKE
6	ABJ-04	SITE DE TEST	saisie	0		ABIDJAN	Côte d'Ivoire	\N	1	2026-04-03 04:18:17	2026-04-03 04:18:17	0	\N	\N	\N	\N	\N
7	ABJ-05	CASERNE AKOUEDO FDS	pose	1		ABIDJAN	Côte d'Ivoire	\N	1	2026-04-03 04:22:59	2026-06-05 11:37:05	0	\N	\N	\N	\N	CASERNE AKOUEDO FDS
8	ENT	Administration centrale	entrepot	0		ABIDJAN	Côte d'Ivoire	\N	1	2026-04-03 04:45:47	2026-06-05 11:36:11	0	\N	\N	\N	\N	Administration centrale
9	ADM	ADMINISTRATION (Siége)	siege	0		ABIDJAN Zone4	Côte d'Ivoire	\N	1	2026-04-03 04:46:43	2026-04-03 04:46:43	0	\N	\N	\N	\N	\N
10	ABJ-06	FDS AKOUEDO	pose	1	ABIDJAN	ABIDJAN	Côte d'Ivoire	\N	1	2026-04-29 15:45:19	2026-04-29 15:45:19	0	\N	\N	\N	\N	\N
11	CAGBAN	CASERNE AGBAN FDS	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	CASERNE AGBAN FDS
12	ABOPOL	ABOBO PREFECTURE POLICE	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	ABOBO PREFECTURE POLICE
13	CBAE	CASERNE BAE FDS	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	CASERNE BAE FDS
14	DGTTC	DGTTC Parc Etat	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	DGTTC Parc Etat
15	GOCAB	GOCAB	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	GOCAB
16	SMMAN	SERVICE MOBILE MAN AOPACI	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	SERVICE MOBILE MAN AOPACI
17	SIIRAST	SIIRAST SOUBRE	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	SIIRAST SOUBRE
18	SOCIDA	SOCIDA	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	SOCIDA
19	SURYS	Surysinc	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	Surysinc
20	UMOTORS	UNITED MOTORS	pose	0	\N	\N	Côte d'Ivoire	\N	1	2026-06-05 11:36:11	2026-06-05 11:36:11	0	\N	\N	\N	\N	UNITED MOTORS
21	MAN-01	SERVICE MOBILE MAN AOPACI	pose	0		MAN	Côte d'Ivoire	\N	1	2026-06-05 11:57:15	2026-06-05 11:57:15	0	\N	\N	\N	\N	\N
22	MAG	Magasin central	magasin	0	\N	Abidjan	Côte d'Ivoire	\N	1	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	0	\N	\N	\N	\N	\N
\.


--
-- Data for Name: stock_consommables_site; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_consommables_site (id, consommable_id, site_id, quantite, updated_at) FROM stdin;
1	2	5	1.00	2026-04-02 14:18:16
2	2	3	2.00	2026-04-02 14:21:26
3	5	3	2.00	2026-04-03 04:38:56
4	6	3	5.00	2026-04-03 04:57:38
5	5	7	1.00	2026-04-03 05:02:41
6	3	1	205.00	2026-04-27 14:35:27
7	1	3	4.00	2026-04-15 22:39:31
8	4	1	1.00	2026-04-20 12:21:41
10	1	1	4.00	2026-06-04 16:10:16
\.


--
-- Data for Name: stock_departement; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_departement (id, article_id, departement_id, quantite, updated_at) FROM stdin;
\.


--
-- Data for Name: stock_fin_mois; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_fin_mois (id, article_id, site_id, annee, mois, quantite, created_at) FROM stdin;
\.


--
-- Data for Name: stock_pmma; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_pmma (id, site_id, type_pmma, quantite, type_mouvement, bobine_id, notes, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: stock_pmma_site; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_pmma_site (id, site_id, type_pmma, quantite, seuil_alerte, updated_at) FROM stdin;
\.


--
-- Data for Name: stock_site; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock_site (id, article_id, site_id, quantite, updated_at) FROM stdin;
1	2	5	1	2026-06-04 16:01:35
2	2	3	2	2026-06-04 16:01:35
3	5	3	2	2026-06-04 16:01:35
4	6	3	5	2026-06-04 16:01:35
5	5	7	1	2026-06-04 16:01:35
6	3	1	205	2026-06-04 16:01:35
7	1	3	4	2026-06-04 16:01:35
8	4	1	1	2026-06-04 16:01:35
9	1	1	4	2026-06-04 16:10:16
10	1	22	40	2026-08-18 21:54:29.842238
11	2	22	40	2026-08-18 21:54:29.842238
12	4	22	30	2026-08-18 21:54:29.842238
13	7	22	25	2026-08-18 21:54:29.842238
14	5	22	80	2026-08-18 21:54:29.842238
15	6	22	30	2026-08-18 21:54:29.842238
16	3	22	500	2026-08-18 21:54:29.842238
\.


--
-- Data for Name: support_it_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.support_it_roles (id, user_id, sous_role, actif, affecte_par, created_at) FROM stdin;
1	12	maintenance	0	3	2026-06-05 15:34:12
2	12	controleur_production	0	3	2026-06-05 15:34:15
3	12	gestionnaire_bobines	0	3	2026-06-05 15:34:16
4	6	maintenance	1	\N	2026-08-18 21:52:42.476181
\.


--
-- Data for Name: user_departements; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_departements (user_id, departement_id, is_n1, created_at) FROM stdin;
5	1	0	2026-08-18 21:54:29.842238
14	3	0	2026-08-18 21:54:29.842238
15	2	0	2026-08-18 21:54:29.842238
16	2	0	2026-08-18 21:54:29.842238
17	2	0	2026-08-18 21:54:29.842238
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, nom, prenom, email, password_hash, role_id, site_id, avatar, telephone, signature, actif, last_login, reset_token, reset_token_expiry, created_at, updated_at, support_it_sous_roles) FROM stdin;
1	Admin	Super	admin@stockapp.local	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	1	\N	\N	\N	\N	1	\N	3ab6396e0e7db7593d436c5342a1060ca1963089fabbe18d8d46c79dbd5a9684	2026-04-01 13:35:27	2026-04-01 11:43:26	2026-04-01 12:35:27	\N
2	KOUASSI	RITA	kri@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	2	1	\N	0789745649	\N	0	\N	\N	\N	2026-04-01 13:52:31	2026-04-19 08:05:33	\N
3	DON	Axelle	donruthaxelle01@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	1	\N	\N	\N	\N	1	2026-06-09 11:21:37	\N	\N	2026-04-02 06:00:26	2026-06-09 11:21:37	\N
5	operation	test	testoperation@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	8	9	\N	+22507897462222	\N	1	2026-06-05 12:46:13	\N	\N	2026-04-19 08:04:30	2026-06-05 12:46:13	\N
7	cordo	test	testcordo@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	6	1	\N	+22507897462222	\N	1	2026-06-05 09:34:48	\N	\N	2026-04-19 08:06:45	2026-06-05 09:34:48	\N
8	stock	test	teststock@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	5	\N	\N	+22507897462222	\N	1	2026-06-05 12:47:08	\N	\N	2026-04-19 08:07:28	2026-06-05 12:47:08	\N
9	gestion	Pose	gestionpose@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	9	\N	\N	+22507897462222	\N	1	2026-04-27 17:20:11	\N	\N	2026-04-24 18:11:36	2026-04-27 17:20:11	\N
10	cordo2	test	testcordo2@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	6	3	\N	+2250504333315	\N	1	2026-06-05 15:39:12	\N	\N	2026-06-05 10:16:25	2026-06-05 15:39:12	\N
11	bobine	stock	stockbobine@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	13	9	\N	+2250504333315	\N	1	2026-06-05 13:05:36	\N	\N	2026-06-05 11:44:39	2026-06-05 13:05:36	\N
12	IT	INFO	supit@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	16	9	\N	+2250504333315	\N	1	\N	\N	\N	2026-06-05 15:33:45	2026-06-05 15:33:45	\N
4	KOUASSI	BERTRAND	bertrand@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	5	\N	\N		\N	1	2026-05-29 14:18:38	\N	\N	2026-04-02 11:27:44	2026-05-29 14:18:38	\N
6	info	test	testinfo@gmail.com	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	16	9	\N	+22507897462223	\N	1	2026-06-04 17:09:38	\N	\N	2026-04-19 08:05:15	2026-06-04 17:09:38	\N
13	MAGASIN	Recette	magasin@recette.local	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	5	9	\N	\N	\N	1	\N	\N	\N	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	\N
14	ACHAT	Recette	achat@recette.local	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	17	9	\N	\N	\N	1	\N	\N	\N	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	\N
15	RAF	Recette	raf@recette.local	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	18	9	\N	\N	\N	1	\N	\N	\N	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	\N
16	DAF	Recette	daf@recette.local	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	19	9	\N	\N	\N	1	\N	\N	\N	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	\N
17	PDG	Recette	pdg@recette.local	$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i	4	9	\N	\N	\N	1	\N	\N	\N	2026-08-18 21:54:29.842238	2026-08-18 21:54:29.842238	\N
\.


--
-- Data for Name: validations_stock_matin; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.validations_stock_matin (id, site_id, date_validation, statut, nb_ecarts, details_ecarts, gsb_user_id, gsb_at, commentaire, bobines_snapshot, created_at) FROM stdin;
\.


--
-- Name: achat_paliers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.achat_paliers_id_seq', 3, true);


--
-- Name: achat_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.achat_types_id_seq', 3, true);


--
-- Name: affectations_equipements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.affectations_equipements_id_seq', 1, true);


--
-- Name: agents_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.agents_id_seq', 1, false);


--
-- Name: articles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.articles_id_seq', 7, true);


--
-- Name: audit_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.audit_log_id_seq', 375, true);


--
-- Name: bilans_mensuels_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.bilans_mensuels_bobines_id_seq', 1, true);


--
-- Name: budget_validations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.budget_validations_id_seq', 1, false);


--
-- Name: commande_lignes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.commande_lignes_id_seq', 12, true);


--
-- Name: commandes_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.commandes_bobines_id_seq', 4, true);


--
-- Name: commandes_bobines_lignes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.commandes_bobines_lignes_id_seq', 1, true);


--
-- Name: commandes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.commandes_id_seq', 7, true);


--
-- Name: comparaisons_stock_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.comparaisons_stock_id_seq', 2, true);


--
-- Name: config_postes_composants_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.config_postes_composants_id_seq', 1, true);


--
-- Name: config_postes_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.config_postes_types_id_seq', 1, true);


--
-- Name: configurations_site_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.configurations_site_id_seq', 21, true);


--
-- Name: consommables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.consommables_id_seq', 7, true);


--
-- Name: consommations_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.consommations_bobines_id_seq', 5, true);


--
-- Name: corrections_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.corrections_bobines_id_seq', 1, true);


--
-- Name: delegations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.delegations_id_seq', 1, true);


--
-- Name: demandes_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.demandes_bobines_id_seq', 1, true);


--
-- Name: demandes_correction_saisie_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.demandes_correction_saisie_id_seq', 1, true);


--
-- Name: departements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.departements_id_seq', 3, true);


--
-- Name: di_demandes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.di_demandes_id_seq', 1, false);


--
-- Name: di_etapes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.di_etapes_id_seq', 23, true);


--
-- Name: di_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.di_types_id_seq', 9, true);


--
-- Name: distribution_lignes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.distribution_lignes_id_seq', 7, true);


--
-- Name: distributions_site_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.distributions_site_id_seq', 3, true);


--
-- Name: ecarts_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ecarts_bobines_id_seq', 19, true);


--
-- Name: emuci_sites_inconnus_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.emuci_sites_inconnus_id_seq', 1, true);


--
-- Name: equipements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.equipements_id_seq', 27, true);


--
-- Name: familles_achat_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.familles_achat_id_seq', 35, true);


--
-- Name: feb_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.feb_id_seq', 1, false);


--
-- Name: feb_lignes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.feb_lignes_id_seq', 1, false);


--
-- Name: feb_offres_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.feb_offres_id_seq', 1, false);


--
-- Name: feb_pieces_jointes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.feb_pieces_jointes_id_seq', 1, false);


--
-- Name: feb_receptions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.feb_receptions_id_seq', 1, false);


--
-- Name: feb_suivi_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.feb_suivi_id_seq', 1, false);


--
-- Name: fournisseurs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.fournisseurs_id_seq', 2, true);


--
-- Name: import_optoplate_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.import_optoplate_id_seq', 3956, true);


--
-- Name: import_optotrace_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.import_optotrace_id_seq', 5660, true);


--
-- Name: interventions_maintenance_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.interventions_maintenance_id_seq', 27, true);


--
-- Name: inventaire_corrections_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.inventaire_corrections_id_seq', 1, false);


--
-- Name: inventaire_details_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.inventaire_details_bobines_id_seq', 48, true);


--
-- Name: inventaire_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.inventaire_sessions_id_seq', 1, false);


--
-- Name: inventaires_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.inventaires_bobines_id_seq', 12, true);


--
-- Name: lignes_budgetaires_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.lignes_budgetaires_id_seq', 6, true);


--
-- Name: litige_messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.litige_messages_id_seq', 3, true);


--
-- Name: livraisons_consommables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.livraisons_consommables_id_seq', 11, true);


--
-- Name: mouvements_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.mouvements_bobines_id_seq', 708, true);


--
-- Name: mouvements_equipements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.mouvements_equipements_id_seq', 27, true);


--
-- Name: mouvements_stock_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.mouvements_stock_id_seq', 1, true);


--
-- Name: nomenclature_liens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.nomenclature_liens_id_seq', 1, true);


--
-- Name: nomenclatures_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.nomenclatures_id_seq', 9, true);


--
-- Name: notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.notifications_id_seq', 83, true);


--
-- Name: op_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.op_bobines_id_seq', 1421, true);


--
-- Name: op_films_utilises_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.op_films_utilises_id_seq', 37, true);


--
-- Name: op_pmma_utilises_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.op_pmma_utilises_id_seq', 1, false);


--
-- Name: op_points_journaliers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.op_points_journaliers_id_seq', 20, true);


--
-- Name: op_stock_rivets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.op_stock_rivets_id_seq', 8, true);


--
-- Name: op_types_bobines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.op_types_bobines_id_seq', 56, true);


--
-- Name: op_types_vehicule_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.op_types_vehicule_id_seq', 4, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissions_id_seq', 808, true);


--
-- Name: points_emuci_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.points_emuci_id_seq', 2, true);


--
-- Name: points_journaliers_info_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.points_journaliers_info_id_seq', 1, true);


--
-- Name: rapports_journaliers_info_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.rapports_journaliers_info_id_seq', 1, true);


--
-- Name: reception_lignes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.reception_lignes_id_seq', 1, true);


--
-- Name: receptions_consommables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.receptions_consommables_id_seq', 11, true);


--
-- Name: receptions_fournisseur_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.receptions_fournisseur_id_seq', 1, true);


--
-- Name: receptions_site_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.receptions_site_id_seq', 8, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 20, true);


--
-- Name: sites_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.sites_id_seq', 22, true);


--
-- Name: stock_consommables_site_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_consommables_site_id_seq', 10, true);


--
-- Name: stock_departement_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_departement_id_seq', 1, false);


--
-- Name: stock_fin_mois_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_fin_mois_id_seq', 1, true);


--
-- Name: stock_pmma_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_pmma_id_seq', 9, true);


--
-- Name: stock_pmma_site_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_pmma_site_id_seq', 8, true);


--
-- Name: stock_site_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_site_id_seq', 16, true);


--
-- Name: support_it_roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.support_it_roles_id_seq', 4, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 17, true);


--
-- Name: validations_stock_matin_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.validations_stock_matin_id_seq', 1, true);


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

\unrestrict K4FtUqLZP901jgd6SvFhoCvC1ZZMgAi8WIUebZ6b2gnWkcaNmJ4hyafOO3iZrHi

