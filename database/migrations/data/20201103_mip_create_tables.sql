--
-- PostgreSQL database dump
--

-- Dumped from database version 10.14
-- Dumped by pg_dump version 10.14

-- Replace <application_database_owner> with database owner
-- Replace <application_database_reader> with database read user

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

--
-- Name: jarjestelma_osio; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.jarjestelma_osio AS ENUM (
    'rakennusinventointi',
    'arkeologia'
);


--
-- Name: jarjestelma_rooli; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.jarjestelma_rooli AS ENUM (
    'katselija',
    'inventoija',
    'tutkija',
    'ulkopuolinen tutkija',
    'pääkäyttäjä'
);


--
-- Name: ykv_kerrosluku; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.ykv_kerrosluku AS ENUM (
    '',
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    '10',
    '11',
    '12',
    '13',
    '14',
    'vaihtelee'
);


--
-- Name: ykv_tyyli; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.ykv_tyyli AS ENUM (
    '',
    'Klassismi < 1827',
    'Empire 1828-1860',
    'Sveitsiläistyyli 1850-1870',
    'Kertaustyylit 1850-1920',
    'Jugend & kansallisromantiikka 1900-1920',
    'Klassisismi 1900-1930',
    'Varhainen modernismi 1928-1938',
    'Jälleenrakennuskausi 1939-1952',
    '(1950-luku) Modernismi 1948-1960',
    'Rationalistinen asuntotuotanto 1958-1970',
    'Konstruktivismi ja rationalismi 1960-1980',
    'Romantiikka ja postmodernismi 1980-1990',
    'Millenismi (modernismi) 1990-2008'
);



--
-- Name: audit_table(regclass); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.audit_table(target_table regclass) RETURNS void
    LANGUAGE sql
    AS $_$ 
SELECT audit_table($1, BOOLEAN 't', BOOLEAN 't'); 
$_$;


--
-- Name: audit_table(regclass, boolean, boolean); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.audit_table(target_table regclass, audit_rows boolean, audit_query_text boolean) RETURNS void
    LANGUAGE sql
    AS $_$ 
SELECT audit_table($1, $2, $3, ARRAY[]::text[]); 
$_$;


--
-- Name: audit_table(regclass, boolean, boolean, text[]); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.audit_table(target_table regclass, audit_rows boolean, audit_query_text boolean, ignored_cols text[]) RETURNS void
    LANGUAGE plpgsql
    AS $$  
DECLARE 
  stm_targets text = 'INSERT OR UPDATE OR DELETE OR TRUNCATE'; 
  _q_txt text; 
  _ignored_cols_snip text = ''; 
BEGIN 
    EXECUTE 'DROP TRIGGER IF EXISTS audit_trigger_row ON ' || quote_ident(target_table::TEXT); 
    EXECUTE 'DROP TRIGGER IF EXISTS audit_trigger_stm ON ' || quote_ident(target_table::TEXT); 
   IF audit_rows THEN 
        IF array_length(ignored_cols,1) > 0 THEN 
            _ignored_cols_snip = ', ' || quote_literal(ignored_cols); 
        END IF; 
        _q_txt = 'CREATE TRIGGER audit_trigger_row AFTER INSERT OR UPDATE OR DELETE ON ' ||  
                 quote_ident(target_table::TEXT) ||  
                 ' FOR EACH ROW EXECUTE PROCEDURE if_modified_func(' || 
                 quote_literal(audit_query_text) || _ignored_cols_snip || ');'; 
       RAISE NOTICE '%',_q_txt; 
        EXECUTE _q_txt; 
        stm_targets = 'TRUNCATE'; 
    ELSE 
    END IF; 
    _q_txt = 'CREATE TRIGGER audit_trigger_stm AFTER ' || stm_targets || ' ON ' || 
             target_table || 
             ' FOR EACH STATEMENT EXECUTE PROCEDURE if_modified_func('|| 
             quote_literal(audit_query_text) || ');'; 
    RAISE NOTICE '%',_q_txt; 
    EXECUTE _q_txt; 
END; 
$$;


--
-- Name: if_modified_func(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.if_modified_func() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'pg_catalog', 'public'
    AS $$ 
DECLARE 
	audit_row logged_actions; 
	include_values boolean; 
   log_diffs boolean; 
	h_old hstore; 
	h_new hstore; 
	excluded_cols text[] = ARRAY[]::text[]; 
BEGIN 
	IF TG_WHEN <> 'AFTER' THEN 
	    RAISE EXCEPTION 'if_modified_func() may only run as an AFTER trigger'; 
	END IF; 
	   audit_row = ROW( 
	       nextval('logged_actions_event_id_seq'), 
	        TG_TABLE_SCHEMA::text, 
	        TG_TABLE_NAME::text, 
	        TG_RELID, 
	        session_user::text, 
			current_setting('application.userid'), 
	        current_timestamp, 
	        statement_timestamp(), 
	        clock_timestamp(), 
	        txid_current(), 
	        current_setting('application_name'), 
	        inet_client_addr(), 
	        inet_client_port(), 
	        current_query(), 
	        substring(TG_OP,1,1), 
	        NULL, NULL, 
	        'f' 
	       ); 
	    IF NOT TG_ARGV[0]::boolean IS DISTINCT FROM 'f'::boolean THEN 
	        audit_row.client_query = NULL; 
	    END IF; 
	    IF TG_ARGV[1] IS NOT NULL THEN 
	        excluded_cols = TG_ARGV[1]::text[]; 
	    END IF; 
	    IF (TG_OP = 'UPDATE' AND TG_LEVEL = 'ROW') THEN 
	        audit_row.row_data = hstore(OLD.*) - excluded_cols; 
	        audit_row.changed_fields =  (hstore(NEW.*) - audit_row.row_data) - excluded_cols; 
	        IF audit_row.changed_fields = hstore('') THEN 
	            -- All changed fields are ignored. Skip this update. 
	            RETURN NULL; 
	        END IF; 
	    ELSIF (TG_OP = 'DELETE' AND TG_LEVEL = 'ROW') THEN 
	        audit_row.row_data = hstore(OLD.*) - excluded_cols; 
	    ELSIF (TG_OP = 'INSERT' AND TG_LEVEL = 'ROW') THEN 
	        audit_row.row_data = hstore(NEW.*) - excluded_cols; 
	    ELSIF (TG_LEVEL = 'STATEMENT' AND TG_OP IN ('INSERT','UPDATE','DELETE','TRUNCATE')) THEN 
	        audit_row.statement_only = 't'; 
	    ELSE 
	        RAISE EXCEPTION '[if_modified_func] - Trigger func added as trigger for unhandled case: %, %',TG_OP, TG_LEVEL; 
	        RETURN NULL; 
	    END IF; 
	    INSERT INTO logged_actions VALUES (audit_row.*); 
	    RETURN NULL; 
END; 
$$;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: MAPINFO_MAPCATALOG; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public."MAPINFO_MAPCATALOG" (
    "SPATIALTYPE" real NOT NULL,
    "TABLENAME" character(32) NOT NULL,
    "OWNERNAME" character(32) NOT NULL,
    "SPATIALCOLUMN" character(32) NOT NULL,
    "DB_X_LL" real NOT NULL,
    "DB_Y_LL" real NOT NULL,
    "DB_X_UR" real NOT NULL,
    "DB_Y_UR" real NOT NULL,
    "COORDINATESYSTEM" character(254) NOT NULL,
    "SYMBOL" character(254) NOT NULL,
    "XCOLUMNNAME" character(32) NOT NULL,
    "YCOLUMNNAME" character(32) NOT NULL
);


--
-- Name: ajoitus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ajoitus (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ajoitus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ajoitus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ajoitus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ajoitus_id_seq OWNED BY public.ajoitus.id;


--
-- Name: ajoitustarkenne; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ajoitustarkenne (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    ajoitus_id integer NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ajoitustarkenne_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ajoitustarkenne_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ajoitustarkenne_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ajoitustarkenne_id_seq OWNED BY public.ajoitustarkenne.id;


--
-- Name: alkuperaisyys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.alkuperaisyys (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: alkuperaisyys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.alkuperaisyys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: alkuperaisyys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.alkuperaisyys_id_seq OWNED BY public.alkuperaisyys.id;


--
-- Name: alue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.alue (
    id integer NOT NULL,
    nimi character varying,
    maisema text,
    historia text,
    nykytila text,
    lisatiedot text,
    lahteet text,
    keskipiste public.geometry(Geometry,3067),
    aluerajaus public.geometry(Geometry,3067),
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    paikkakunta text,
    arkeologinen_kohde boolean DEFAULT false
);


--
-- Name: alue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.alue_id_seq
    START WITH 3113
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: alue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.alue_id_seq OWNED BY public.alue.id;


--
-- Name: alue_kyla; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.alue_kyla (
    alue_id integer NOT NULL,
    kyla_id integer NOT NULL
);


--
-- Name: aluetyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aluetyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: aluetyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.aluetyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: aluetyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.aluetyyppi_id_seq OWNED BY public.aluetyyppi.id;


--
-- Name: ark_alakohde_ajoitus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_alakohde_ajoitus (
    id bigint NOT NULL,
    ark_kohde_alakohde_id integer NOT NULL,
    ajoitus_id integer NOT NULL,
    ajoitustarkenne_id integer,
    ajoituskriteeri text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_alakohde_ajoitus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_alakohde_ajoitus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_alakohde_ajoitus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_alakohde_ajoitus_id_seq OWNED BY public.ark_alakohde_ajoitus.id;


--
-- Name: ark_alakohde_sijainti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_alakohde_sijainti (
    id bigint NOT NULL,
    ark_kohde_alakohde_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    sijainti public.geometry(Geometry,3067)
);


--
-- Name: ark_alakohde_sijainti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_alakohde_sijainti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_alakohde_sijainti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_alakohde_sijainti_id_seq OWNED BY public.ark_alakohde_sijainti.id;


--
-- Name: ark_kartta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kartta (
    id bigint NOT NULL,
    piirtaja text,
    organisaatio text,
    karttanumero integer,
    kuvaus text,
    tekijanoikeuslauseke text,
    mittaukset_kentalla text,
    lisatiedot text,
    koko integer,
    tyyppi integer,
    mittakaava integer,
    ark_tutkimus_id integer NOT NULL,
    julkinen boolean NOT NULL,
    polku text NOT NULL,
    tiedostonimi text NOT NULL,
    alkup_tiedostonimi text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    migraatiodata text,
    alkup_karttanumero text
);


--
-- Name: ark_kartta_asiasana; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kartta_asiasana (
    id bigint NOT NULL,
    asiasana text NOT NULL,
    kieli text NOT NULL,
    ark_kartta_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kartta_asiasana_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kartta_asiasana_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kartta_asiasana_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kartta_asiasana_id_seq OWNED BY public.ark_kartta_asiasana.id;


--
-- Name: ark_kartta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kartta_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kartta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kartta_id_seq OWNED BY public.ark_kartta.id;


--
-- Name: ark_kartta_loyto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kartta_loyto (
    ark_kartta_id integer NOT NULL,
    ark_loyto_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kartta_nayte; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kartta_nayte (
    ark_kartta_id integer NOT NULL,
    ark_nayte_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kartta_yksikko; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kartta_yksikko (
    ark_kartta_id integer NOT NULL,
    ark_yksikko_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_karttakoko; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_karttakoko (
    id bigint NOT NULL,
    koko text NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_karttakoko_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_karttakoko_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_karttakoko_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_karttakoko_id_seq OWNED BY public.ark_karttakoko.id;


--
-- Name: ark_karttatyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_karttatyyppi (
    id bigint NOT NULL,
    tyyppi text NOT NULL,
    numero integer NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_karttatyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_karttatyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_karttatyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_karttatyyppi_id_seq OWNED BY public.ark_karttatyyppi.id;


--
-- Name: ark_kohde; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde (
    id bigint NOT NULL,
    muinaisjaannostunnus integer,
    nimi text,
    muutnimet text,
    maakuntanimi text,
    tyhja boolean DEFAULT false NOT NULL,
    tuhoutumissyy_id integer,
    tuhoutumiskuvaus text,
    virallinenkatselmus boolean DEFAULT false NOT NULL,
    mahdollisetseuraamukset text,
    tarkenne text,
    jarjestysnumero integer,
    ark_kohdelaji_id integer,
    vedenalainen boolean DEFAULT false NOT NULL,
    suojelukriteeri text,
    rauhoitusluokka_id integer,
    lukumaara integer,
    haaksirikkovuosi integer,
    alkuperamaa text,
    alkuperamaanperustelu text,
    koordselite text,
    etaisyystieto text,
    korkeus_min double precision,
    korkeus_max double precision,
    syvyys_min double precision,
    syvyys_max double precision,
    peruskarttanumero text,
    peruskarttanimi text,
    koordinaattijarjestelma text,
    sijainti_ei_tiedossa boolean DEFAULT false NOT NULL,
    alkuperaisyys_id integer,
    rajaustarkkuus_id integer,
    maastomerkinta_id integer,
    kunto_id integer,
    hoitotarve_id integer,
    huomautus text,
    lahteet text,
    avattu timestamp(0) without time zone,
    avaaja text,
    muutettu timestamp(0) without time zone,
    muuttaja text,
    yllapitoorganisaatiotunnus integer,
    yllapitoorganisaatio text,
    julkinenurl text,
    viranomaisurl text,
    kuvaus text,
    taustatiedot text,
    havainnot text,
    tulkinta text,
    lisatiedot text,
    rajattu boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    kyppi_status text,
    vaatii_tarkastusta boolean DEFAULT false NOT NULL,
    tarkastus_muistiinpano text
);


--
-- Name: ark_kohde_ajoitus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_ajoitus (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    ajoitus_id integer NOT NULL,
    ajoitustarkenne_id integer,
    ajoituskriteeri text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_ajoitus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_ajoitus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_ajoitus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_ajoitus_id_seq OWNED BY public.ark_kohde_ajoitus.id;


--
-- Name: ark_kohde_alakohde; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_alakohde (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    nimi text,
    kuvaus text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    ark_kohdetyyppi_id integer,
    ark_kohdetyyppitarkenne_id integer,
    koordselite text,
    korkeus_min double precision,
    korkeus_max double precision
);


--
-- Name: ark_kohde_alakohde_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_alakohde_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_alakohde_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_alakohde_id_seq OWNED BY public.ark_kohde_alakohde.id;


--
-- Name: ark_kohde_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_id_seq OWNED BY public.ark_kohde.id;


--
-- Name: ark_kohde_kiinteistorakennus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_kiinteistorakennus (
    id bigint NOT NULL,
    ark_kohde_id bigint NOT NULL,
    kiinteistotunnus text,
    kiinteisto_nimi text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_kiinteistorakennus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_kiinteistorakennus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_kiinteistorakennus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_kiinteistorakennus_id_seq OWNED BY public.ark_kohde_kiinteistorakennus.id;


--
-- Name: ark_kohde_kuntakyla; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_kuntakyla (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    kyla_id integer,
    kunta_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_kuntakyla_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_kuntakyla_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_kuntakyla_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_kuntakyla_id_seq OWNED BY public.ark_kohde_kuntakyla.id;


--
-- Name: ark_kohde_mjrtutkimus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_mjrtutkimus (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    ark_tutkimus_id integer,
    tutkija text,
    vuosi integer,
    ark_tutkimuslaji_id integer,
    huomio text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_mjrtutkimus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_mjrtutkimus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_mjrtutkimus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_mjrtutkimus_id_seq OWNED BY public.ark_kohde_mjrtutkimus.id;


--
-- Name: ark_kohde_nightly; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_nightly (
    seuraava_hakupvm date NOT NULL
);


--
-- Name: ark_kohde_osoite; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_osoite (
    id bigint NOT NULL,
    ark_kohde_kiinteistorakennus_id bigint NOT NULL,
    rakennustunnus text,
    katunimi text,
    katunumero text,
    postinumero text,
    kuntanimi text,
    kieli text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_osoite_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_osoite_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_osoite_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_osoite_id_seq OWNED BY public.ark_kohde_osoite.id;


--
-- Name: ark_kohde_projekti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_projekti (
    id bigint NOT NULL,
    projekti_id bigint NOT NULL,
    ark_kohde_id bigint NOT NULL,
    merkinta text,
    selite text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_projekti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_projekti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_projekti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_projekti_id_seq OWNED BY public.ark_kohde_projekti.id;


--
-- Name: ark_kohde_rekisterilinkki; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_rekisterilinkki (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    rekisteri text NOT NULL,
    kohdenimi text NOT NULL,
    rekisterilinkkiurl text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_rekisterilinkki_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_rekisterilinkki_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_rekisterilinkki_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_rekisterilinkki_id_seq OWNED BY public.ark_kohde_rekisterilinkki.id;


--
-- Name: ark_kohde_sijainti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_sijainti (
    id bigint NOT NULL,
    kohde_id bigint NOT NULL,
    tuhoutunut boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    sijainti public.geometry(Geometry,3067)
);


--
-- Name: ark_kohde_sijainti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_sijainti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_sijainti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_sijainti_id_seq OWNED BY public.ark_kohde_sijainti.id;


--
-- Name: ark_kohde_suojelutiedot; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_suojelutiedot (
    id bigint NOT NULL,
    suojelutyyppi_id bigint NOT NULL,
    ark_kohde_id bigint NOT NULL,
    merkinta text,
    selite text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_suojelutiedot_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_suojelutiedot_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_suojelutiedot_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_suojelutiedot_id_seq OWNED BY public.ark_kohde_suojelutiedot.id;


--
-- Name: ark_kohde_tutkimus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_tutkimus (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    ark_tutkimus_id integer NOT NULL
);


--
-- Name: ark_kohde_tutkimus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_tutkimus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_tutkimus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_tutkimus_id_seq OWNED BY public.ark_kohde_tutkimus.id;


--
-- Name: ark_kohde_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_tyyppi (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    tyyppi_id integer NOT NULL,
    tyyppitarkenne_id integer,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_tyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_tyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_tyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_tyyppi_id_seq OWNED BY public.ark_kohde_tyyppi.id;


--
-- Name: ark_kohde_vanhakunta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohde_vanhakunta (
    id bigint NOT NULL,
    ark_kohde_id integer NOT NULL,
    kuntanimi text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_kohde_vanhakunta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohde_vanhakunta_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohde_vanhakunta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohde_vanhakunta_id_seq OWNED BY public.ark_kohde_vanhakunta.id;


--
-- Name: ark_kohdelaji; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohdelaji (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kohdelaji_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohdelaji_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohdelaji_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohdelaji_id_seq OWNED BY public.ark_kohdelaji.id;


--
-- Name: ark_kohdetyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohdetyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kohdetyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohdetyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohdetyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohdetyyppi_id_seq OWNED BY public.ark_kohdetyyppi.id;


--
-- Name: ark_kohdetyyppitarkenne; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kohdetyyppitarkenne (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    ark_kohdetyyppi_id integer NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kohdetyyppitarkenne_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kohdetyyppitarkenne_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kohdetyyppitarkenne_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kohdetyyppitarkenne_id_seq OWNED BY public.ark_kohdetyyppitarkenne.id;


--
-- Name: ark_kokoelmalaji; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kokoelmalaji (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kokoelmalaji_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kokoelmalaji_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kokoelmalaji_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kokoelmalaji_id_seq OWNED BY public.ark_kokoelmalaji.id;


--
-- Name: ark_kons_kasittely; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_kasittely (
    id bigint NOT NULL,
    kasittelytunnus text,
    alkaa date,
    paattyy date,
    kuvaus text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kons_kasittely_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_kasittely_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_kasittely_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_kasittely_id_seq OWNED BY public.ark_kons_kasittely.id;


--
-- Name: ark_kons_kasittelytapahtumat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_kasittelytapahtumat (
    id bigint NOT NULL,
    ark_kons_kasittely_id bigint,
    paivamaara date,
    kasittelytoimenpide text,
    huomiot text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kons_kasittelytapahtumat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_kasittelytapahtumat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_kasittelytapahtumat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_kasittelytapahtumat_id_seq OWNED BY public.ark_kons_kasittelytapahtumat.id;


--
-- Name: ark_kons_loyto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_loyto (
    id bigint NOT NULL,
    ark_kons_toimenpiteet_id bigint NOT NULL,
    ark_loyto_id bigint NOT NULL,
    ark_kons_kasittely_id bigint,
    paattyy date,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL
);


--
-- Name: ark_kons_loyto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_loyto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_loyto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_loyto_id_seq OWNED BY public.ark_kons_loyto.id;


--
-- Name: ark_kons_materiaali; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_materiaali (
    id bigint NOT NULL,
    nimi text,
    muut_nimet text,
    kemiallinen_kaava text,
    lisatiedot text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kons_materiaali_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_materiaali_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_materiaali_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_materiaali_id_seq OWNED BY public.ark_kons_materiaali.id;


--
-- Name: ark_kons_menetelma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_menetelma (
    id bigint NOT NULL,
    nimi text,
    kuvaus text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kons_menetelma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_menetelma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_menetelma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_menetelma_id_seq OWNED BY public.ark_kons_menetelma.id;


--
-- Name: ark_kons_nayte; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_nayte (
    id bigint NOT NULL,
    ark_kons_toimenpiteet_id bigint NOT NULL,
    ark_nayte_id bigint NOT NULL,
    ark_kons_kasittely_id bigint,
    paattyy date,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL
);


--
-- Name: ark_kons_nayte_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_nayte_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_nayte_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_nayte_id_seq OWNED BY public.ark_kons_nayte.id;


--
-- Name: ark_kons_toimenpide; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_toimenpide (
    id bigint NOT NULL,
    nimi text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kons_toimenpide_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_toimenpide_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_toimenpide_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_toimenpide_id_seq OWNED BY public.ark_kons_toimenpide.id;


--
-- Name: ark_kons_toimenpide_materiaalit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_toimenpide_materiaalit (
    id bigint NOT NULL,
    ark_kons_toimenpiteet_id bigint,
    ark_kons_materiaali_id bigint,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL
);


--
-- Name: ark_kons_toimenpide_materiaalit_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_toimenpide_materiaalit_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_toimenpide_materiaalit_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_toimenpide_materiaalit_id_seq OWNED BY public.ark_kons_toimenpide_materiaalit.id;


--
-- Name: ark_kons_toimenpide_menetelma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_toimenpide_menetelma (
    id bigint NOT NULL,
    toimenpide_id integer NOT NULL,
    menetelma_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL
);


--
-- Name: ark_kons_toimenpide_menetelma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_toimenpide_menetelma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_toimenpide_menetelma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_toimenpide_menetelma_id_seq OWNED BY public.ark_kons_toimenpide_menetelma.id;


--
-- Name: ark_kons_toimenpiteet; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kons_toimenpiteet (
    id bigint NOT NULL,
    ark_kons_toimenpide_id bigint NOT NULL,
    ark_kons_menetelma_id bigint,
    ark_kons_kasittely_id bigint,
    tekija integer,
    alkaa date,
    lisatiedot text,
    menetelman_kuvaus text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_kons_toimenpiteet_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kons_toimenpiteet_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kons_toimenpiteet_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kons_toimenpiteet_id_seq OWNED BY public.ark_kons_toimenpiteet.id;


--
-- Name: ark_konservointivaihe; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_konservointivaihe (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_konservointivaihe_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_konservointivaihe_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_konservointivaihe_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_konservointivaihe_id_seq OWNED BY public.ark_konservointivaihe.id;


--
-- Name: ark_kuva; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva (
    id bigint NOT NULL,
    kuvaaja text,
    organisaatio text,
    luettelointinumero text,
    kuvaus text,
    tekijanoikeuslauseke text,
    julkinen boolean NOT NULL,
    polku text NOT NULL,
    tiedostonimi text NOT NULL,
    alkup_tiedostonimi text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    ark_tutkimus_id integer,
    kuvauspvm timestamp(0) without time zone,
    kuvaussuunta text,
    otsikko text,
    konservointivaihe_id bigint,
    tunnistekuva boolean DEFAULT false,
    lisatiedot text,
    migraatiodata text
);


--
-- Name: ark_kuva_asiasana; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva_asiasana (
    id bigint NOT NULL,
    asiasana text NOT NULL,
    kieli text NOT NULL,
    ark_kuva_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kuva_asiasana_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kuva_asiasana_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kuva_asiasana_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kuva_asiasana_id_seq OWNED BY public.ark_kuva_asiasana.id;


--
-- Name: ark_kuva_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_kuva_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_kuva_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_kuva_id_seq OWNED BY public.ark_kuva.id;


--
-- Name: ark_kuva_kohde; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva_kohde (
    ark_kuva_id integer NOT NULL,
    ark_kohde_id integer NOT NULL,
    jarjestys integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kuva_loyto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva_loyto (
    ark_kuva_id integer NOT NULL,
    ark_loyto_id integer NOT NULL,
    jarjestys integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kuva_nayte; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva_nayte (
    ark_kuva_id integer NOT NULL,
    ark_nayte_id integer NOT NULL,
    jarjestys integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kuva_tutkimus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva_tutkimus (
    ark_kuva_id integer NOT NULL,
    ark_tutkimus_id integer NOT NULL,
    jarjestys integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kuva_tutkimusalue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva_tutkimusalue (
    ark_kuva_id integer NOT NULL,
    ark_tutkimusalue_id integer NOT NULL,
    jarjestys integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_kuva_yksikko; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_kuva_yksikko (
    ark_kuva_id integer NOT NULL,
    ark_yksikko_id integer NOT NULL,
    jarjestys integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_loyto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto (
    id bigint NOT NULL,
    ark_tutkimusalue_yksikko_id bigint,
    ark_tutkimusalue_kerros_id bigint,
    ark_loyto_materiaalikoodi_id bigint,
    ark_loyto_tyyppi_id bigint,
    luettelointinumero text,
    alanumero integer,
    loytopaikan_tarkenne text,
    koordinaatti_z numeric(8,2),
    kuvaus text,
    kappalemaara integer,
    paino numeric(8,2),
    painoyksikko text,
    pituus numeric(8,2),
    pituusyksikko text,
    leveys numeric(8,2),
    leveysyksikko text,
    korkeus numeric(8,2),
    korkeusyksikko text,
    halkaisija numeric(8,2),
    halkaisijayksikko text,
    paksuus numeric(8,2),
    paksuusyksikko text,
    muut_mitat text,
    tulkinta text,
    alkuvuosi integer,
    alkuvuosi_ajanlasku text,
    paatosvuosi integer,
    paatosvuosi_ajanlasku text,
    ajoitus_kuvaus text,
    ajoituksen_perusteet text,
    tutkimukset_lahteet text,
    lisatiedot text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    ark_loyto_ensisijainen_materiaali_id bigint,
    koordinaatti_n numeric(10,3),
    koordinaatti_e numeric(9,3),
    loydon_tila_id integer DEFAULT 1 NOT NULL,
    konservointi integer,
    loytopaivamaara date,
    kappalemaara_arvio boolean DEFAULT false NOT NULL,
    ark_tutkimusalue_id bigint,
    vakituinen_sailytystila_id integer,
    vakituinen_hyllypaikka text,
    tilapainen_sijainti text,
    paino_ennen numeric(8,2),
    paino_ennen_yksikko text,
    paino_jalkeen numeric(8,2),
    paino_jalkeen_yksikko text,
    kunto text,
    kunto_paivamaara date,
    tekija integer,
    sailytysolosuhteet text,
    konservointi_lisatiedot text,
    migraatiodata text,
    kenttanumero_vanha_tyonumero text
);


--
-- Name: ark_loyto_asiasanat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_asiasanat (
    id bigint NOT NULL,
    ark_loyto_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    asiasana text,
    kieli text
);


--
-- Name: ark_loyto_asiasanat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_asiasanat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_asiasanat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_asiasanat_id_seq OWNED BY public.ark_loyto_asiasanat.id;


--
-- Name: ark_loyto_ensisijaiset_materiaalit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_ensisijaiset_materiaalit (
    id bigint NOT NULL,
    ark_loyto_materiaalikoodi_id integer NOT NULL,
    ark_loyto_materiaali_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_ensisijaiset_materiaalit_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_ensisijaiset_materiaalit_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_ensisijaiset_materiaalit_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_ensisijaiset_materiaalit_id_seq OWNED BY public.ark_loyto_ensisijaiset_materiaalit.id;


--
-- Name: ark_loyto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_id_seq OWNED BY public.ark_loyto.id;


--
-- Name: ark_loyto_luettelonrohistoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_luettelonrohistoria (
    id bigint NOT NULL,
    ark_loyto_id integer NOT NULL,
    luettelointinumero_vanha text NOT NULL,
    luettelointinumero_uusi text NOT NULL,
    ark_loyto_tapahtumat_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_luettelonrohistoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_luettelonrohistoria_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_luettelonrohistoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_luettelonrohistoria_id_seq OWNED BY public.ark_loyto_luettelonrohistoria.id;


--
-- Name: ark_loyto_materiaali; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_materiaali (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_materiaali_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_materiaali_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_materiaali_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_materiaali_id_seq OWNED BY public.ark_loyto_materiaali.id;


--
-- Name: ark_loyto_materiaalikoodi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_materiaalikoodi (
    id bigint NOT NULL,
    koodi text,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_materiaalikoodi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_materiaalikoodi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_materiaalikoodi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_materiaalikoodi_id_seq OWNED BY public.ark_loyto_materiaalikoodi.id;


--
-- Name: ark_loyto_materiaalit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_materiaalit (
    id bigint NOT NULL,
    ark_loyto_id integer NOT NULL,
    ark_loyto_materiaali_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_materiaalit_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_materiaalit_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_materiaalit_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_materiaalit_id_seq OWNED BY public.ark_loyto_materiaalit.id;


--
-- Name: ark_loyto_merkinnat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_merkinnat (
    id bigint NOT NULL,
    ark_loyto_id integer NOT NULL,
    ark_loyto_merkinta_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_merkinnat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_merkinnat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_merkinnat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_merkinnat_id_seq OWNED BY public.ark_loyto_merkinnat.id;


--
-- Name: ark_loyto_merkinta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_merkinta (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_merkinta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_merkinta_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_merkinta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_merkinta_id_seq OWNED BY public.ark_loyto_merkinta.id;


--
-- Name: ark_loyto_tapahtuma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_tapahtuma (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_tapahtuma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_tapahtuma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_tapahtuma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_tapahtuma_id_seq OWNED BY public.ark_loyto_tapahtuma.id;


--
-- Name: ark_loyto_tapahtumat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_tapahtumat (
    id bigint NOT NULL,
    ark_loyto_id bigint NOT NULL,
    ark_loyto_tapahtuma_id bigint NOT NULL,
    kuvaus text,
    tapahtumapaivamaara date,
    luotu timestamp(6) without time zone NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    lainaaja text,
    tilapainen_sijainti text,
    loppupvm timestamp(0) without time zone,
    vakituinen_sailytystila_id integer,
    vakituinen_hyllypaikka text
);


--
-- Name: ark_loyto_tapahtumat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_tapahtumat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_tapahtumat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_tapahtumat_id_seq OWNED BY public.ark_loyto_tapahtumat.id;


--
-- Name: ark_loyto_tila; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_tila (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_tila_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_tila_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_tila_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_tila_id_seq OWNED BY public.ark_loyto_tila.id;


--
-- Name: ark_loyto_tila_tapahtuma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_tila_tapahtuma (
    id bigint NOT NULL,
    ark_loyto_tila_id integer NOT NULL,
    ark_loyto_tapahtuma_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL
);


--
-- Name: ark_loyto_tila_tapahtuma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_tila_tapahtuma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_tila_tapahtuma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_tila_tapahtuma_id_seq OWNED BY public.ark_loyto_tila_tapahtuma.id;


--
-- Name: ark_loyto_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_tyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_tyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_tyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_tyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_tyyppi_id_seq OWNED BY public.ark_loyto_tyyppi.id;


--
-- Name: ark_loyto_tyyppi_tarkenne; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_tyyppi_tarkenne (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_tyyppi_tarkenne_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_tyyppi_tarkenne_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_tyyppi_tarkenne_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_tyyppi_tarkenne_id_seq OWNED BY public.ark_loyto_tyyppi_tarkenne.id;


--
-- Name: ark_loyto_tyyppi_tarkenteet; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_loyto_tyyppi_tarkenteet (
    id bigint NOT NULL,
    ark_loyto_id integer NOT NULL,
    ark_loyto_tyyppi_tarkenne_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_loyto_tyyppi_tarkenteet_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_loyto_tyyppi_tarkenteet_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_loyto_tyyppi_tarkenteet_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_loyto_tyyppi_tarkenteet_id_seq OWNED BY public.ark_loyto_tyyppi_tarkenteet.id;


--
-- Name: ark_mittakaava; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_mittakaava (
    id bigint NOT NULL,
    mittakaava text NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_mittakaava_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_mittakaava_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_mittakaava_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_mittakaava_id_seq OWNED BY public.ark_mittakaava.id;


--
-- Name: ark_nayte; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_nayte (
    id bigint NOT NULL,
    ark_tutkimusalue_yksikko_id bigint,
    ark_tutkimusalue_kerros_id bigint,
    ark_naytekoodi_id bigint,
    ark_naytetyyppi_id bigint,
    ark_talteenottotapa_id bigint,
    ark_nayte_tila_id bigint,
    luettelointinumero text,
    alanumero integer,
    kuvaus text,
    koordinaatti_n numeric(10,3),
    koordinaatti_e numeric(9,3),
    koordinaatti_z numeric(8,2),
    koordinaatti_n_min numeric(10,3),
    koordinaatti_n_max numeric(10,3),
    koordinaatti_e_min numeric(9,3),
    koordinaatti_e_max numeric(9,3),
    koordinaatti_z_min numeric(8,2),
    koordinaatti_z_max numeric(8,2),
    laboratorion_arvio text,
    luokka integer,
    luunayte_maara numeric(8,2),
    luunayte_maara_yksikko text,
    maanayte_maara numeric(8,2),
    rf_naytteen_koko text,
    lisatiedot text,
    naytetta_jaljella boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    ark_tutkimusalue_id bigint,
    vakituinen_sailytystila_id integer,
    vakituinen_hyllypaikka text,
    tilapainen_sijainti text,
    paino_ennen numeric(8,2),
    paino_ennen_yksikko text,
    paino_jalkeen numeric(8,2),
    paino_jalkeen_yksikko text,
    kunto text,
    kunto_paivamaara date,
    tekija integer,
    sailytysolosuhteet text,
    konservointi_lisatiedot text,
    migraatiodata text,
    alkup_luetnro text
);


--
-- Name: ark_nayte_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_nayte_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_nayte_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_nayte_id_seq OWNED BY public.ark_nayte.id;


--
-- Name: ark_nayte_talteenottotapa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_nayte_talteenottotapa (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_nayte_talteenottotapa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_nayte_talteenottotapa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_nayte_talteenottotapa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_nayte_talteenottotapa_id_seq OWNED BY public.ark_nayte_talteenottotapa.id;


--
-- Name: ark_nayte_tapahtuma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_nayte_tapahtuma (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_nayte_tapahtuma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_nayte_tapahtuma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_nayte_tapahtuma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_nayte_tapahtuma_id_seq OWNED BY public.ark_nayte_tapahtuma.id;


--
-- Name: ark_nayte_tapahtumat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_nayte_tapahtumat (
    id bigint NOT NULL,
    ark_nayte_id bigint NOT NULL,
    ark_nayte_tapahtuma_id bigint NOT NULL,
    kuvaus text,
    tapahtumapaivamaara date,
    luotu timestamp(0) without time zone NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    lainaaja text,
    tilapainen_sijainti text,
    loppupvm timestamp(0) without time zone,
    vakituinen_sailytystila_id integer,
    vakituinen_hyllypaikka text
);


--
-- Name: ark_nayte_tapahtumat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_nayte_tapahtumat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_nayte_tapahtumat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_nayte_tapahtumat_id_seq OWNED BY public.ark_nayte_tapahtumat.id;


--
-- Name: ark_nayte_tila; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_nayte_tila (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_nayte_tila_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_nayte_tila_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_nayte_tila_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_nayte_tila_id_seq OWNED BY public.ark_nayte_tila.id;


--
-- Name: ark_nayte_tila_tapahtuma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_nayte_tila_tapahtuma (
    id bigint NOT NULL,
    ark_nayte_tila_id integer NOT NULL,
    ark_nayte_tapahtuma_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL
);


--
-- Name: ark_nayte_tila_tapahtuma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_nayte_tila_tapahtuma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_nayte_tila_tapahtuma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_nayte_tila_tapahtuma_id_seq OWNED BY public.ark_nayte_tila_tapahtuma.id;


--
-- Name: ark_naytekoodi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_naytekoodi (
    id bigint NOT NULL,
    koodi text,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_naytekoodi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_naytekoodi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_naytekoodi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_naytekoodi_id_seq OWNED BY public.ark_naytekoodi.id;


--
-- Name: ark_naytetyypit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_naytetyypit (
    id bigint NOT NULL,
    ark_naytekoodi_id integer NOT NULL,
    ark_naytetyyppi_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_naytetyypit_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_naytetyypit_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_naytetyypit_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_naytetyypit_id_seq OWNED BY public.ark_naytetyypit.id;


--
-- Name: ark_naytetyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_naytetyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_naytetyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_naytetyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_naytetyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_naytetyyppi_id_seq OWNED BY public.ark_naytetyyppi.id;


--
-- Name: ark_rontgenkuva; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_rontgenkuva (
    id bigint NOT NULL,
    numero text NOT NULL,
    pvm timestamp(0) without time zone,
    kuvaaja text,
    lisatiedot text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_rontgenkuva_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_rontgenkuva_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_rontgenkuva_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_rontgenkuva_id_seq OWNED BY public.ark_rontgenkuva.id;


--
-- Name: ark_rontgenkuva_loyto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_rontgenkuva_loyto (
    ark_rontgenkuva_id integer NOT NULL,
    ark_loyto_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_rontgenkuva_nayte; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_rontgenkuva_nayte (
    ark_rontgenkuva_id integer NOT NULL,
    ark_nayte_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_sailytystila; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_sailytystila (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_sailytystila_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_sailytystila_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_sailytystila_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_sailytystila_id_seq OWNED BY public.ark_sailytystila.id;


--
-- Name: ark_tarkastus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tarkastus (
    id bigint NOT NULL,
    ark_tutkimus_id bigint NOT NULL,
    tarkastaja integer NOT NULL,
    aiemmat_tiedot text,
    aiemmat_loydot text,
    tarkastusloydot text,
    liitteet text,
    tarkastusolosuhteet text,
    muuta text,
    tarkastuksen_syy text,
    ymparisto_maasto text,
    kohteen_kuvaus text,
    muut_tiedot text,
    kohteen_kunto text,
    hoitotarve text,
    suoja_alueeksi text,
    maankayttohankkeet text,
    sailymisen_asiat text,
    kohteen_tiedon_maara text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_tarkastus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tarkastus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tarkastus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tarkastus_id_seq OWNED BY public.ark_tarkastus.id;


--
-- Name: ark_tiedosto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto (
    id bigint NOT NULL,
    otsikko text,
    kuvaus text,
    polku text NOT NULL,
    tiedostonimi text NOT NULL,
    alkup_tiedostonimi text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_tiedosto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tiedosto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tiedosto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tiedosto_id_seq OWNED BY public.ark_tiedosto.id;


--
-- Name: ark_tiedosto_kohde; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_kohde (
    ark_tiedosto_id integer NOT NULL,
    ark_kohde_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tiedosto_kons_kasittely; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_kons_kasittely (
    ark_tiedosto_id integer NOT NULL,
    ark_kons_kasittely_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tiedosto_kons_toimenpiteet; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_kons_toimenpiteet (
    ark_tiedosto_id integer NOT NULL,
    ark_kons_toimenpiteet_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tiedosto_loyto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_loyto (
    ark_tiedosto_id integer NOT NULL,
    ark_loyto_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tiedosto_nayte; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_nayte (
    ark_tiedosto_id integer NOT NULL,
    ark_nayte_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tiedosto_rontgenkuva; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_rontgenkuva (
    ark_tiedosto_id integer NOT NULL,
    ark_rontgenkuva_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tiedosto_tutkimus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_tutkimus (
    ark_tiedosto_id integer NOT NULL,
    ark_tutkimus_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tiedosto_yksikko; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tiedosto_yksikko (
    ark_tiedosto_id integer NOT NULL,
    ark_yksikko_id integer NOT NULL,
    luoja integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    muokkaaja integer,
    muokattu timestamp(0) without time zone,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: ark_tuhoutumissyy; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tuhoutumissyy (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_tuhoutumissyy_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tuhoutumissyy_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tuhoutumissyy_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tuhoutumissyy_id_seq OWNED BY public.ark_tuhoutumissyy.id;


--
-- Name: ark_tutkimus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimus (
    id bigint NOT NULL,
    ark_tutkimuslaji_id bigint NOT NULL,
    nimi text NOT NULL,
    rahoittaja text,
    alkupvm date,
    loppupvm date,
    kenttatyo_alkupvm date,
    kenttatyo_loppupvm date,
    loyto_paanumero text,
    digikuva_paanumero text,
    mustavalko_paanumero text,
    dia_paanumero text,
    valmis boolean DEFAULT false NOT NULL,
    julkinen boolean DEFAULT false NOT NULL,
    kl_koodi integer,
    tiivistelma text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    ark_loyto_kokoelmalaji_id bigint,
    ark_raportti_kokoelmalaji_id bigint,
    ark_kartta_kokoelmalaji_id bigint,
    ark_valokuva_kokoelmalaji_id bigint,
    ark_nayte_kokoelmalaji_id bigint,
    tutkimuksen_lyhenne text,
    nayte_paanumero text,
    lisatiedot text,
    kenttatyojohtaja text
);


--
-- Name: ark_tutkimuslaji; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimuslaji (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    mjr_nimi_fi text,
    mjr_nimi_se text,
    mjr_nimi_en text
);


--
-- Name: ark_tutkimus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimus_id_seq OWNED BY public.ark_tutkimuslaji.id;


--
-- Name: ark_tutkimus_id_seq1; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimus_id_seq1
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimus_id_seq1; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimus_id_seq1 OWNED BY public.ark_tutkimus.id;


--
-- Name: ark_tutkimus_kayttaja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimus_kayttaja (
    id bigint NOT NULL,
    ark_tutkimus_id bigint NOT NULL,
    kayttaja_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_tutkimus_kayttaja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimus_kayttaja_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimus_kayttaja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimus_kayttaja_id_seq OWNED BY public.ark_tutkimus_kayttaja.id;


--
-- Name: ark_tutkimus_kiinteistorakennus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimus_kiinteistorakennus (
    id bigint NOT NULL,
    ark_tutkimus_id bigint NOT NULL,
    kiinteistotunnus text,
    kiinteisto_nimi text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_tutkimus_kiinteistorakennus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimus_kiinteistorakennus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimus_kiinteistorakennus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimus_kiinteistorakennus_id_seq OWNED BY public.ark_tutkimus_kiinteistorakennus.id;


--
-- Name: ark_tutkimus_kuntakyla; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimus_kuntakyla (
    id bigint NOT NULL,
    ark_tutkimus_id integer NOT NULL,
    kyla_id integer,
    kunta_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_tutkimus_kuntakyla_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimus_kuntakyla_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimus_kuntakyla_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimus_kuntakyla_id_seq OWNED BY public.ark_tutkimus_kuntakyla.id;


--
-- Name: ark_tutkimus_osoite; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimus_osoite (
    id bigint NOT NULL,
    ark_tutkimus_kiinteistorakennus_id bigint NOT NULL,
    rakennustunnus text,
    katunimi text,
    katunumero text,
    postinumero text,
    kuntanimi text,
    kieli text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: ark_tutkimus_osoite_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimus_osoite_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimus_osoite_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimus_osoite_id_seq OWNED BY public.ark_tutkimus_osoite.id;


--
-- Name: ark_tutkimusalue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimusalue (
    id bigint NOT NULL,
    ark_tutkimus_id integer NOT NULL,
    nimi text NOT NULL,
    sijaintikuvaus text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    sijainti public.geometry(Geometry,3067),
    muistiinpanot text,
    havainnot text
);


--
-- Name: ark_tutkimusalue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimusalue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimusalue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimusalue_id_seq OWNED BY public.ark_tutkimusalue.id;


--
-- Name: ark_tutkimusalue_yksikko; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimusalue_yksikko (
    id bigint NOT NULL,
    ark_tutkimusalue_id integer NOT NULL,
    yksikkotunnus text,
    kaivaus_valmis boolean DEFAULT false NOT NULL,
    tyonimi text,
    tyo_sijainti text,
    tyo_kaivajat text,
    kuvaus text,
    kuvaus_note text,
    yksikon_perusteet text,
    stratigrafiset_suhteet text,
    rajapinnat text,
    tulkinta text,
    tulkinta_note text,
    ajoitus text,
    ajoitus_note text,
    ajoituksen_perusteet text,
    ajoituksen_perusteet_note text,
    lisatiedot text,
    lisatiedot_note text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    yksikko_tyyppi_id bigint,
    yksikko_kaivaustapa_id bigint,
    yksikko_seulontatapa_id bigint,
    yksikon_numero integer,
    yksikon_perusteet_note text,
    stratigrafiset_suhteet_note text,
    rajapinnat_note text,
    yksikko_paamaalaji_id bigint,
    kaivaustapa_lisatieto text,
    kaivaustapa_lisatieto_note text,
    migraatiodata text
);


--
-- Name: ark_tutkimusalue_yksikko_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimusalue_yksikko_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimusalue_yksikko_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimusalue_yksikko_id_seq OWNED BY public.ark_tutkimusalue_yksikko.id;


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ark_tutkimusalue_yksikko_tyovaihe (
    id bigint NOT NULL,
    ark_tutkimusalue_yksikko_id integer NOT NULL,
    paivamaara date,
    kuvaus text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ark_tutkimusalue_yksikko_tyovaihe_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ark_tutkimusalue_yksikko_tyovaihe_id_seq OWNED BY public.ark_tutkimusalue_yksikko_tyovaihe.id;


--
-- Name: arvoalue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arvoalue (
    id integer NOT NULL,
    alue_id integer NOT NULL,
    nimi character varying,
    kuvaus text,
    keskipiste public.geometry(Geometry,3067),
    aluerajaus public.geometry(Geometry,3067),
    tarkistettu character varying,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    arvotustyyppi_id bigint,
    aluetyyppi_id bigint,
    inventointinumero integer DEFAULT 1 NOT NULL,
    yhteenveto text,
    paikkakunta text,
    arkeologinen_kohde boolean DEFAULT false
);


--
-- Name: arvoalue_arvoaluekulttuurihistoriallinenarvo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arvoalue_arvoaluekulttuurihistoriallinenarvo (
    arvoalue_id integer NOT NULL,
    kulttuurihistoriallinenarvo_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: arvoalue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.arvoalue_id_seq
    START WITH 2527
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: arvoalue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.arvoalue_id_seq OWNED BY public.arvoalue.id;


--
-- Name: arvoalue_kyla; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arvoalue_kyla (
    arvoalue_id integer NOT NULL,
    kyla_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: arvoalue_suojelutyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arvoalue_suojelutyyppi (
    id bigint NOT NULL,
    suojelutyyppi_id bigint NOT NULL,
    arvoalue_id bigint NOT NULL,
    merkinta text,
    selite text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: arvoalue_suojelutyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.arvoalue_suojelutyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: arvoalue_suojelutyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.arvoalue_suojelutyyppi_id_seq OWNED BY public.arvoalue_suojelutyyppi.id;


--
-- Name: arvoaluekulttuurihistoriallinenarvo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arvoaluekulttuurihistoriallinenarvo (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: arvoaluekulttuurihistoriallinenarvo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.arvoaluekulttuurihistoriallinenarvo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: arvoaluekulttuurihistoriallinenarvo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.arvoaluekulttuurihistoriallinenarvo_id_seq OWNED BY public.arvoaluekulttuurihistoriallinenarvo.id;


--
-- Name: arvotustyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arvotustyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: arvotustyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.arvotustyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: arvotustyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.arvotustyyppi_id_seq OWNED BY public.arvotustyyppi.id;


--
-- Name: asiasana; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asiasana (
    id bigint NOT NULL,
    asiasana_fi text NOT NULL,
    asiasana_se text,
    asiasana_en text,
    asiasanasto_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: asiasana_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asiasana_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asiasana_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asiasana_id_seq OWNED BY public.asiasana.id;


--
-- Name: asiasanasto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asiasanasto (
    id bigint NOT NULL,
    tunnus_fi text NOT NULL,
    tunnus_se text,
    tunnus_en text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: asiasanasto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asiasanasto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asiasanasto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asiasanasto_id_seq OWNED BY public.asiasanasto.id;


--
-- Name: entiteetti_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.entiteetti_tyyppi (
    id bigint NOT NULL,
    nimi text NOT NULL
);


--
-- Name: hoitotarve; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hoitotarve (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: hoitotarve_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hoitotarve_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hoitotarve_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hoitotarve_id_seq OWNED BY public.hoitotarve.id;


--
-- Name: inventoidut_rakennukset_mysql9_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventoidut_rakennukset_mysql9_id_seq
    START WITH 4361
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointijulkaisu; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointijulkaisu (
    id bigint NOT NULL,
    nimi text NOT NULL,
    kuvaus text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    kentat text
);


--
-- Name: inventointijulkaisu_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointijulkaisu_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointijulkaisu_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointijulkaisu_id_seq OWNED BY public.inventointijulkaisu.id;


--
-- Name: inventointijulkaisu_inventointiprojekti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointijulkaisu_inventointiprojekti (
    id bigint NOT NULL,
    inventointijulkaisu_id integer NOT NULL,
    inventointiprojekti_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: inventointijulkaisu_inventointiprojekti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointijulkaisu_inventointiprojekti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointijulkaisu_inventointiprojekti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointijulkaisu_inventointiprojekti_id_seq OWNED BY public.inventointijulkaisu_inventointiprojekti.id;


--
-- Name: inventointijulkaisu_kuntakyla; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointijulkaisu_kuntakyla (
    id bigint NOT NULL,
    inventointijulkaisu_id integer NOT NULL,
    kyla_id integer,
    kunta_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: inventointijulkaisu_kuntakyla_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointijulkaisu_kuntakyla_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointijulkaisu_kuntakyla_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointijulkaisu_kuntakyla_id_seq OWNED BY public.inventointijulkaisu_kuntakyla.id;


--
-- Name: inventointijulkaisu_taso; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointijulkaisu_taso (
    id bigint NOT NULL,
    inventointijulkaisu_id integer NOT NULL,
    entiteetti_tyyppi_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: inventointijulkaisu_taso_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointijulkaisu_taso_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointijulkaisu_taso_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointijulkaisu_taso_id_seq OWNED BY public.inventointijulkaisu_taso.id;


--
-- Name: inventointiprojekti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti (
    id integer NOT NULL,
    nimi character varying NOT NULL,
    kuvaus text,
    inventointiaika character varying,
    toimeksiantaja character varying,
    inventointitunnus character varying,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    tyyppi_id bigint,
    laji_id bigint DEFAULT (1)::bigint NOT NULL
);


--
-- Name: inventointiprojekti_ajanjakso; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti_ajanjakso (
    id bigint NOT NULL,
    inventointiprojekti_id bigint NOT NULL,
    alkupvm date NOT NULL,
    loppupvm date,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: inventointiprojekti_ajanjakso_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojekti_ajanjakso_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojekti_ajanjakso_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojekti_ajanjakso_id_seq OWNED BY public.inventointiprojekti_ajanjakso.id;


--
-- Name: inventointiprojekti_alue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti_alue (
    alue_id integer NOT NULL,
    inventointiprojekti_id integer NOT NULL,
    inventoija_nimi character varying,
    inventoija_arvo character varying,
    inventoija_id integer,
    inventoija_organisaatio character varying(150),
    kenttapaiva timestamp(6) with time zone,
    inventointipaiva timestamp(6) with time zone,
    id bigint NOT NULL,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: inventointiprojekti_alue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojekti_alue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojekti_alue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojekti_alue_id_seq OWNED BY public.inventointiprojekti_alue.id;


--
-- Name: inventointiprojekti_arvoalue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti_arvoalue (
    inventointiprojekti_id integer NOT NULL,
    arvoalue_id integer NOT NULL,
    inventoija_nimi character varying,
    inventoija_arvo character varying,
    inventoija_id integer,
    inventoija_organisaatio character varying(150),
    kenttapaiva timestamp(6) with time zone,
    inventointipaiva timestamp(6) with time zone,
    id bigint NOT NULL,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: inventointiprojekti_arvoalue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojekti_arvoalue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojekti_arvoalue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojekti_arvoalue_id_seq OWNED BY public.inventointiprojekti_arvoalue.id;


--
-- Name: inventointiprojekti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojekti_id_seq
    START WITH 250
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojekti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojekti_id_seq OWNED BY public.inventointiprojekti.id;


--
-- Name: inventointiprojekti_inventoija; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti_inventoija (
    id integer NOT NULL,
    inventointiprojekti_id integer NOT NULL,
    inventoija_nimi character varying,
    inventoija_arvo character varying,
    inventoija_id integer,
    inventoija_organisaatio character varying(150)
);


--
-- Name: inventointiprojekti_inventoija_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojekti_inventoija_id_seq
    START WITH 275
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojekti_inventoija_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojekti_inventoija_id_seq OWNED BY public.inventointiprojekti_inventoija.id;


--
-- Name: inventointiprojekti_kiinteisto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti_kiinteisto (
    inventointiprojekti_id integer NOT NULL,
    kiinteisto_id integer NOT NULL,
    inventoija_nimi character varying,
    inventointipaiva timestamp with time zone,
    inventointipaiva_tekstina character varying,
    kenttapaiva timestamp with time zone,
    inventoija_id integer,
    inventoija_organisaatio character varying(150),
    id bigint NOT NULL,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: COLUMN inventointiprojekti_kiinteisto.inventointipaiva_tekstina; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.inventointiprojekti_kiinteisto.inventointipaiva_tekstina IS 'for backwards compatiblity';


--
-- Name: inventointiprojekti_kiinteisto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojekti_kiinteisto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojekti_kiinteisto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojekti_kiinteisto_id_seq OWNED BY public.inventointiprojekti_kiinteisto.id;


--
-- Name: inventointiprojekti_kunta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti_kunta (
    inventointiprojekti_id integer NOT NULL,
    kunta_id integer NOT NULL
);


--
-- Name: inventointiprojekti_laji; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojekti_laji (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    tekninen_projekti boolean DEFAULT false NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: inventointiprojekti_laji_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojekti_laji_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojekti_laji_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojekti_laji_id_seq OWNED BY public.inventointiprojekti_laji.id;


--
-- Name: inventointiprojektityyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventointiprojektityyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: inventointiprojektityyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventointiprojektityyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventointiprojektityyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventointiprojektityyppi_id_seq OWNED BY public.inventointiprojektityyppi.id;


--
-- Name: jarjestelma_roolit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jarjestelma_roolit (
    id integer NOT NULL,
    katselu boolean NOT NULL,
    luonti boolean NOT NULL,
    muokkaus boolean NOT NULL,
    poisto boolean NOT NULL,
    entiteetti character varying(25) NOT NULL,
    rooli public.jarjestelma_rooli,
    osio public.jarjestelma_osio
);


--
-- Name: jarjestelma_roolit_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jarjestelma_roolit_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jarjestelma_roolit_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jarjestelma_roolit_id_seq OWNED BY public.jarjestelma_roolit.id;


--
-- Name: julkaisu; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.julkaisu (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    aika date NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: julkaisu_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.julkaisu_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: julkaisu_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.julkaisu_id_seq OWNED BY public.julkaisu.id;


--
-- Name: katetyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.katetyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: katetyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.katetyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: katetyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.katetyyppi_id_seq OWNED BY public.katetyyppi.id;


--
-- Name: kattotyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kattotyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: kattotyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kattotyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kattotyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kattotyyppi_id_seq OWNED BY public.kattotyyppi.id;


--
-- Name: kayttaja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kayttaja (
    id integer NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    etunimi character varying(30) NOT NULL,
    sukunimi character varying(30) NOT NULL,
    sahkoposti character varying(100) NOT NULL,
    salasana character varying(60) NOT NULL,
    kieli character varying(2) DEFAULT 'fi'::character varying NOT NULL,
    organisaatio character varying(150),
    salasana_avain character varying(100),
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    poistettu timestamp(0) without time zone,
    rooli public.jarjestelma_rooli,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    "vanhatKarttavarit" boolean DEFAULT false NOT NULL,
    ark_rooli character varying(255),
    tekijanoikeuslauseke text
);


--
-- Name: kayttaja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kayttaja_id_seq
    START WITH 230
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kayttaja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kayttaja_id_seq OWNED BY public.kayttaja.id;


--
-- Name: kayttaja_salasanaresetointi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kayttaja_salasanaresetointi (
    sahkoposti character varying(100) NOT NULL,
    avain character varying(255) NOT NULL,
    pvm_luotu timestamp(0) without time zone NOT NULL
);


--
-- Name: kayttotarkoitus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kayttotarkoitus (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: kayttotarkoitus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kayttotarkoitus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kayttotarkoitus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kayttotarkoitus_id_seq OWNED BY public.kayttotarkoitus.id;


--
-- Name: keramiikkatyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.keramiikkatyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: keramiikkatyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.keramiikkatyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: keramiikkatyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.keramiikkatyyppi_id_seq OWNED BY public.keramiikkatyyppi.id;


--
-- Name: kiinteisto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiinteisto (
    id integer NOT NULL,
    kyla_id integer,
    kiinteistotunnus character varying,
    nimi character varying,
    osoite text,
    postinumero character varying,
    paikkakunta character varying,
    aluetyyppi character varying,
    arvotus character varying,
    historiallinen_tilatyyppi character varying,
    lisatiedot text,
    perustelut_yhteenveto text,
    lahteet text,
    kiinteiston_sijainti public.geometry(Geometry,3067),
    asutushistoria text,
    lahiymparisto text,
    pihapiiri text,
    omistajatiedot text,
    arkeologinen_intressi character varying,
    muu_historia character varying,
    perustelut text,
    tarkistettu date,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    data_sailo text,
    arvotustyyppi_id bigint,
    julkinen boolean DEFAULT true NOT NULL,
    palstanumero integer,
    arkeologinen_kohde boolean DEFAULT false,
    linkit_paikallismuseoihin text,
    paikallismuseot_kuvaus text
);


--
-- Name: kiinteisto_aluetyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiinteisto_aluetyyppi (
    id bigint NOT NULL,
    kiinteisto_id bigint NOT NULL,
    aluetyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: kiinteisto_aluetyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kiinteisto_aluetyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kiinteisto_aluetyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kiinteisto_aluetyyppi_id_seq OWNED BY public.kiinteisto_aluetyyppi.id;


--
-- Name: kiinteisto_historiallinen_tilatyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiinteisto_historiallinen_tilatyyppi (
    id bigint NOT NULL,
    kiinteisto_id bigint NOT NULL,
    tilatyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: kiinteisto_historiallinen_tilatyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kiinteisto_historiallinen_tilatyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kiinteisto_historiallinen_tilatyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kiinteisto_historiallinen_tilatyyppi_id_seq OWNED BY public.kiinteisto_historiallinen_tilatyyppi.id;


--
-- Name: kiinteisto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kiinteisto_id_seq
    START WITH 44840
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kiinteisto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kiinteisto_id_seq OWNED BY public.kiinteisto.id;


--
-- Name: kiinteisto_kiinteistokulttuurihistoriallinenarvo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiinteisto_kiinteistokulttuurihistoriallinenarvo (
    kiinteisto_id integer NOT NULL,
    kulttuurihistoriallinenarvo_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: kiinteisto_suojelutyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiinteisto_suojelutyyppi (
    id bigint NOT NULL,
    suojelutyyppi_id bigint NOT NULL,
    kiinteisto_id bigint NOT NULL,
    merkinta text,
    selite text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: kiinteisto_suojelutyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kiinteisto_suojelutyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kiinteisto_suojelutyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kiinteisto_suojelutyyppi_id_seq OWNED BY public.kiinteisto_suojelutyyppi.id;


--
-- Name: kiinteistokulttuurihistoriallinenarvo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiinteistokulttuurihistoriallinenarvo (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: kiinteistokulttuurihistoriallinenarvo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kiinteistokulttuurihistoriallinenarvo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kiinteistokulttuurihistoriallinenarvo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kiinteistokulttuurihistoriallinenarvo_id_seq OWNED BY public.kiinteistokulttuurihistoriallinenarvo.id;


--
-- Name: kokoelma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kokoelma (
    id bigint NOT NULL,
    museo_id bigint NOT NULL,
    paakokoelma_id bigint,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: kokoelma_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kokoelma_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kokoelma_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kokoelma_id_seq OWNED BY public.kokoelma.id;


--
-- Name: kori; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kori (
    id bigint NOT NULL,
    korityyppi_id bigint,
    nimi text,
    kuvaus text,
    julkinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    kori_id_lista json,
    mip_alue text
);


--
-- Name: kori_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kori_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kori_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kori_id_seq OWNED BY public.kori.id;


--
-- Name: korityyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.korityyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    taulu text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: korityyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.korityyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: korityyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.korityyppi_id_seq OWNED BY public.korityyppi.id;


--
-- Name: kunnat_kylat_2013; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kunnat_kylat_2013 (
    kuntakoodi character varying(255) NOT NULL,
    kuntanimi character varying(255) NOT NULL,
    kylakoodi character varying(255) NOT NULL,
    kylanimi character varying(255) NOT NULL
);


--
-- Name: kunta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kunta (
    id integer NOT NULL,
    kuntanumero character varying(3) NOT NULL,
    nimi character varying NOT NULL,
    nimi_se character varying,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer
);


--
-- Name: kunta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kunta_id_seq
    START WITH 67
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kunta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kunta_id_seq OWNED BY public.kunta.id;


--
-- Name: kunto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kunto (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: kunto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kunto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kunto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kunto_id_seq OWNED BY public.kunto.id;


--
-- Name: kuntotyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuntotyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: kuntotyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kuntotyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kuntotyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kuntotyyppi_id_seq OWNED BY public.kuntotyyppi.id;


--
-- Name: kuva; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva (
    id integer NOT NULL,
    nimi character varying NOT NULL,
    alkuperainen_nimi character varying NOT NULL,
    polku character varying NOT NULL,
    otsikko character varying,
    kuvaus text,
    kayttaja_id integer NOT NULL,
    pvm_kuvaus timestamp without time zone,
    kuvaaja character varying,
    julkinen boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    poistettu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    tekijanoikeuslauseke text
);


--
-- Name: kuva_alue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva_alue (
    kuva_id integer NOT NULL,
    alue_id integer NOT NULL,
    jarjestys bigint
);


--
-- Name: kuva_arvoalue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva_arvoalue (
    kuva_id integer NOT NULL,
    arvoalue_id integer NOT NULL,
    jarjestys bigint
);


--
-- Name: kuva_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kuva_id_seq
    START WITH 144242
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kuva_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kuva_id_seq OWNED BY public.kuva.id;


--
-- Name: kuva_kiinteisto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva_kiinteisto (
    kuva_id integer NOT NULL,
    kiinteisto_id integer NOT NULL,
    jarjestys bigint
);


--
-- Name: kuva_kyla; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva_kyla (
    kuva_id integer NOT NULL,
    kyla_id integer NOT NULL,
    jarjestys bigint
);


--
-- Name: kuva_porrashuone; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva_porrashuone (
    kuva_id integer NOT NULL,
    porrashuone_id integer NOT NULL,
    jarjestys bigint
);


--
-- Name: kuva_rakennus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva_rakennus (
    kuva_id integer NOT NULL,
    rakennus_id integer NOT NULL,
    jarjestys bigint
);


--
-- Name: kuva_suunnittelija; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kuva_suunnittelija (
    kuva_id integer NOT NULL,
    suunnittelija_id integer NOT NULL,
    jarjestys integer NOT NULL
);


--
-- Name: kyla; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kyla (
    id integer NOT NULL,
    kunta_id integer NOT NULL,
    kylanumero character varying(32) NOT NULL,
    nimi character varying NOT NULL,
    nimi_se character varying,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer
);


--
-- Name: kyla_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kyla_id_seq
    START WITH 3266
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kyla_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kyla_id_seq OWNED BY public.kyla.id;


--
-- Name: laatu; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.laatu (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: laatu_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.laatu_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: laatu_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.laatu_id_seq OWNED BY public.laatu.id;


--
-- Name: liite_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.liite_tyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: liite_tyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.liite_tyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: liite_tyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.liite_tyyppi_id_seq OWNED BY public.liite_tyyppi.id;


--
-- Name: logged_actions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.logged_actions (
    event_id bigint NOT NULL,
    schema_name text NOT NULL,
    table_name text NOT NULL,
    relid oid NOT NULL,
    session_user_name text,
    application_user_id bigint,
    action_tstamp_tx timestamp with time zone NOT NULL,
    action_tstamp_stm timestamp with time zone NOT NULL,
    action_tstamp_clk timestamp with time zone NOT NULL,
    transaction_id bigint,
    application_name text,
    client_addr inet,
    client_port integer,
    client_query text,
    action text NOT NULL,
    row_data public.hstore,
    changed_fields public.hstore,
    statement_only boolean NOT NULL,
    CONSTRAINT logged_actions_action_check CHECK ((action = ANY (ARRAY['I'::text, 'D'::text, 'U'::text, 'T'::text])))
);


--
-- Name: logged_actions_event_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.logged_actions_event_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: logged_actions_event_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.logged_actions_event_id_seq OWNED BY public.logged_actions.event_id;


--
-- Name: loyto_kategoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.loyto_kategoria (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    lyhenne_fi text,
    lyhenne_se text,
    lyhenne_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: loyto_kategoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.loyto_kategoria_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: loyto_kategoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.loyto_kategoria_id_seq OWNED BY public.loyto_kategoria.id;


--
-- Name: loyto_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.loyto_tyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: loyto_tyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.loyto_tyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: loyto_tyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.loyto_tyyppi_id_seq OWNED BY public.loyto_tyyppi.id;


--
-- Name: maastomerkinta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maastomerkinta (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: maastomerkinta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.maastomerkinta_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: maastomerkinta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.maastomerkinta_id_seq OWNED BY public.maastomerkinta.id;


--
-- Name: materiaali; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.materiaali (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: materiaali_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.materiaali_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: materiaali_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.materiaali_id_seq OWNED BY public.materiaali.id;


--
-- Name: matkaraportinsyy; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matkaraportinsyy (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: matkaraportinsyy_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.matkaraportinsyy_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matkaraportinsyy_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.matkaraportinsyy_id_seq OWNED BY public.matkaraportinsyy.id;


--
-- Name: matkaraportti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matkaraportti (
    id bigint NOT NULL,
    tehtavan_kuvaus text NOT NULL,
    huomautukset text NOT NULL,
    matkapvm date NOT NULL,
    kiinteisto_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: matkaraportti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.matkaraportti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matkaraportti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.matkaraportti_id_seq OWNED BY public.matkaraportti.id;


--
-- Name: matkaraportti_syy; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matkaraportti_syy (
    id bigint NOT NULL,
    matkaraportti_id integer NOT NULL,
    matkaraportinsyy_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: matkaraportti_syy_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.matkaraportti_syy_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matkaraportti_syy_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.matkaraportti_syy_id_seq OWNED BY public.matkaraportti_syy.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: museo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.museo (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: museo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.museo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: museo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.museo_id_seq OWNED BY public.museo.id;


--
-- Name: muutoshistoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.muutoshistoria (
    id bigint NOT NULL,
    ref_table character varying NOT NULL,
    ref_keys character varying NOT NULL,
    ref_values character varying NOT NULL,
    ref_action character varying NOT NULL,
    kayttaja_id integer,
    lahde character varying,
    muutos_aika timestamp with time zone DEFAULT now() NOT NULL,
    kommentit text
);


--
-- Name: muutoshistoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.muutoshistoria_id_seq
    START WITH 608235
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: muutoshistoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.muutoshistoria_id_seq OWNED BY public.muutoshistoria.id;


--
-- Name: muutoshistoria_tieto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.muutoshistoria_tieto (
    muutoshistoria_id bigint NOT NULL,
    attribuutti character varying NOT NULL,
    arvo text NOT NULL
);


--
-- Name: nayttely; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nayttely (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    paikka_fi text,
    paikka_se text,
    paikka_en text,
    alkaen date NOT NULL,
    paattyen date,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: nayttely_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nayttely_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nayttely_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nayttely_id_seq OWNED BY public.nayttely.id;


--
-- Name: ocm_luokka; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ocm_luokka (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: perustustyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.perustustyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: perustustyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.perustustyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: perustustyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.perustustyyppi_id_seq OWNED BY public.perustustyyppi.id;


--
-- Name: porrashuone; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.porrashuone (
    id integer NOT NULL,
    rakennus_id integer NOT NULL,
    huoneistojen_maara character varying,
    portaiden_muoto character varying,
    kattoikkuna boolean,
    hissi boolean,
    hissin_kuvaus text,
    yleiskuvaus text,
    sisaantulokerros text,
    ovet_ja_ikkunat text,
    portaat_tasanteet_kaiteet text,
    pintamateriaalit text,
    muu_kiintea_sisustus text,
    talotekniikka text,
    tehdyt_korjaukset text,
    esteettomyys text,
    lisatiedot text,
    porrashuoneen_tunnus character varying NOT NULL,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    porrashuonetyyppi_id bigint
);


--
-- Name: porrashuone_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.porrashuone_id_seq
    START WITH 1411
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: porrashuone_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.porrashuone_id_seq OWNED BY public.porrashuone.id;


--
-- Name: porrashuonetyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.porrashuonetyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: porrashuonetyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.porrashuonetyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: porrashuonetyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.porrashuonetyyppi_id_seq OWNED BY public.porrashuonetyyppi.id;


--
-- Name: projekti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projekti (
    id bigint NOT NULL,
    tunnus character varying(256) NOT NULL,
    nimi text NOT NULL,
    kuvaus text NOT NULL,
    projekti_tyyppi_id bigint NOT NULL,
    kunta_id integer,
    julkinen boolean DEFAULT true NOT NULL,
    paanumero character varying(256),
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: projekti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.projekti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projekti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.projekti_id_seq OWNED BY public.projekti.id;


--
-- Name: projekti_kayttaja_rooli; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projekti_kayttaja_rooli (
    kayttaja_id integer NOT NULL,
    projekti_id bigint NOT NULL,
    projekti_rooli_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL
);


--
-- Name: projekti_rooli; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projekti_rooli (
    id bigint NOT NULL,
    nimi_fi text NOT NULL,
    kuvaus_fi text NOT NULL,
    nimi_se text NOT NULL,
    kuvaus_se text NOT NULL,
    nimi_en text NOT NULL,
    kuvaus_en text NOT NULL,
    poistettu boolean DEFAULT false NOT NULL
);


--
-- Name: projekti_sijainti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projekti_sijainti (
    id bigint NOT NULL,
    projekti_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    sijainti public.geometry(Geometry,3067)
);


--
-- Name: projekti_sijainti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.projekti_sijainti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projekti_sijainti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.projekti_sijainti_id_seq OWNED BY public.projekti_sijainti.id;


--
-- Name: projekti_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projekti_tyyppi (
    id bigint NOT NULL,
    nimi_fi text NOT NULL,
    kuvaus_fi text NOT NULL,
    nimi_se text NOT NULL,
    kuvaus_se text NOT NULL,
    nimi_en text NOT NULL,
    kuvaus_en text NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rajaustarkkuus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rajaustarkkuus (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: rajaustarkkuus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rajaustarkkuus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rajaustarkkuus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rajaustarkkuus_id_seq OWNED BY public.rajaustarkkuus.id;


--
-- Name: rakennus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus (
    id integer NOT NULL,
    kiinteisto_id integer,
    inventointinumero integer,
    rakennustyyppi_kuvaus character varying,
    kerroslukumaara character varying,
    alkuperainen_kaytto character varying,
    nykykaytto character varying,
    perustus character varying,
    runko character varying,
    vuoraus character varying,
    ulkovari character varying,
    katto character varying,
    kate character varying,
    kunto character varying,
    erityispiirteet text,
    rakennushistoria text,
    sisatilakuvaus text,
    rakennuksen_sijainti public.geometry(Geometry,3067),
    muut_tiedot text,
    nykyinen_tyyli character varying,
    arvotus character varying,
    asuin_ja_liikehuoneistoja character varying,
    rakennustunnus character varying,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer,
    data_sailo text,
    kuntotyyppi_id bigint,
    arvotustyyppi_id bigint,
    nykyinen_tyyli_id bigint,
    rakennusvuosi_selite text,
    kulttuurihistoriallisetarvot_perustelut character varying(254),
    purettu boolean DEFAULT false NOT NULL,
    rakennusvuosi_alku integer,
    rakennusvuosi_loppu integer,
    postinumero text
);


--
-- Name: rakennus_alkuperainenkaytto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_alkuperainenkaytto (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    kayttotarkoitus_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_alkuperainenkaytto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_alkuperainenkaytto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_alkuperainenkaytto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_alkuperainenkaytto_id_seq OWNED BY public.rakennus_alkuperainenkaytto.id;


--
-- Name: rakennus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_id_seq
    START WITH 104783
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_id_seq OWNED BY public.rakennus.id;


--
-- Name: rakennus_katetyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_katetyyppi (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    katetyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_katetyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_katetyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_katetyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_katetyyppi_id_seq OWNED BY public.rakennus_katetyyppi.id;


--
-- Name: rakennus_kattotyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_kattotyyppi (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    kattotyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_kattotyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_kattotyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_kattotyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_kattotyyppi_id_seq OWNED BY public.rakennus_kattotyyppi.id;


--
-- Name: rakennus_muutosvuosi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_muutosvuosi (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    alkuvuosi integer,
    loppuvuosi integer,
    selite text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_muutosvuosi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_muutosvuosi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_muutosvuosi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_muutosvuosi_id_seq OWNED BY public.rakennus_muutosvuosi.id;


--
-- Name: rakennus_nykykaytto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_nykykaytto (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    kayttotarkoitus_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_nykykaytto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_nykykaytto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_nykykaytto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_nykykaytto_id_seq OWNED BY public.rakennus_nykykaytto.id;


--
-- Name: rakennus_omistaja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_omistaja (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    etunimi text NOT NULL,
    sukunimi text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_omistaja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_omistaja_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_omistaja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_omistaja_id_seq OWNED BY public.rakennus_omistaja.id;


--
-- Name: rakennus_osoite; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_osoite (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    katunimi text NOT NULL,
    katunumero text,
    jarjestysnumero integer,
    kieli text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_osoite_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_osoite_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_osoite_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_osoite_id_seq OWNED BY public.rakennus_osoite.id;


--
-- Name: rakennus_perustustyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_perustustyyppi (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    perustustyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_perustustyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_perustustyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_perustustyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_perustustyyppi_id_seq OWNED BY public.rakennus_perustustyyppi.id;


--
-- Name: rakennus_rakennuskulttuurihistoriallinenarvo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_rakennuskulttuurihistoriallinenarvo (
    rakennus_id integer NOT NULL,
    kulttuurihistoriallinenarvo_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: rakennus_rakennustyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_rakennustyyppi (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    rakennustyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_rakennustyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_rakennustyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_rakennustyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_rakennustyyppi_id_seq OWNED BY public.rakennus_rakennustyyppi.id;


--
-- Name: rakennus_runkotyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_runkotyyppi (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    runkotyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_runkotyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_runkotyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_runkotyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_runkotyyppi_id_seq OWNED BY public.rakennus_runkotyyppi.id;


--
-- Name: rakennus_suojelutyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_suojelutyyppi (
    id bigint NOT NULL,
    suojelutyyppi_id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    merkinta text,
    selite text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_suojelutyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_suojelutyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_suojelutyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_suojelutyyppi_id_seq OWNED BY public.rakennus_suojelutyyppi.id;


--
-- Name: rakennus_vuoraustyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennus_vuoraustyyppi (
    id bigint NOT NULL,
    rakennus_id bigint NOT NULL,
    vuoraustyyppi_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: rakennus_vuoraustyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennus_vuoraustyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennus_vuoraustyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennus_vuoraustyyppi_id_seq OWNED BY public.rakennus_vuoraustyyppi.id;


--
-- Name: rakennuskulttuurihistoriallinenarvo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennuskulttuurihistoriallinenarvo (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: rakennuskulttuurihistoriallinenarvo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennuskulttuurihistoriallinenarvo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennuskulttuurihistoriallinenarvo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennuskulttuurihistoriallinenarvo_id_seq OWNED BY public.rakennuskulttuurihistoriallinenarvo.id;


--
-- Name: rakennustyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakennustyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: rakennustyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakennustyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakennustyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakennustyyppi_id_seq OWNED BY public.rakennustyyppi.id;


--
-- Name: rakentaja_vanha; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rakentaja_vanha (
    id integer NOT NULL,
    aakkostus character varying NOT NULL,
    nimi character varying NOT NULL,
    ammatti_arvo character varying,
    kuvaus text
);


--
-- Name: rakentaja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rakentaja_id_seq
    START WITH 1338
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rakentaja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rakentaja_id_seq OWNED BY public.rakentaja_vanha.id;


--
-- Name: rauhoitusluokka; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rauhoitusluokka (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: rauhoitusluokka_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rauhoitusluokka_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rauhoitusluokka_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rauhoitusluokka_id_seq OWNED BY public.rauhoitusluokka.id;


--
-- Name: runkotyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.runkotyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: runkotyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.runkotyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: runkotyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.runkotyyppi_id_seq OWNED BY public.runkotyyppi.id;


--
-- Name: suojelumerkinta_kohde; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suojelumerkinta_kohde (
    kohde character varying NOT NULL,
    nimi character varying NOT NULL,
    kuvaus text,
    jarjestysnumero integer
);


--
-- Name: suojelutyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suojelutyyppi (
    id bigint NOT NULL,
    suojelutyyppi_ryhma_id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: suojelutyyppi_ryhma; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suojelutyyppi_ryhma (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: suunnittelija; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija (
    id bigint NOT NULL,
    sukunimi text,
    etunimi text,
    kuvaus text,
    suunnittelija_laji_id bigint,
    suunnittelija_ammattiarvo_id bigint,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: suunnittelija_ammattiarvo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_ammattiarvo (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: suunnittelija_ammattiarvo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suunnittelija_ammattiarvo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suunnittelija_ammattiarvo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suunnittelija_ammattiarvo_id_seq OWNED BY public.suunnittelija_ammattiarvo.id;


--
-- Name: suunnittelija_vanha; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_vanha (
    etunimi text,
    sukunimi text,
    ammatti_arvo text,
    kuvaus text,
    id integer NOT NULL,
    rakentajan_nimi text,
    poistettu timestamp(0) without time zone,
    luotu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer
);


--
-- Name: suunnittelija_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suunnittelija_id_seq
    START WITH 2335
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suunnittelija_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suunnittelija_id_seq OWNED BY public.suunnittelija_vanha.id;


--
-- Name: suunnittelija_id_seq1; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suunnittelija_id_seq1
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suunnittelija_id_seq1; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suunnittelija_id_seq1 OWNED BY public.suunnittelija.id;


--
-- Name: suunnittelija_laji; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_laji (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    yritys boolean DEFAULT true NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: suunnittelija_laji_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suunnittelija_laji_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suunnittelija_laji_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suunnittelija_laji_id_seq OWNED BY public.suunnittelija_laji.id;


--
-- Name: suunnittelija_porrashuone; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_porrashuone (
    id bigint NOT NULL,
    porrashuone_id integer NOT NULL,
    suunnittelija_id bigint NOT NULL,
    suunnittelija_tyyppi_id bigint NOT NULL,
    suunnitteluvuosi_alku integer,
    suunnitteluvuosi_loppu integer,
    lisatieto text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: suunnittelija_porrashuone_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suunnittelija_porrashuone_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suunnittelija_porrashuone_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suunnittelija_porrashuone_id_seq OWNED BY public.suunnittelija_porrashuone.id;


--
-- Name: suunnittelija_porrashuone_vanha; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_porrashuone_vanha (
    porrashuone_id integer NOT NULL,
    suunnittelijan_nimi character varying,
    tyyppi character varying,
    laji character varying,
    lisatiedot text,
    ammatti_arvo character varying,
    yritys character varying(255),
    suunnittelija_id integer NOT NULL
);


--
-- Name: suunnittelija_rakennus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_rakennus (
    id bigint NOT NULL,
    rakennus_id integer NOT NULL,
    suunnittelija_id bigint NOT NULL,
    suunnittelija_tyyppi_id bigint NOT NULL,
    suunnitteluvuosi_alku integer,
    suunnitteluvuosi_loppu integer,
    lisatieto text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: suunnittelija_rakennus_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suunnittelija_rakennus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suunnittelija_rakennus_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suunnittelija_rakennus_id_seq OWNED BY public.suunnittelija_rakennus.id;


--
-- Name: suunnittelija_rakennus_vanha; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_rakennus_vanha (
    rakennus_id integer,
    suunnittelija_id integer,
    lisatiedot text,
    yritys text,
    tyyppi text,
    ammatti_arvo text,
    laji text
);


--
-- Name: suunnittelija_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suunnittelija_tyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: suunnittelija_tyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suunnittelija_tyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suunnittelija_tyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suunnittelija_tyyppi_id_seq OWNED BY public.suunnittelija_tyyppi.id;


--
-- Name: talteenottotapa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.talteenottotapa (
    id bigint NOT NULL,
    nimi_fi text NOT NULL,
    kuvaus_fi text NOT NULL,
    nimi_se text NOT NULL,
    kuvaus_se text NOT NULL,
    nimi_en text NOT NULL,
    kuvaus_en text NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: talteenottotapa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.talteenottotapa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: talteenottotapa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.talteenottotapa_id_seq OWNED BY public.talteenottotapa.id;


--
-- Name: tekijanoikeuslauseke; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tekijanoikeuslauseke (
    id bigint NOT NULL,
    osio text,
    lauseke text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer,
    otsikko text NOT NULL
);


--
-- Name: tekijanoikeuslauseke_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tekijanoikeuslauseke_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tekijanoikeuslauseke_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tekijanoikeuslauseke_id_seq OWNED BY public.tekijanoikeuslauseke.id;


--
-- Name: tiedosto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto (
    id integer NOT NULL,
    nimi character varying NOT NULL,
    alkuperainen_nimi character varying NOT NULL,
    polku character varying NOT NULL,
    otsikko character varying NOT NULL,
    kuvaus character varying,
    kayttaja_id integer NOT NULL,
    luotu timestamp without time zone DEFAULT now() NOT NULL,
    poistettu timestamp(0) without time zone,
    muokattu timestamp(0) without time zone,
    luoja integer,
    muokkaaja integer,
    poistaja integer
);


--
-- Name: tiedosto_alue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto_alue (
    tiedosto_id integer NOT NULL,
    alue_id integer NOT NULL
);


--
-- Name: tiedosto_arvoalue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto_arvoalue (
    tiedosto_id integer NOT NULL,
    arvoalue_id integer NOT NULL
);


--
-- Name: tiedosto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tiedosto_id_seq
    START WITH 1750
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tiedosto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tiedosto_id_seq OWNED BY public.tiedosto.id;


--
-- Name: tiedosto_kiinteisto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto_kiinteisto (
    tiedosto_id integer NOT NULL,
    kiinteisto_id integer NOT NULL
);


--
-- Name: tiedosto_kunta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto_kunta (
    tiedosto_id integer NOT NULL,
    kunta_id integer NOT NULL
);


--
-- Name: tiedosto_porrashuone; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto_porrashuone (
    tiedosto_id integer NOT NULL,
    porrashuone_id integer NOT NULL
);


--
-- Name: tiedosto_rakennus; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto_rakennus (
    tiedosto_id integer NOT NULL,
    rakennus_id integer NOT NULL
);


--
-- Name: tiedosto_suunnittelija; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tiedosto_suunnittelija (
    tiedosto_id integer NOT NULL,
    suunnittelija_id integer NOT NULL
);


--
-- Name: tilatyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tilatyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: tilatyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tilatyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tilatyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tilatyyppi_id_seq OWNED BY public.tilatyyppi.id;


--
-- Name: tyylisuunta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tyylisuunta (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: tyylisuunta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tyylisuunta_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tyylisuunta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tyylisuunta_id_seq OWNED BY public.tyylisuunta.id;


--
-- Name: valinnat; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.valinnat (
    id integer NOT NULL,
    kategoria character varying NOT NULL,
    arvo_fi character varying NOT NULL,
    arvo_se character varying
);


--
-- Name: valinnat_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.valinnat_id_seq
    START WITH 321
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: valinnat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.valinnat_id_seq OWNED BY public.valinnat.id;


--
-- Name: varasto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.varasto (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    kuvaus_fi text,
    kuvaus_se text,
    kuvaus_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    poistettu boolean DEFAULT false NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: varasto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.varasto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: varasto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.varasto_id_seq OWNED BY public.varasto.id;


--
-- Name: vuoraustyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vuoraustyyppi (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistaja integer,
    poistettu timestamp(0) without time zone
);


--
-- Name: vuoraustyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vuoraustyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vuoraustyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vuoraustyyppi_id_seq OWNED BY public.vuoraustyyppi.id;


--
-- Name: wms_rajapinta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wms_rajapinta (
    id integer NOT NULL,
    url text[] NOT NULL,
    taso character varying NOT NULL,
    nimi character varying NOT NULL,
    tyyppi text,
    kaytossa boolean
);


--
-- Name: wms_rajapinta_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.wms_rajapinta_id_seq
    START WITH 17
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: wms_rajapinta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.wms_rajapinta_id_seq OWNED BY public.wms_rajapinta.id;


--
-- Name: yksikko; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko (
    id bigint NOT NULL,
    tunnus character varying(256) NOT NULL,
    projekti_id bigint NOT NULL,
    yksikko_tyyppi_id bigint NOT NULL,
    peruste text,
    kuvaus text,
    rajapinnat text,
    stratigrafiset_havainnot text,
    loytomateriaali text,
    tulkinta text,
    ajoitus text,
    hairioisyys text,
    lisatiedot text,
    muistiinpanot text,
    yksikon_elinkaari_id bigint,
    uusi_yksikko bigint,
    yhdistetty bigint,
    tutkija integer,
    sijainti_kuvaus text,
    muut_pohjavaaitukset text,
    muut_pintavaaitukset text,
    koostumus text,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    x1 numeric(10,6),
    x2 numeric(10,6),
    y1 numeric(10,6),
    y2 numeric(10,6),
    z1 numeric(10,6),
    z2 numeric(10,6)
);


--
-- Name: yksikko_asiasana; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_asiasana (
    id bigint NOT NULL,
    yksikko_id bigint NOT NULL,
    asiasana text NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: yksikko_asiasana_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_asiasana_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_asiasana_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_asiasana_id_seq OWNED BY public.yksikko_asiasana.id;


--
-- Name: yksikko_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_id_seq OWNED BY public.yksikko.id;


--
-- Name: yksikko_kaivaustapa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_kaivaustapa (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: yksikko_kaivaustapa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_kaivaustapa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_kaivaustapa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_kaivaustapa_id_seq OWNED BY public.yksikko_kaivaustapa.id;


--
-- Name: yksikko_maalaji; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_maalaji (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: yksikko_maalaji_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_maalaji_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_maalaji_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_maalaji_id_seq OWNED BY public.yksikko_maalaji.id;


--
-- Name: yksikko_muut_maalajit; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_muut_maalajit (
    id bigint NOT NULL,
    ark_tutkimusalue_yksikko_id integer NOT NULL,
    yksikko_muu_maalaji_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: yksikko_muut_maalajit_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_muut_maalajit_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_muut_maalajit_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_muut_maalajit_id_seq OWNED BY public.yksikko_muut_maalajit.id;


--
-- Name: yksikko_paasekoitteet; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_paasekoitteet (
    id bigint NOT NULL,
    ark_tutkimusalue_yksikko_id integer NOT NULL,
    yksikko_paasekoite_id integer NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: yksikko_paasekoitteet_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_paasekoitteet_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_paasekoitteet_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_paasekoitteet_id_seq OWNED BY public.yksikko_paasekoitteet.id;


--
-- Name: yksikko_seulontatapa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_seulontatapa (
    id bigint NOT NULL,
    nimi_fi text,
    nimi_se text,
    nimi_en text,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    poistettu timestamp(0) without time zone,
    poistaja integer
);


--
-- Name: yksikko_seulontatapa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_seulontatapa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_seulontatapa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_seulontatapa_id_seq OWNED BY public.yksikko_seulontatapa.id;


--
-- Name: yksikko_sijainti; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_sijainti (
    id bigint NOT NULL,
    yksikko_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer,
    sijainti public.geometry(Geometry,3067)
);


--
-- Name: yksikko_sijainti_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_sijainti_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_sijainti_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_sijainti_id_seq OWNED BY public.yksikko_sijainti.id;


--
-- Name: yksikko_talteenottotapa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_talteenottotapa (
    id bigint NOT NULL,
    yksikko_id bigint NOT NULL,
    talteenottotapa_id bigint NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: yksikko_talteenottotapa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_talteenottotapa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_talteenottotapa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_talteenottotapa_id_seq OWNED BY public.yksikko_talteenottotapa.id;


--
-- Name: yksikko_tyyppi; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikko_tyyppi (
    id bigint NOT NULL,
    nimi_fi text NOT NULL,
    nimi_se text NOT NULL,
    nimi_en text NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: yksikko_tyyppi_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikko_tyyppi_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikko_tyyppi_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikko_tyyppi_id_seq OWNED BY public.yksikko_tyyppi.id;


--
-- Name: yksikon_elinkaari; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yksikon_elinkaari (
    id bigint NOT NULL,
    nimi_fi text NOT NULL,
    kuvaus_fi text NOT NULL,
    nimi_se text NOT NULL,
    kuvaus_se text NOT NULL,
    nimi_en text NOT NULL,
    kuvaus_en text NOT NULL,
    aktiivinen boolean DEFAULT true NOT NULL,
    luotu timestamp(0) without time zone DEFAULT now() NOT NULL,
    luoja integer NOT NULL,
    muokattu timestamp(0) without time zone,
    muokkaaja integer
);


--
-- Name: yksikon_elinkaari_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yksikon_elinkaari_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yksikon_elinkaari_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yksikon_elinkaari_id_seq OWNED BY public.yksikon_elinkaari.id;


--
-- Name: ajoitus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitus ALTER COLUMN id SET DEFAULT nextval('public.ajoitus_id_seq'::regclass);


--
-- Name: ajoitustarkenne id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitustarkenne ALTER COLUMN id SET DEFAULT nextval('public.ajoitustarkenne_id_seq'::regclass);


--
-- Name: alkuperaisyys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alkuperaisyys ALTER COLUMN id SET DEFAULT nextval('public.alkuperaisyys_id_seq'::regclass);


--
-- Name: alue id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alue ALTER COLUMN id SET DEFAULT nextval('public.alue_id_seq'::regclass);


--
-- Name: aluetyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aluetyyppi ALTER COLUMN id SET DEFAULT nextval('public.aluetyyppi_id_seq'::regclass);


--
-- Name: ark_alakohde_ajoitus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_ajoitus ALTER COLUMN id SET DEFAULT nextval('public.ark_alakohde_ajoitus_id_seq'::regclass);


--
-- Name: ark_alakohde_sijainti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_sijainti ALTER COLUMN id SET DEFAULT nextval('public.ark_alakohde_sijainti_id_seq'::regclass);


--
-- Name: ark_kartta id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta ALTER COLUMN id SET DEFAULT nextval('public.ark_kartta_id_seq'::regclass);


--
-- Name: ark_kartta_asiasana id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_asiasana ALTER COLUMN id SET DEFAULT nextval('public.ark_kartta_asiasana_id_seq'::regclass);


--
-- Name: ark_karttakoko id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttakoko ALTER COLUMN id SET DEFAULT nextval('public.ark_karttakoko_id_seq'::regclass);


--
-- Name: ark_karttatyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttatyyppi ALTER COLUMN id SET DEFAULT nextval('public.ark_karttatyyppi_id_seq'::regclass);


--
-- Name: ark_kohde id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_id_seq'::regclass);


--
-- Name: ark_kohde_ajoitus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_ajoitus ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_ajoitus_id_seq'::regclass);


--
-- Name: ark_kohde_alakohde id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_alakohde ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_alakohde_id_seq'::regclass);


--
-- Name: ark_kohde_kiinteistorakennus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kiinteistorakennus ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_kiinteistorakennus_id_seq'::regclass);


--
-- Name: ark_kohde_kuntakyla id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kuntakyla ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_kuntakyla_id_seq'::regclass);


--
-- Name: ark_kohde_mjrtutkimus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_mjrtutkimus ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_mjrtutkimus_id_seq'::regclass);


--
-- Name: ark_kohde_osoite id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_osoite ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_osoite_id_seq'::regclass);


--
-- Name: ark_kohde_projekti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_projekti ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_projekti_id_seq'::regclass);


--
-- Name: ark_kohde_rekisterilinkki id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_rekisterilinkki ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_rekisterilinkki_id_seq'::regclass);


--
-- Name: ark_kohde_sijainti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_sijainti ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_sijainti_id_seq'::regclass);


--
-- Name: ark_kohde_suojelutiedot id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_suojelutiedot ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_suojelutiedot_id_seq'::regclass);


--
-- Name: ark_kohde_tutkimus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tutkimus ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_tutkimus_id_seq'::regclass);


--
-- Name: ark_kohde_tyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tyyppi ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_tyyppi_id_seq'::regclass);


--
-- Name: ark_kohde_vanhakunta id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_vanhakunta ALTER COLUMN id SET DEFAULT nextval('public.ark_kohde_vanhakunta_id_seq'::regclass);


--
-- Name: ark_kohdelaji id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdelaji ALTER COLUMN id SET DEFAULT nextval('public.ark_kohdelaji_id_seq'::regclass);


--
-- Name: ark_kohdetyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppi ALTER COLUMN id SET DEFAULT nextval('public.ark_kohdetyyppi_id_seq'::regclass);


--
-- Name: ark_kohdetyyppitarkenne id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppitarkenne ALTER COLUMN id SET DEFAULT nextval('public.ark_kohdetyyppitarkenne_id_seq'::regclass);


--
-- Name: ark_kokoelmalaji id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kokoelmalaji ALTER COLUMN id SET DEFAULT nextval('public.ark_kokoelmalaji_id_seq'::regclass);


--
-- Name: ark_kons_kasittely id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittely ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_kasittely_id_seq'::regclass);


--
-- Name: ark_kons_kasittelytapahtumat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittelytapahtumat ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_kasittelytapahtumat_id_seq'::regclass);


--
-- Name: ark_kons_loyto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_loyto ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_loyto_id_seq'::regclass);


--
-- Name: ark_kons_materiaali id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_materiaali ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_materiaali_id_seq'::regclass);


--
-- Name: ark_kons_menetelma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_menetelma ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_menetelma_id_seq'::regclass);


--
-- Name: ark_kons_nayte id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_nayte ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_nayte_id_seq'::regclass);


--
-- Name: ark_kons_toimenpide id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_toimenpide_id_seq'::regclass);


--
-- Name: ark_kons_toimenpide_materiaalit id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_materiaalit ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_toimenpide_materiaalit_id_seq'::regclass);


--
-- Name: ark_kons_toimenpide_menetelma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_menetelma ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_toimenpide_menetelma_id_seq'::regclass);


--
-- Name: ark_kons_toimenpiteet id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet ALTER COLUMN id SET DEFAULT nextval('public.ark_kons_toimenpiteet_id_seq'::regclass);


--
-- Name: ark_konservointivaihe id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_konservointivaihe ALTER COLUMN id SET DEFAULT nextval('public.ark_konservointivaihe_id_seq'::regclass);


--
-- Name: ark_kuva id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva ALTER COLUMN id SET DEFAULT nextval('public.ark_kuva_id_seq'::regclass);


--
-- Name: ark_kuva_asiasana id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_asiasana ALTER COLUMN id SET DEFAULT nextval('public.ark_kuva_asiasana_id_seq'::regclass);


--
-- Name: ark_loyto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_id_seq'::regclass);


--
-- Name: ark_loyto_asiasanat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_asiasanat ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_asiasanat_id_seq'::regclass);


--
-- Name: ark_loyto_ensisijaiset_materiaalit id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_ensisijaiset_materiaalit ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_ensisijaiset_materiaalit_id_seq'::regclass);


--
-- Name: ark_loyto_luettelonrohistoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_luettelonrohistoria ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_luettelonrohistoria_id_seq'::regclass);


--
-- Name: ark_loyto_materiaali id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaali ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_materiaali_id_seq'::regclass);


--
-- Name: ark_loyto_materiaalikoodi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalikoodi ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_materiaalikoodi_id_seq'::regclass);


--
-- Name: ark_loyto_materiaalit id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalit ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_materiaalit_id_seq'::regclass);


--
-- Name: ark_loyto_merkinnat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinnat ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_merkinnat_id_seq'::regclass);


--
-- Name: ark_loyto_merkinta id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinta ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_merkinta_id_seq'::regclass);


--
-- Name: ark_loyto_tapahtuma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtuma ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_tapahtuma_id_seq'::regclass);


--
-- Name: ark_loyto_tapahtumat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtumat ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_tapahtumat_id_seq'::regclass);


--
-- Name: ark_loyto_tila id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_tila_id_seq'::regclass);


--
-- Name: ark_loyto_tila_tapahtuma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila_tapahtuma ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_tila_tapahtuma_id_seq'::regclass);


--
-- Name: ark_loyto_tyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_tyyppi_id_seq'::regclass);


--
-- Name: ark_loyto_tyyppi_tarkenne id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenne ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_tyyppi_tarkenne_id_seq'::regclass);


--
-- Name: ark_loyto_tyyppi_tarkenteet id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenteet ALTER COLUMN id SET DEFAULT nextval('public.ark_loyto_tyyppi_tarkenteet_id_seq'::regclass);


--
-- Name: ark_mittakaava id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_mittakaava ALTER COLUMN id SET DEFAULT nextval('public.ark_mittakaava_id_seq'::regclass);


--
-- Name: ark_nayte id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte ALTER COLUMN id SET DEFAULT nextval('public.ark_nayte_id_seq'::regclass);


--
-- Name: ark_nayte_talteenottotapa id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_talteenottotapa ALTER COLUMN id SET DEFAULT nextval('public.ark_nayte_talteenottotapa_id_seq'::regclass);


--
-- Name: ark_nayte_tapahtuma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtuma ALTER COLUMN id SET DEFAULT nextval('public.ark_nayte_tapahtuma_id_seq'::regclass);


--
-- Name: ark_nayte_tapahtumat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtumat ALTER COLUMN id SET DEFAULT nextval('public.ark_nayte_tapahtumat_id_seq'::regclass);


--
-- Name: ark_nayte_tila id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila ALTER COLUMN id SET DEFAULT nextval('public.ark_nayte_tila_id_seq'::regclass);


--
-- Name: ark_nayte_tila_tapahtuma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila_tapahtuma ALTER COLUMN id SET DEFAULT nextval('public.ark_nayte_tila_tapahtuma_id_seq'::regclass);


--
-- Name: ark_naytekoodi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytekoodi ALTER COLUMN id SET DEFAULT nextval('public.ark_naytekoodi_id_seq'::regclass);


--
-- Name: ark_naytetyypit id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyypit ALTER COLUMN id SET DEFAULT nextval('public.ark_naytetyypit_id_seq'::regclass);


--
-- Name: ark_naytetyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyyppi ALTER COLUMN id SET DEFAULT nextval('public.ark_naytetyyppi_id_seq'::regclass);


--
-- Name: ark_rontgenkuva id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva ALTER COLUMN id SET DEFAULT nextval('public.ark_rontgenkuva_id_seq'::regclass);


--
-- Name: ark_sailytystila id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_sailytystila ALTER COLUMN id SET DEFAULT nextval('public.ark_sailytystila_id_seq'::regclass);


--
-- Name: ark_tarkastus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tarkastus ALTER COLUMN id SET DEFAULT nextval('public.ark_tarkastus_id_seq'::regclass);


--
-- Name: ark_tiedosto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto ALTER COLUMN id SET DEFAULT nextval('public.ark_tiedosto_id_seq'::regclass);


--
-- Name: ark_tuhoutumissyy id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tuhoutumissyy ALTER COLUMN id SET DEFAULT nextval('public.ark_tuhoutumissyy_id_seq'::regclass);


--
-- Name: ark_tutkimus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimus_id_seq1'::regclass);


--
-- Name: ark_tutkimus_kayttaja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kayttaja ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimus_kayttaja_id_seq'::regclass);


--
-- Name: ark_tutkimus_kiinteistorakennus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kiinteistorakennus ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimus_kiinteistorakennus_id_seq'::regclass);


--
-- Name: ark_tutkimus_kuntakyla id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kuntakyla ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimus_kuntakyla_id_seq'::regclass);


--
-- Name: ark_tutkimus_osoite id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_osoite ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimus_osoite_id_seq'::regclass);


--
-- Name: ark_tutkimusalue id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimusalue_id_seq'::regclass);


--
-- Name: ark_tutkimusalue_yksikko id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimusalue_yksikko_id_seq'::regclass);


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko_tyovaihe ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimusalue_yksikko_tyovaihe_id_seq'::regclass);


--
-- Name: ark_tutkimuslaji id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimuslaji ALTER COLUMN id SET DEFAULT nextval('public.ark_tutkimus_id_seq'::regclass);


--
-- Name: arvoalue id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue ALTER COLUMN id SET DEFAULT nextval('public.arvoalue_id_seq'::regclass);


--
-- Name: arvoalue_suojelutyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_suojelutyyppi ALTER COLUMN id SET DEFAULT nextval('public.arvoalue_suojelutyyppi_id_seq'::regclass);


--
-- Name: arvoaluekulttuurihistoriallinenarvo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoaluekulttuurihistoriallinenarvo ALTER COLUMN id SET DEFAULT nextval('public.arvoaluekulttuurihistoriallinenarvo_id_seq'::regclass);


--
-- Name: arvotustyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvotustyyppi ALTER COLUMN id SET DEFAULT nextval('public.arvotustyyppi_id_seq'::regclass);


--
-- Name: asiasana id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasana ALTER COLUMN id SET DEFAULT nextval('public.asiasana_id_seq'::regclass);


--
-- Name: asiasanasto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasanasto ALTER COLUMN id SET DEFAULT nextval('public.asiasanasto_id_seq'::regclass);


--
-- Name: hoitotarve id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hoitotarve ALTER COLUMN id SET DEFAULT nextval('public.hoitotarve_id_seq'::regclass);


--
-- Name: inventointijulkaisu id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu ALTER COLUMN id SET DEFAULT nextval('public.inventointijulkaisu_id_seq'::regclass);


--
-- Name: inventointijulkaisu_inventointiprojekti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_inventointiprojekti ALTER COLUMN id SET DEFAULT nextval('public.inventointijulkaisu_inventointiprojekti_id_seq'::regclass);


--
-- Name: inventointijulkaisu_kuntakyla id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_kuntakyla ALTER COLUMN id SET DEFAULT nextval('public.inventointijulkaisu_kuntakyla_id_seq'::regclass);


--
-- Name: inventointijulkaisu_taso id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_taso ALTER COLUMN id SET DEFAULT nextval('public.inventointijulkaisu_taso_id_seq'::regclass);


--
-- Name: inventointiprojekti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojekti_id_seq'::regclass);


--
-- Name: inventointiprojekti_ajanjakso id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_ajanjakso ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojekti_ajanjakso_id_seq'::regclass);


--
-- Name: inventointiprojekti_alue id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_alue ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojekti_alue_id_seq'::regclass);


--
-- Name: inventointiprojekti_arvoalue id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_arvoalue ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojekti_arvoalue_id_seq'::regclass);


--
-- Name: inventointiprojekti_inventoija id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_inventoija ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojekti_inventoija_id_seq'::regclass);


--
-- Name: inventointiprojekti_kiinteisto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_kiinteisto ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojekti_kiinteisto_id_seq'::regclass);


--
-- Name: inventointiprojekti_laji id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_laji ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojekti_laji_id_seq'::regclass);


--
-- Name: inventointiprojektityyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojektityyppi ALTER COLUMN id SET DEFAULT nextval('public.inventointiprojektityyppi_id_seq'::regclass);


--
-- Name: jarjestelma_roolit id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jarjestelma_roolit ALTER COLUMN id SET DEFAULT nextval('public.jarjestelma_roolit_id_seq'::regclass);


--
-- Name: julkaisu id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.julkaisu ALTER COLUMN id SET DEFAULT nextval('public.julkaisu_id_seq'::regclass);


--
-- Name: katetyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.katetyyppi ALTER COLUMN id SET DEFAULT nextval('public.katetyyppi_id_seq'::regclass);


--
-- Name: kattotyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kattotyyppi ALTER COLUMN id SET DEFAULT nextval('public.kattotyyppi_id_seq'::regclass);


--
-- Name: kayttaja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kayttaja ALTER COLUMN id SET DEFAULT nextval('public.kayttaja_id_seq'::regclass);


--
-- Name: kayttotarkoitus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kayttotarkoitus ALTER COLUMN id SET DEFAULT nextval('public.kayttotarkoitus_id_seq'::regclass);


--
-- Name: keramiikkatyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.keramiikkatyyppi ALTER COLUMN id SET DEFAULT nextval('public.keramiikkatyyppi_id_seq'::regclass);


--
-- Name: kiinteisto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto ALTER COLUMN id SET DEFAULT nextval('public.kiinteisto_id_seq'::regclass);


--
-- Name: kiinteisto_aluetyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_aluetyyppi ALTER COLUMN id SET DEFAULT nextval('public.kiinteisto_aluetyyppi_id_seq'::regclass);


--
-- Name: kiinteisto_historiallinen_tilatyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_historiallinen_tilatyyppi ALTER COLUMN id SET DEFAULT nextval('public.kiinteisto_historiallinen_tilatyyppi_id_seq'::regclass);


--
-- Name: kiinteisto_suojelutyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_suojelutyyppi ALTER COLUMN id SET DEFAULT nextval('public.kiinteisto_suojelutyyppi_id_seq'::regclass);


--
-- Name: kiinteistokulttuurihistoriallinenarvo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteistokulttuurihistoriallinenarvo ALTER COLUMN id SET DEFAULT nextval('public.kiinteistokulttuurihistoriallinenarvo_id_seq'::regclass);


--
-- Name: kokoelma id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kokoelma ALTER COLUMN id SET DEFAULT nextval('public.kokoelma_id_seq'::regclass);


--
-- Name: kori id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kori ALTER COLUMN id SET DEFAULT nextval('public.kori_id_seq'::regclass);


--
-- Name: korityyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.korityyppi ALTER COLUMN id SET DEFAULT nextval('public.korityyppi_id_seq'::regclass);


--
-- Name: kunta id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunta ALTER COLUMN id SET DEFAULT nextval('public.kunta_id_seq'::regclass);


--
-- Name: kunto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunto ALTER COLUMN id SET DEFAULT nextval('public.kunto_id_seq'::regclass);


--
-- Name: kuntotyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuntotyyppi ALTER COLUMN id SET DEFAULT nextval('public.kuntotyyppi_id_seq'::regclass);


--
-- Name: kuva id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva ALTER COLUMN id SET DEFAULT nextval('public.kuva_id_seq'::regclass);


--
-- Name: kyla id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kyla ALTER COLUMN id SET DEFAULT nextval('public.kyla_id_seq'::regclass);


--
-- Name: laatu id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.laatu ALTER COLUMN id SET DEFAULT nextval('public.laatu_id_seq'::regclass);


--
-- Name: liite_tyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liite_tyyppi ALTER COLUMN id SET DEFAULT nextval('public.liite_tyyppi_id_seq'::regclass);


--
-- Name: logged_actions event_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logged_actions ALTER COLUMN event_id SET DEFAULT nextval('public.logged_actions_event_id_seq'::regclass);


--
-- Name: loyto_kategoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_kategoria ALTER COLUMN id SET DEFAULT nextval('public.loyto_kategoria_id_seq'::regclass);


--
-- Name: loyto_tyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_tyyppi ALTER COLUMN id SET DEFAULT nextval('public.loyto_tyyppi_id_seq'::regclass);


--
-- Name: maastomerkinta id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maastomerkinta ALTER COLUMN id SET DEFAULT nextval('public.maastomerkinta_id_seq'::regclass);


--
-- Name: materiaali id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.materiaali ALTER COLUMN id SET DEFAULT nextval('public.materiaali_id_seq'::regclass);


--
-- Name: matkaraportinsyy id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportinsyy ALTER COLUMN id SET DEFAULT nextval('public.matkaraportinsyy_id_seq'::regclass);


--
-- Name: matkaraportti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti ALTER COLUMN id SET DEFAULT nextval('public.matkaraportti_id_seq'::regclass);


--
-- Name: matkaraportti_syy id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti_syy ALTER COLUMN id SET DEFAULT nextval('public.matkaraportti_syy_id_seq'::regclass);


--
-- Name: museo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.museo ALTER COLUMN id SET DEFAULT nextval('public.museo_id_seq'::regclass);


--
-- Name: muutoshistoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.muutoshistoria ALTER COLUMN id SET DEFAULT nextval('public.muutoshistoria_id_seq'::regclass);


--
-- Name: nayttely id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nayttely ALTER COLUMN id SET DEFAULT nextval('public.nayttely_id_seq'::regclass);


--
-- Name: perustustyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perustustyyppi ALTER COLUMN id SET DEFAULT nextval('public.perustustyyppi_id_seq'::regclass);


--
-- Name: porrashuone id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuone ALTER COLUMN id SET DEFAULT nextval('public.porrashuone_id_seq'::regclass);


--
-- Name: porrashuonetyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuonetyyppi ALTER COLUMN id SET DEFAULT nextval('public.porrashuonetyyppi_id_seq'::regclass);


--
-- Name: projekti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti ALTER COLUMN id SET DEFAULT nextval('public.projekti_id_seq'::regclass);


--
-- Name: projekti_sijainti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_sijainti ALTER COLUMN id SET DEFAULT nextval('public.projekti_sijainti_id_seq'::regclass);


--
-- Name: rajaustarkkuus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rajaustarkkuus ALTER COLUMN id SET DEFAULT nextval('public.rajaustarkkuus_id_seq'::regclass);


--
-- Name: rakennus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus ALTER COLUMN id SET DEFAULT nextval('public.rakennus_id_seq'::regclass);


--
-- Name: rakennus_alkuperainenkaytto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_alkuperainenkaytto ALTER COLUMN id SET DEFAULT nextval('public.rakennus_alkuperainenkaytto_id_seq'::regclass);


--
-- Name: rakennus_katetyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_katetyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_katetyyppi_id_seq'::regclass);


--
-- Name: rakennus_kattotyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_kattotyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_kattotyyppi_id_seq'::regclass);


--
-- Name: rakennus_muutosvuosi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_muutosvuosi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_muutosvuosi_id_seq'::regclass);


--
-- Name: rakennus_nykykaytto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_nykykaytto ALTER COLUMN id SET DEFAULT nextval('public.rakennus_nykykaytto_id_seq'::regclass);


--
-- Name: rakennus_omistaja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_omistaja ALTER COLUMN id SET DEFAULT nextval('public.rakennus_omistaja_id_seq'::regclass);


--
-- Name: rakennus_osoite id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_osoite ALTER COLUMN id SET DEFAULT nextval('public.rakennus_osoite_id_seq'::regclass);


--
-- Name: rakennus_perustustyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_perustustyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_perustustyyppi_id_seq'::regclass);


--
-- Name: rakennus_rakennustyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennustyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_rakennustyyppi_id_seq'::regclass);


--
-- Name: rakennus_runkotyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_runkotyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_runkotyyppi_id_seq'::regclass);


--
-- Name: rakennus_suojelutyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_suojelutyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_suojelutyyppi_id_seq'::regclass);


--
-- Name: rakennus_vuoraustyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_vuoraustyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennus_vuoraustyyppi_id_seq'::regclass);


--
-- Name: rakennuskulttuurihistoriallinenarvo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennuskulttuurihistoriallinenarvo ALTER COLUMN id SET DEFAULT nextval('public.rakennuskulttuurihistoriallinenarvo_id_seq'::regclass);


--
-- Name: rakennustyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennustyyppi ALTER COLUMN id SET DEFAULT nextval('public.rakennustyyppi_id_seq'::regclass);


--
-- Name: rakentaja_vanha id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakentaja_vanha ALTER COLUMN id SET DEFAULT nextval('public.rakentaja_id_seq'::regclass);


--
-- Name: rauhoitusluokka id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rauhoitusluokka ALTER COLUMN id SET DEFAULT nextval('public.rauhoitusluokka_id_seq'::regclass);


--
-- Name: runkotyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.runkotyyppi ALTER COLUMN id SET DEFAULT nextval('public.runkotyyppi_id_seq'::regclass);


--
-- Name: suunnittelija id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija ALTER COLUMN id SET DEFAULT nextval('public.suunnittelija_id_seq1'::regclass);


--
-- Name: suunnittelija_ammattiarvo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_ammattiarvo ALTER COLUMN id SET DEFAULT nextval('public.suunnittelija_ammattiarvo_id_seq'::regclass);


--
-- Name: suunnittelija_laji id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_laji ALTER COLUMN id SET DEFAULT nextval('public.suunnittelija_laji_id_seq'::regclass);


--
-- Name: suunnittelija_porrashuone id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone ALTER COLUMN id SET DEFAULT nextval('public.suunnittelija_porrashuone_id_seq'::regclass);


--
-- Name: suunnittelija_rakennus id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus ALTER COLUMN id SET DEFAULT nextval('public.suunnittelija_rakennus_id_seq'::regclass);


--
-- Name: suunnittelija_tyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_tyyppi ALTER COLUMN id SET DEFAULT nextval('public.suunnittelija_tyyppi_id_seq'::regclass);


--
-- Name: suunnittelija_vanha id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_vanha ALTER COLUMN id SET DEFAULT nextval('public.suunnittelija_id_seq'::regclass);


--
-- Name: talteenottotapa id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talteenottotapa ALTER COLUMN id SET DEFAULT nextval('public.talteenottotapa_id_seq'::regclass);


--
-- Name: tekijanoikeuslauseke id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tekijanoikeuslauseke ALTER COLUMN id SET DEFAULT nextval('public.tekijanoikeuslauseke_id_seq'::regclass);


--
-- Name: tiedosto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto ALTER COLUMN id SET DEFAULT nextval('public.tiedosto_id_seq'::regclass);


--
-- Name: tilatyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tilatyyppi ALTER COLUMN id SET DEFAULT nextval('public.tilatyyppi_id_seq'::regclass);


--
-- Name: tyylisuunta id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tyylisuunta ALTER COLUMN id SET DEFAULT nextval('public.tyylisuunta_id_seq'::regclass);


--
-- Name: valinnat id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.valinnat ALTER COLUMN id SET DEFAULT nextval('public.valinnat_id_seq'::regclass);


--
-- Name: varasto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.varasto ALTER COLUMN id SET DEFAULT nextval('public.varasto_id_seq'::regclass);


--
-- Name: vuoraustyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vuoraustyyppi ALTER COLUMN id SET DEFAULT nextval('public.vuoraustyyppi_id_seq'::regclass);


--
-- Name: wms_rajapinta id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wms_rajapinta ALTER COLUMN id SET DEFAULT nextval('public.wms_rajapinta_id_seq'::regclass);


--
-- Name: yksikko id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko ALTER COLUMN id SET DEFAULT nextval('public.yksikko_id_seq'::regclass);


--
-- Name: yksikko_asiasana id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_asiasana ALTER COLUMN id SET DEFAULT nextval('public.yksikko_asiasana_id_seq'::regclass);


--
-- Name: yksikko_kaivaustapa id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_kaivaustapa ALTER COLUMN id SET DEFAULT nextval('public.yksikko_kaivaustapa_id_seq'::regclass);


--
-- Name: yksikko_maalaji id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_maalaji ALTER COLUMN id SET DEFAULT nextval('public.yksikko_maalaji_id_seq'::regclass);


--
-- Name: yksikko_muut_maalajit id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_muut_maalajit ALTER COLUMN id SET DEFAULT nextval('public.yksikko_muut_maalajit_id_seq'::regclass);


--
-- Name: yksikko_paasekoitteet id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_paasekoitteet ALTER COLUMN id SET DEFAULT nextval('public.yksikko_paasekoitteet_id_seq'::regclass);


--
-- Name: yksikko_seulontatapa id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_seulontatapa ALTER COLUMN id SET DEFAULT nextval('public.yksikko_seulontatapa_id_seq'::regclass);


--
-- Name: yksikko_sijainti id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_sijainti ALTER COLUMN id SET DEFAULT nextval('public.yksikko_sijainti_id_seq'::regclass);


--
-- Name: yksikko_talteenottotapa id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_talteenottotapa ALTER COLUMN id SET DEFAULT nextval('public.yksikko_talteenottotapa_id_seq'::regclass);


--
-- Name: yksikko_tyyppi id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_tyyppi ALTER COLUMN id SET DEFAULT nextval('public.yksikko_tyyppi_id_seq'::regclass);


--
-- Name: yksikon_elinkaari id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikon_elinkaari ALTER COLUMN id SET DEFAULT nextval('public.yksikon_elinkaari_id_seq'::regclass);


--
-- Name: ajoitus ajoitus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitus
    ADD CONSTRAINT ajoitus_pkey PRIMARY KEY (id);


--
-- Name: ajoitustarkenne ajoitustarkenne_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitustarkenne
    ADD CONSTRAINT ajoitustarkenne_pkey PRIMARY KEY (id);


--
-- Name: alkuperaisyys alkuperaisyys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alkuperaisyys
    ADD CONSTRAINT alkuperaisyys_pkey PRIMARY KEY (id);


--
-- Name: alue_kyla alue_kyla_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alue_kyla
    ADD CONSTRAINT alue_kyla_pkey PRIMARY KEY (kyla_id, alue_id);


--
-- Name: alue alue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alue
    ADD CONSTRAINT alue_pkey PRIMARY KEY (id);


--
-- Name: aluetyyppi aluetyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aluetyyppi
    ADD CONSTRAINT aluetyyppi_pkey PRIMARY KEY (id);


--
-- Name: ark_alakohde_ajoitus ark_alakohde_ajoitus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_ajoitus
    ADD CONSTRAINT ark_alakohde_ajoitus_pkey PRIMARY KEY (id);


--
-- Name: ark_alakohde_sijainti ark_alakohde_sijainti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_sijainti
    ADD CONSTRAINT ark_alakohde_sijainti_pkey PRIMARY KEY (id);


--
-- Name: ark_kartta_asiasana ark_kartta_asiasana_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_asiasana
    ADD CONSTRAINT ark_kartta_asiasana_pkey PRIMARY KEY (id);


--
-- Name: ark_kartta ark_kartta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_pkey PRIMARY KEY (id);


--
-- Name: ark_karttakoko ark_karttakoko_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttakoko
    ADD CONSTRAINT ark_karttakoko_pkey PRIMARY KEY (id);


--
-- Name: ark_karttatyyppi ark_karttatyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttatyyppi
    ADD CONSTRAINT ark_karttatyyppi_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_ajoitus ark_kohde_ajoitus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_ajoitus
    ADD CONSTRAINT ark_kohde_ajoitus_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_alakohde ark_kohde_alakohde_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_alakohde
    ADD CONSTRAINT ark_kohde_alakohde_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_kiinteistorakennus ark_kohde_kiinteistorakennus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kiinteistorakennus
    ADD CONSTRAINT ark_kohde_kiinteistorakennus_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_kuntakyla ark_kohde_kuntakyla_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kuntakyla
    ADD CONSTRAINT ark_kohde_kuntakyla_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_mjrtutkimus ark_kohde_mjrtutkimus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_mjrtutkimus
    ADD CONSTRAINT ark_kohde_mjrtutkimus_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_osoite ark_kohde_osoite_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_osoite
    ADD CONSTRAINT ark_kohde_osoite_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde ark_kohde_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_projekti ark_kohde_projekti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_projekti
    ADD CONSTRAINT ark_kohde_projekti_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_rekisterilinkki ark_kohde_rekisterilinkki_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_rekisterilinkki
    ADD CONSTRAINT ark_kohde_rekisterilinkki_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_sijainti ark_kohde_sijainti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_sijainti
    ADD CONSTRAINT ark_kohde_sijainti_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_suojelutiedot ark_kohde_suojelutiedot_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_suojelutiedot
    ADD CONSTRAINT ark_kohde_suojelutiedot_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_tutkimus ark_kohde_tutkimus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tutkimus
    ADD CONSTRAINT ark_kohde_tutkimus_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_tyyppi ark_kohde_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tyyppi
    ADD CONSTRAINT ark_kohde_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: ark_kohde_vanhakunta ark_kohde_vanhakunta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_vanhakunta
    ADD CONSTRAINT ark_kohde_vanhakunta_pkey PRIMARY KEY (id);


--
-- Name: ark_kohdelaji ark_kohdelaji_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdelaji
    ADD CONSTRAINT ark_kohdelaji_pkey PRIMARY KEY (id);


--
-- Name: ark_kohdetyyppi ark_kohdetyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppi
    ADD CONSTRAINT ark_kohdetyyppi_pkey PRIMARY KEY (id);


--
-- Name: ark_kohdetyyppitarkenne ark_kohdetyyppitarkenne_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppitarkenne
    ADD CONSTRAINT ark_kohdetyyppitarkenne_pkey PRIMARY KEY (id);


--
-- Name: ark_kokoelmalaji ark_kokoelmalaji_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kokoelmalaji
    ADD CONSTRAINT ark_kokoelmalaji_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_kasittely ark_kons_kasittely_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittely
    ADD CONSTRAINT ark_kons_kasittely_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_kasittelytapahtumat ark_kons_kasittelytapahtumat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittelytapahtumat
    ADD CONSTRAINT ark_kons_kasittelytapahtumat_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_loyto ark_kons_loyto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_loyto
    ADD CONSTRAINT ark_kons_loyto_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_materiaali ark_kons_materiaali_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_materiaali
    ADD CONSTRAINT ark_kons_materiaali_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_menetelma ark_kons_menetelma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_menetelma
    ADD CONSTRAINT ark_kons_menetelma_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_nayte ark_kons_nayte_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_nayte
    ADD CONSTRAINT ark_kons_nayte_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_toimenpide_materiaalit ark_kons_toimenpide_materiaalit_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_materiaalit
    ADD CONSTRAINT ark_kons_toimenpide_materiaalit_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_toimenpide_menetelma ark_kons_toimenpide_menetelma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_menetelma
    ADD CONSTRAINT ark_kons_toimenpide_menetelma_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_toimenpide ark_kons_toimenpide_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide
    ADD CONSTRAINT ark_kons_toimenpide_pkey PRIMARY KEY (id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_pkey PRIMARY KEY (id);


--
-- Name: ark_konservointivaihe ark_konservointivaihe_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_konservointivaihe
    ADD CONSTRAINT ark_konservointivaihe_pkey PRIMARY KEY (id);


--
-- Name: ark_kuva_asiasana ark_kuva_asiasana_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_asiasana
    ADD CONSTRAINT ark_kuva_asiasana_pkey PRIMARY KEY (id);


--
-- Name: ark_kuva ark_kuva_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva
    ADD CONSTRAINT ark_kuva_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_asiasanat ark_loyto_asiasanat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_asiasanat
    ADD CONSTRAINT ark_loyto_asiasanat_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_ensisijaiset_materiaalit ark_loyto_ensisijaiset_materiaalit_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_ensisijaiset_materiaalit
    ADD CONSTRAINT ark_loyto_ensisijaiset_materiaalit_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_luettelonrohistoria ark_loyto_luettelonrohistoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_luettelonrohistoria
    ADD CONSTRAINT ark_loyto_luettelonrohistoria_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_materiaali ark_loyto_materiaali_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaali
    ADD CONSTRAINT ark_loyto_materiaali_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_materiaalikoodi ark_loyto_materiaalikoodi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalikoodi
    ADD CONSTRAINT ark_loyto_materiaalikoodi_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_materiaalit ark_loyto_materiaalit_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalit
    ADD CONSTRAINT ark_loyto_materiaalit_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_merkinnat ark_loyto_merkinnat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinnat
    ADD CONSTRAINT ark_loyto_merkinnat_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_merkinta ark_loyto_merkinta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinta
    ADD CONSTRAINT ark_loyto_merkinta_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto ark_loyto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_tapahtuma ark_loyto_tapahtuma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtuma
    ADD CONSTRAINT ark_loyto_tapahtuma_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_tapahtumat ark_loyto_tapahtumat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtumat
    ADD CONSTRAINT ark_loyto_tapahtumat_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_tila ark_loyto_tila_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila
    ADD CONSTRAINT ark_loyto_tila_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_tila_tapahtuma ark_loyto_tila_tapahtuma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila_tapahtuma
    ADD CONSTRAINT ark_loyto_tila_tapahtuma_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_tyyppi ark_loyto_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi
    ADD CONSTRAINT ark_loyto_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_tyyppi_tarkenne ark_loyto_tyyppi_tarkenne_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenne
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenne_pkey PRIMARY KEY (id);


--
-- Name: ark_loyto_tyyppi_tarkenteet ark_loyto_tyyppi_tarkenteet_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenteet
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenteet_pkey PRIMARY KEY (id);


--
-- Name: ark_mittakaava ark_mittakaava_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_mittakaava
    ADD CONSTRAINT ark_mittakaava_pkey PRIMARY KEY (id);


--
-- Name: ark_nayte ark_nayte_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_pkey PRIMARY KEY (id);


--
-- Name: ark_nayte_talteenottotapa ark_nayte_talteenottotapa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_talteenottotapa
    ADD CONSTRAINT ark_nayte_talteenottotapa_pkey PRIMARY KEY (id);


--
-- Name: ark_nayte_tapahtuma ark_nayte_tapahtuma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtuma
    ADD CONSTRAINT ark_nayte_tapahtuma_pkey PRIMARY KEY (id);


--
-- Name: ark_nayte_tapahtumat ark_nayte_tapahtumat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtumat
    ADD CONSTRAINT ark_nayte_tapahtumat_pkey PRIMARY KEY (id);


--
-- Name: ark_nayte_tila ark_nayte_tila_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila
    ADD CONSTRAINT ark_nayte_tila_pkey PRIMARY KEY (id);


--
-- Name: ark_nayte_tila_tapahtuma ark_nayte_tila_tapahtuma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila_tapahtuma
    ADD CONSTRAINT ark_nayte_tila_tapahtuma_pkey PRIMARY KEY (id);


--
-- Name: ark_naytekoodi ark_naytekoodi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytekoodi
    ADD CONSTRAINT ark_naytekoodi_pkey PRIMARY KEY (id);


--
-- Name: ark_naytetyypit ark_naytetyypit_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyypit
    ADD CONSTRAINT ark_naytetyypit_pkey PRIMARY KEY (id);


--
-- Name: ark_naytetyyppi ark_naytetyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyyppi
    ADD CONSTRAINT ark_naytetyyppi_pkey PRIMARY KEY (id);


--
-- Name: ark_rontgenkuva ark_rontgenkuva_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva
    ADD CONSTRAINT ark_rontgenkuva_pkey PRIMARY KEY (id);


--
-- Name: ark_sailytystila ark_sailytystila_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_sailytystila
    ADD CONSTRAINT ark_sailytystila_pkey PRIMARY KEY (id);


--
-- Name: ark_tarkastus ark_tarkastus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tarkastus
    ADD CONSTRAINT ark_tarkastus_pkey PRIMARY KEY (id);


--
-- Name: ark_tiedosto ark_tiedosto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto
    ADD CONSTRAINT ark_tiedosto_pkey PRIMARY KEY (id);


--
-- Name: ark_tuhoutumissyy ark_tuhoutumissyy_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tuhoutumissyy
    ADD CONSTRAINT ark_tuhoutumissyy_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimus_kayttaja ark_tutkimus_kayttaja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kayttaja
    ADD CONSTRAINT ark_tutkimus_kayttaja_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimus_kiinteistorakennus ark_tutkimus_kiinteistorakennus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kiinteistorakennus
    ADD CONSTRAINT ark_tutkimus_kiinteistorakennus_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimus_kuntakyla ark_tutkimus_kuntakyla_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kuntakyla
    ADD CONSTRAINT ark_tutkimus_kuntakyla_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimus_osoite ark_tutkimus_osoite_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_osoite
    ADD CONSTRAINT ark_tutkimus_osoite_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimuslaji ark_tutkimus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimuslaji
    ADD CONSTRAINT ark_tutkimus_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimus ark_tutkimus_pkey1; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_pkey1 PRIMARY KEY (id);


--
-- Name: ark_tutkimusalue ark_tutkimusalue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue
    ADD CONSTRAINT ark_tutkimusalue_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_pkey PRIMARY KEY (id);


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe ark_tutkimusalue_yksikko_tyovaihe_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko_tyovaihe
    ADD CONSTRAINT ark_tutkimusalue_yksikko_tyovaihe_pkey PRIMARY KEY (id);


--
-- Name: arvoalue arvoalue_alue_id_inventointinumero_poistettu_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue
    ADD CONSTRAINT arvoalue_alue_id_inventointinumero_poistettu_unique UNIQUE (alue_id, inventointinumero, poistettu);


--
-- Name: arvoalue_arvoaluekulttuurihistoriallinenarvo arvoalue_arvoaluekulttuurihistoriallinenarvo_arvoalue_id_kulttu; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_arvoaluekulttuurihistoriallinenarvo
    ADD CONSTRAINT arvoalue_arvoaluekulttuurihistoriallinenarvo_arvoalue_id_kulttu UNIQUE (arvoalue_id, kulttuurihistoriallinenarvo_id);


--
-- Name: arvoalue_kyla arvoalue_kyla_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_kyla
    ADD CONSTRAINT arvoalue_kyla_pkey PRIMARY KEY (arvoalue_id, kyla_id);


--
-- Name: arvoalue arvoalue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue
    ADD CONSTRAINT arvoalue_pkey PRIMARY KEY (id);


--
-- Name: arvoalue_suojelutyyppi arvoalue_suojelutyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_suojelutyyppi
    ADD CONSTRAINT arvoalue_suojelutyyppi_pkey PRIMARY KEY (id);


--
-- Name: arvoaluekulttuurihistoriallinenarvo arvoaluekulttuurihistoriallinenarvo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoaluekulttuurihistoriallinenarvo
    ADD CONSTRAINT arvoaluekulttuurihistoriallinenarvo_pkey PRIMARY KEY (id);


--
-- Name: arvotustyyppi arvotustyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvotustyyppi
    ADD CONSTRAINT arvotustyyppi_pkey PRIMARY KEY (id);


--
-- Name: asiasana asiasana_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasana
    ADD CONSTRAINT asiasana_pkey PRIMARY KEY (id);


--
-- Name: asiasanasto asiasanasto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasanasto
    ADD CONSTRAINT asiasanasto_pkey PRIMARY KEY (id);


--
-- Name: entiteetti_tyyppi entiteetti_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entiteetti_tyyppi
    ADD CONSTRAINT entiteetti_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: hoitotarve hoitotarve_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hoitotarve
    ADD CONSTRAINT hoitotarve_pkey PRIMARY KEY (id);


--
-- Name: inventointijulkaisu_inventointiprojekti inventointijulkaisu_inventointiprojekti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_inventointiprojekti
    ADD CONSTRAINT inventointijulkaisu_inventointiprojekti_pkey PRIMARY KEY (id);


--
-- Name: inventointijulkaisu_kuntakyla inventointijulkaisu_kuntakyla_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_kuntakyla
    ADD CONSTRAINT inventointijulkaisu_kuntakyla_pkey PRIMARY KEY (id);


--
-- Name: inventointijulkaisu inventointijulkaisu_nimi_poistettu_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu
    ADD CONSTRAINT inventointijulkaisu_nimi_poistettu_unique UNIQUE (nimi, poistettu);


--
-- Name: inventointijulkaisu inventointijulkaisu_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu
    ADD CONSTRAINT inventointijulkaisu_pkey PRIMARY KEY (id);


--
-- Name: inventointijulkaisu_taso inventointijulkaisu_taso_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_taso
    ADD CONSTRAINT inventointijulkaisu_taso_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojekti_ajanjakso inventointiprojekti_ajanjakso_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_ajanjakso
    ADD CONSTRAINT inventointiprojekti_ajanjakso_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojekti_alue inventointiprojekti_alue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_alue
    ADD CONSTRAINT inventointiprojekti_alue_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojekti_arvoalue inventointiprojekti_arvoalue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_arvoalue
    ADD CONSTRAINT inventointiprojekti_arvoalue_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojekti_inventoija inventointiprojekti_inventoija_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_inventoija
    ADD CONSTRAINT inventointiprojekti_inventoija_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojekti_kiinteisto inventointiprojekti_kiinteisto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_kiinteisto
    ADD CONSTRAINT inventointiprojekti_kiinteisto_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojekti_laji inventointiprojekti_laji_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_laji
    ADD CONSTRAINT inventointiprojekti_laji_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojekti inventointiprojekti_nimi_poistettu_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti
    ADD CONSTRAINT inventointiprojekti_nimi_poistettu_unique UNIQUE (nimi, poistettu);


--
-- Name: inventointiprojekti inventointiprojekti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti
    ADD CONSTRAINT inventointiprojekti_pkey PRIMARY KEY (id);


--
-- Name: inventointiprojektityyppi inventointiprojektityyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojektityyppi
    ADD CONSTRAINT inventointiprojektityyppi_pkey PRIMARY KEY (id);


--
-- Name: jarjestelma_roolit jarjestelma_roolit_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jarjestelma_roolit
    ADD CONSTRAINT jarjestelma_roolit_pkey PRIMARY KEY (id);


--
-- Name: julkaisu julkaisu_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.julkaisu
    ADD CONSTRAINT julkaisu_pkey PRIMARY KEY (id);


--
-- Name: katetyyppi katetyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.katetyyppi
    ADD CONSTRAINT katetyyppi_pkey PRIMARY KEY (id);


--
-- Name: kattotyyppi kattotyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kattotyyppi
    ADD CONSTRAINT kattotyyppi_pkey PRIMARY KEY (id);


--
-- Name: kayttaja kayttaja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kayttaja
    ADD CONSTRAINT kayttaja_pkey PRIMARY KEY (id);


--
-- Name: kayttotarkoitus kayttotarkoitus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kayttotarkoitus
    ADD CONSTRAINT kayttotarkoitus_pkey PRIMARY KEY (id);


--
-- Name: keramiikkatyyppi keramiikkatyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.keramiikkatyyppi
    ADD CONSTRAINT keramiikkatyyppi_pkey PRIMARY KEY (id);


--
-- Name: kiinteisto_aluetyyppi kiinteisto_aluetyyppi_kiinteisto_id_aluetyyppi_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_aluetyyppi
    ADD CONSTRAINT kiinteisto_aluetyyppi_kiinteisto_id_aluetyyppi_id_unique UNIQUE (kiinteisto_id, aluetyyppi_id);


--
-- Name: kiinteisto_aluetyyppi kiinteisto_aluetyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_aluetyyppi
    ADD CONSTRAINT kiinteisto_aluetyyppi_pkey PRIMARY KEY (id);


--
-- Name: kiinteisto_historiallinen_tilatyyppi kiinteisto_historiallinen_tilatyyppi_kiinteisto_id_tilatyyppi_i; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_historiallinen_tilatyyppi
    ADD CONSTRAINT kiinteisto_historiallinen_tilatyyppi_kiinteisto_id_tilatyyppi_i UNIQUE (kiinteisto_id, tilatyyppi_id);


--
-- Name: kiinteisto_historiallinen_tilatyyppi kiinteisto_historiallinen_tilatyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_historiallinen_tilatyyppi
    ADD CONSTRAINT kiinteisto_historiallinen_tilatyyppi_pkey PRIMARY KEY (id);


--
-- Name: kiinteisto_kiinteistokulttuurihistoriallinenarvo kiinteisto_kiinteistokulttuurihistoriallinenarvo_kiinteisto_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_kiinteistokulttuurihistoriallinenarvo
    ADD CONSTRAINT kiinteisto_kiinteistokulttuurihistoriallinenarvo_kiinteisto_uq UNIQUE (kiinteisto_id, kulttuurihistoriallinenarvo_id);


--
-- Name: kiinteisto kiinteisto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto
    ADD CONSTRAINT kiinteisto_pkey PRIMARY KEY (id);


--
-- Name: kiinteisto_suojelutyyppi kiinteisto_suojelutyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_suojelutyyppi
    ADD CONSTRAINT kiinteisto_suojelutyyppi_pkey PRIMARY KEY (id);


--
-- Name: kiinteistokulttuurihistoriallinenarvo kiinteistokulttuurihistoriallinenarvo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteistokulttuurihistoriallinenarvo
    ADD CONSTRAINT kiinteistokulttuurihistoriallinenarvo_pkey PRIMARY KEY (id);


--
-- Name: kokoelma kokoelma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kokoelma
    ADD CONSTRAINT kokoelma_pkey PRIMARY KEY (id);


--
-- Name: kori kori_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kori
    ADD CONSTRAINT kori_pkey PRIMARY KEY (id);


--
-- Name: korityyppi korityyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.korityyppi
    ADD CONSTRAINT korityyppi_pkey PRIMARY KEY (id);


--
-- Name: kunta kunta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunta
    ADD CONSTRAINT kunta_pkey PRIMARY KEY (id);


--
-- Name: kunto kunto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunto
    ADD CONSTRAINT kunto_pkey PRIMARY KEY (id);


--
-- Name: kuntotyyppi kuntotyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuntotyyppi
    ADD CONSTRAINT kuntotyyppi_pkey PRIMARY KEY (id);


--
-- Name: kuva_alue kuva_alue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_alue
    ADD CONSTRAINT kuva_alue_pkey PRIMARY KEY (kuva_id, alue_id);


--
-- Name: kuva_arvoalue kuva_arvoalue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_arvoalue
    ADD CONSTRAINT kuva_arvoalue_pkey PRIMARY KEY (kuva_id, arvoalue_id);


--
-- Name: kuva_kiinteisto kuva_kiinteisto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_kiinteisto
    ADD CONSTRAINT kuva_kiinteisto_pkey PRIMARY KEY (kuva_id, kiinteisto_id);


--
-- Name: kuva_kyla kuva_kyla_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_kyla
    ADD CONSTRAINT kuva_kyla_pkey PRIMARY KEY (kuva_id, kyla_id);


--
-- Name: kuva kuva_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva
    ADD CONSTRAINT kuva_pkey PRIMARY KEY (id);


--
-- Name: kuva_porrashuone kuva_porrashuone_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_porrashuone
    ADD CONSTRAINT kuva_porrashuone_pkey PRIMARY KEY (kuva_id, porrashuone_id);


--
-- Name: kuva_rakennus kuva_rakennus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_rakennus
    ADD CONSTRAINT kuva_rakennus_pkey PRIMARY KEY (kuva_id, rakennus_id);


--
-- Name: kyla kyla_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kyla
    ADD CONSTRAINT kyla_pkey PRIMARY KEY (id);


--
-- Name: laatu laatu_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.laatu
    ADD CONSTRAINT laatu_pkey PRIMARY KEY (id);


--
-- Name: liite_tyyppi liite_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liite_tyyppi
    ADD CONSTRAINT liite_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: logged_actions logged_actions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logged_actions
    ADD CONSTRAINT logged_actions_pkey PRIMARY KEY (event_id);


--
-- Name: loyto_kategoria loyto_kategoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_kategoria
    ADD CONSTRAINT loyto_kategoria_pkey PRIMARY KEY (id);


--
-- Name: loyto_tyyppi loyto_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_tyyppi
    ADD CONSTRAINT loyto_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: maastomerkinta maastomerkinta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maastomerkinta
    ADD CONSTRAINT maastomerkinta_pkey PRIMARY KEY (id);


--
-- Name: materiaali materiaali_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.materiaali
    ADD CONSTRAINT materiaali_pkey PRIMARY KEY (id);


--
-- Name: matkaraportinsyy matkaraportinsyy_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportinsyy
    ADD CONSTRAINT matkaraportinsyy_pkey PRIMARY KEY (id);


--
-- Name: matkaraportti matkaraportti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti
    ADD CONSTRAINT matkaraportti_pkey PRIMARY KEY (id);


--
-- Name: matkaraportti_syy matkaraportti_syy_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti_syy
    ADD CONSTRAINT matkaraportti_syy_pkey PRIMARY KEY (id);


--
-- Name: museo museo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.museo
    ADD CONSTRAINT museo_pkey PRIMARY KEY (id);


--
-- Name: muutoshistoria muutoshistoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.muutoshistoria
    ADD CONSTRAINT muutoshistoria_pkey PRIMARY KEY (id);


--
-- Name: nayttely nayttely_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nayttely
    ADD CONSTRAINT nayttely_pkey PRIMARY KEY (id);


--
-- Name: ocm_luokka ocm_luokka_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ocm_luokka
    ADD CONSTRAINT ocm_luokka_pkey PRIMARY KEY (id);


--
-- Name: perustustyyppi perustustyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perustustyyppi
    ADD CONSTRAINT perustustyyppi_pkey PRIMARY KEY (id);


--
-- Name: porrashuone porrashuone_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuone
    ADD CONSTRAINT porrashuone_pkey PRIMARY KEY (id);


--
-- Name: porrashuonetyyppi porrashuonetyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuonetyyppi
    ADD CONSTRAINT porrashuonetyyppi_pkey PRIMARY KEY (id);


--
-- Name: projekti_kayttaja_rooli projekti_kayttaja_rooli_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_kayttaja_rooli
    ADD CONSTRAINT projekti_kayttaja_rooli_pkey PRIMARY KEY (kayttaja_id, projekti_id, projekti_rooli_id);


--
-- Name: projekti projekti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti
    ADD CONSTRAINT projekti_pkey PRIMARY KEY (id);


--
-- Name: projekti_rooli projekti_rooli_nimi_en_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_rooli
    ADD CONSTRAINT projekti_rooli_nimi_en_unique UNIQUE (nimi_en);


--
-- Name: projekti_rooli projekti_rooli_nimi_fi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_rooli
    ADD CONSTRAINT projekti_rooli_nimi_fi_unique UNIQUE (nimi_fi);


--
-- Name: projekti_rooli projekti_rooli_nimi_se_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_rooli
    ADD CONSTRAINT projekti_rooli_nimi_se_unique UNIQUE (nimi_se);


--
-- Name: projekti_rooli projekti_rooli_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_rooli
    ADD CONSTRAINT projekti_rooli_pkey PRIMARY KEY (id);


--
-- Name: projekti_sijainti projekti_sijainti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_sijainti
    ADD CONSTRAINT projekti_sijainti_pkey PRIMARY KEY (id);


--
-- Name: projekti projekti_tunnus_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti
    ADD CONSTRAINT projekti_tunnus_unique UNIQUE (tunnus);


--
-- Name: projekti_tyyppi projekti_tyyppi_nimi_en_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_tyyppi
    ADD CONSTRAINT projekti_tyyppi_nimi_en_unique UNIQUE (nimi_en);


--
-- Name: projekti_tyyppi projekti_tyyppi_nimi_fi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_tyyppi
    ADD CONSTRAINT projekti_tyyppi_nimi_fi_unique UNIQUE (nimi_fi);


--
-- Name: projekti_tyyppi projekti_tyyppi_nimi_se_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_tyyppi
    ADD CONSTRAINT projekti_tyyppi_nimi_se_unique UNIQUE (nimi_se);


--
-- Name: projekti_tyyppi projekti_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_tyyppi
    ADD CONSTRAINT projekti_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: rajaustarkkuus rajaustarkkuus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rajaustarkkuus
    ADD CONSTRAINT rajaustarkkuus_pkey PRIMARY KEY (id);


--
-- Name: rakennus_alkuperainenkaytto rakennus_alkuperainenkaytto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_alkuperainenkaytto
    ADD CONSTRAINT rakennus_alkuperainenkaytto_pkey PRIMARY KEY (id);


--
-- Name: rakennus_alkuperainenkaytto rakennus_alkuperainenkaytto_rakennus_id_kayttotarkoitus_id_uniq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_alkuperainenkaytto
    ADD CONSTRAINT rakennus_alkuperainenkaytto_rakennus_id_kayttotarkoitus_id_uniq UNIQUE (rakennus_id, kayttotarkoitus_id);


--
-- Name: rakennus_katetyyppi rakennus_katetyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_katetyyppi
    ADD CONSTRAINT rakennus_katetyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_katetyyppi rakennus_katetyyppi_rakennus_id_katetyyppi_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_katetyyppi
    ADD CONSTRAINT rakennus_katetyyppi_rakennus_id_katetyyppi_id_unique UNIQUE (rakennus_id, katetyyppi_id);


--
-- Name: rakennus_kattotyyppi rakennus_kattotyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_kattotyyppi
    ADD CONSTRAINT rakennus_kattotyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_kattotyyppi rakennus_kattotyyppi_rakennus_id_kattotyyppi_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_kattotyyppi
    ADD CONSTRAINT rakennus_kattotyyppi_rakennus_id_kattotyyppi_id_unique UNIQUE (rakennus_id, kattotyyppi_id);


--
-- Name: rakennus_muutosvuosi rakennus_muutosvuosi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_muutosvuosi
    ADD CONSTRAINT rakennus_muutosvuosi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_nykykaytto rakennus_nykykaytto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_nykykaytto
    ADD CONSTRAINT rakennus_nykykaytto_pkey PRIMARY KEY (id);


--
-- Name: rakennus_nykykaytto rakennus_nykykaytto_rakennus_id_kayttotarkoitus_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_nykykaytto
    ADD CONSTRAINT rakennus_nykykaytto_rakennus_id_kayttotarkoitus_id_unique UNIQUE (rakennus_id, kayttotarkoitus_id);


--
-- Name: rakennus_omistaja rakennus_omistaja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_omistaja
    ADD CONSTRAINT rakennus_omistaja_pkey PRIMARY KEY (id);


--
-- Name: rakennus_osoite rakennus_osoite_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_osoite
    ADD CONSTRAINT rakennus_osoite_pkey PRIMARY KEY (id);


--
-- Name: rakennus_perustustyyppi rakennus_perustustyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_perustustyyppi
    ADD CONSTRAINT rakennus_perustustyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_perustustyyppi rakennus_perustustyyppi_rakennus_id_perustustyyppi_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_perustustyyppi
    ADD CONSTRAINT rakennus_perustustyyppi_rakennus_id_perustustyyppi_id_unique UNIQUE (rakennus_id, perustustyyppi_id);


--
-- Name: rakennus rakennus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus
    ADD CONSTRAINT rakennus_pkey PRIMARY KEY (id);


--
-- Name: rakennus_rakennuskulttuurihistoriallinenarvo rakennus_rakennuskulttuurihistoriallinenarvo_rakennus_id_kulttu; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennuskulttuurihistoriallinenarvo
    ADD CONSTRAINT rakennus_rakennuskulttuurihistoriallinenarvo_rakennus_id_kulttu UNIQUE (rakennus_id, kulttuurihistoriallinenarvo_id);


--
-- Name: rakennus_rakennustyyppi rakennus_rakennustyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennustyyppi
    ADD CONSTRAINT rakennus_rakennustyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_rakennustyyppi rakennus_rakennustyyppi_rakennus_id_rakennustyyppi_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennustyyppi
    ADD CONSTRAINT rakennus_rakennustyyppi_rakennus_id_rakennustyyppi_id_unique UNIQUE (rakennus_id, rakennustyyppi_id);


--
-- Name: rakennus_runkotyyppi rakennus_runkotyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_runkotyyppi
    ADD CONSTRAINT rakennus_runkotyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_runkotyyppi rakennus_runkotyyppi_rakennus_id_runkotyyppi_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_runkotyyppi
    ADD CONSTRAINT rakennus_runkotyyppi_rakennus_id_runkotyyppi_id_unique UNIQUE (rakennus_id, runkotyyppi_id);


--
-- Name: rakennus_suojelutyyppi rakennus_suojelutyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_suojelutyyppi
    ADD CONSTRAINT rakennus_suojelutyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_vuoraustyyppi rakennus_vuoraustyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_vuoraustyyppi
    ADD CONSTRAINT rakennus_vuoraustyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakennus_vuoraustyyppi rakennus_vuoraustyyppi_rakennus_id_vuoraustyyppi_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_vuoraustyyppi
    ADD CONSTRAINT rakennus_vuoraustyyppi_rakennus_id_vuoraustyyppi_id_unique UNIQUE (rakennus_id, vuoraustyyppi_id);


--
-- Name: rakennuskulttuurihistoriallinenarvo rakennuskulttuurihistoriallinenarvo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennuskulttuurihistoriallinenarvo
    ADD CONSTRAINT rakennuskulttuurihistoriallinenarvo_pkey PRIMARY KEY (id);


--
-- Name: rakennustyyppi rakennustyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennustyyppi
    ADD CONSTRAINT rakennustyyppi_pkey PRIMARY KEY (id);


--
-- Name: rakentaja_vanha rakentaja_nimi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakentaja_vanha
    ADD CONSTRAINT rakentaja_nimi_unique UNIQUE (nimi);


--
-- Name: rakentaja_vanha rakentaja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakentaja_vanha
    ADD CONSTRAINT rakentaja_pkey PRIMARY KEY (id);


--
-- Name: rauhoitusluokka rauhoitusluokka_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rauhoitusluokka
    ADD CONSTRAINT rauhoitusluokka_pkey PRIMARY KEY (id);


--
-- Name: runkotyyppi runkotyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.runkotyyppi
    ADD CONSTRAINT runkotyyppi_pkey PRIMARY KEY (id);


--
-- Name: suojelumerkinta_kohde suojelumerkinta_kohde_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suojelumerkinta_kohde
    ADD CONSTRAINT suojelumerkinta_kohde_pkey PRIMARY KEY (kohde);


--
-- Name: suojelutyyppi suojelutyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suojelutyyppi
    ADD CONSTRAINT suojelutyyppi_pkey PRIMARY KEY (id);


--
-- Name: suojelutyyppi_ryhma suojelutyyppi_ryhma_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suojelutyyppi_ryhma
    ADD CONSTRAINT suojelutyyppi_ryhma_pkey PRIMARY KEY (id);


--
-- Name: suunnittelija_ammattiarvo suunnittelija_ammattiarvo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_ammattiarvo
    ADD CONSTRAINT suunnittelija_ammattiarvo_pkey PRIMARY KEY (id);


--
-- Name: suunnittelija_laji suunnittelija_laji_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_laji
    ADD CONSTRAINT suunnittelija_laji_pkey PRIMARY KEY (id);


--
-- Name: suunnittelija_vanha suunnittelija_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_vanha
    ADD CONSTRAINT suunnittelija_pkey PRIMARY KEY (id);


--
-- Name: suunnittelija suunnittelija_pkey1; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija
    ADD CONSTRAINT suunnittelija_pkey1 PRIMARY KEY (id);


--
-- Name: suunnittelija_porrashuone suunnittelija_porrashuone_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone
    ADD CONSTRAINT suunnittelija_porrashuone_pkey PRIMARY KEY (id);


--
-- Name: suunnittelija_rakennus suunnittelija_rakennus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus
    ADD CONSTRAINT suunnittelija_rakennus_pkey PRIMARY KEY (id);


--
-- Name: suunnittelija_tyyppi suunnittelija_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_tyyppi
    ADD CONSTRAINT suunnittelija_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: talteenottotapa talteenottotapa_nimi_en_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talteenottotapa
    ADD CONSTRAINT talteenottotapa_nimi_en_unique UNIQUE (nimi_en);


--
-- Name: talteenottotapa talteenottotapa_nimi_fi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talteenottotapa
    ADD CONSTRAINT talteenottotapa_nimi_fi_unique UNIQUE (nimi_fi);


--
-- Name: talteenottotapa talteenottotapa_nimi_se_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talteenottotapa
    ADD CONSTRAINT talteenottotapa_nimi_se_unique UNIQUE (nimi_se);


--
-- Name: talteenottotapa talteenottotapa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talteenottotapa
    ADD CONSTRAINT talteenottotapa_pkey PRIMARY KEY (id);


--
-- Name: tekijanoikeuslauseke tekijanoikeuslauseke_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tekijanoikeuslauseke
    ADD CONSTRAINT tekijanoikeuslauseke_pkey PRIMARY KEY (id);


--
-- Name: tiedosto_alue tiedosto_alue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_alue
    ADD CONSTRAINT tiedosto_alue_pkey PRIMARY KEY (tiedosto_id, alue_id);


--
-- Name: tiedosto_arvoalue tiedosto_arvoalue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_arvoalue
    ADD CONSTRAINT tiedosto_arvoalue_pkey PRIMARY KEY (tiedosto_id, arvoalue_id);


--
-- Name: tiedosto_kiinteisto tiedosto_kiinteisto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_kiinteisto
    ADD CONSTRAINT tiedosto_kiinteisto_pkey PRIMARY KEY (tiedosto_id, kiinteisto_id);


--
-- Name: tiedosto_kunta tiedosto_kunta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_kunta
    ADD CONSTRAINT tiedosto_kunta_pkey PRIMARY KEY (tiedosto_id, kunta_id);


--
-- Name: tiedosto tiedosto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto
    ADD CONSTRAINT tiedosto_pkey PRIMARY KEY (id);


--
-- Name: tiedosto_porrashuone tiedosto_porrashuone_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_porrashuone
    ADD CONSTRAINT tiedosto_porrashuone_pkey PRIMARY KEY (tiedosto_id, porrashuone_id);


--
-- Name: tiedosto_rakennus tiedosto_rakennus_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_rakennus
    ADD CONSTRAINT tiedosto_rakennus_pkey PRIMARY KEY (tiedosto_id, rakennus_id);


--
-- Name: tiedosto_suunnittelija tiedosto_suunnittelija_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_suunnittelija
    ADD CONSTRAINT tiedosto_suunnittelija_pkey PRIMARY KEY (tiedosto_id, suunnittelija_id);


--
-- Name: tilatyyppi tilatyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tilatyyppi
    ADD CONSTRAINT tilatyyppi_pkey PRIMARY KEY (id);


--
-- Name: tyylisuunta tyylisuunta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tyylisuunta
    ADD CONSTRAINT tyylisuunta_pkey PRIMARY KEY (id);


--
-- Name: valinnat valinnat_category_value_fi_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.valinnat
    ADD CONSTRAINT valinnat_category_value_fi_key UNIQUE (kategoria, arvo_fi);


--
-- Name: valinnat valinnat_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.valinnat
    ADD CONSTRAINT valinnat_pkey PRIMARY KEY (id);


--
-- Name: varasto varasto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.varasto
    ADD CONSTRAINT varasto_pkey PRIMARY KEY (id);


--
-- Name: vuoraustyyppi vuoraustyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vuoraustyyppi
    ADD CONSTRAINT vuoraustyyppi_pkey PRIMARY KEY (id);


--
-- Name: wms_rajapinta wms_rajapinta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wms_rajapinta
    ADD CONSTRAINT wms_rajapinta_pkey PRIMARY KEY (id);


--
-- Name: yksikko_asiasana yksikko_asiasana_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_asiasana
    ADD CONSTRAINT yksikko_asiasana_pkey PRIMARY KEY (id);


--
-- Name: yksikko_asiasana yksikko_asiasana_yksikko_id_asiasana_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_asiasana
    ADD CONSTRAINT yksikko_asiasana_yksikko_id_asiasana_unique UNIQUE (yksikko_id, asiasana);


--
-- Name: yksikko_kaivaustapa yksikko_kaivaustapa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_kaivaustapa
    ADD CONSTRAINT yksikko_kaivaustapa_pkey PRIMARY KEY (id);


--
-- Name: yksikko_maalaji yksikko_maalaji_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_maalaji
    ADD CONSTRAINT yksikko_maalaji_pkey PRIMARY KEY (id);


--
-- Name: yksikko_muut_maalajit yksikko_muut_maalajit_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_muut_maalajit
    ADD CONSTRAINT yksikko_muut_maalajit_pkey PRIMARY KEY (id);


--
-- Name: yksikko_paasekoitteet yksikko_paasekoitteet_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_paasekoitteet
    ADD CONSTRAINT yksikko_paasekoitteet_pkey PRIMARY KEY (id);


--
-- Name: yksikko yksikko_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_pkey PRIMARY KEY (id);


--
-- Name: yksikko_seulontatapa yksikko_seulontatapa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_seulontatapa
    ADD CONSTRAINT yksikko_seulontatapa_pkey PRIMARY KEY (id);


--
-- Name: yksikko_sijainti yksikko_sijainti_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_sijainti
    ADD CONSTRAINT yksikko_sijainti_pkey PRIMARY KEY (id);


--
-- Name: yksikko_talteenottotapa yksikko_talteenottotapa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_talteenottotapa
    ADD CONSTRAINT yksikko_talteenottotapa_pkey PRIMARY KEY (id);


--
-- Name: yksikko yksikko_tunnus_projekti_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_tunnus_projekti_id_unique UNIQUE (tunnus, projekti_id);


--
-- Name: yksikko_tyyppi yksikko_tyyppi_nimi_en_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_tyyppi
    ADD CONSTRAINT yksikko_tyyppi_nimi_en_unique UNIQUE (nimi_en);


--
-- Name: yksikko_tyyppi yksikko_tyyppi_nimi_fi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_tyyppi
    ADD CONSTRAINT yksikko_tyyppi_nimi_fi_unique UNIQUE (nimi_fi);


--
-- Name: yksikko_tyyppi yksikko_tyyppi_nimi_se_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_tyyppi
    ADD CONSTRAINT yksikko_tyyppi_nimi_se_unique UNIQUE (nimi_se);


--
-- Name: yksikko_tyyppi yksikko_tyyppi_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_tyyppi
    ADD CONSTRAINT yksikko_tyyppi_pkey PRIMARY KEY (id);


--
-- Name: yksikon_elinkaari yksikon_elinkaari_nimi_en_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikon_elinkaari
    ADD CONSTRAINT yksikon_elinkaari_nimi_en_unique UNIQUE (nimi_en);


--
-- Name: yksikon_elinkaari yksikon_elinkaari_nimi_fi_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikon_elinkaari
    ADD CONSTRAINT yksikon_elinkaari_nimi_fi_unique UNIQUE (nimi_fi);


--
-- Name: yksikon_elinkaari yksikon_elinkaari_nimi_se_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikon_elinkaari
    ADD CONSTRAINT yksikon_elinkaari_nimi_se_unique UNIQUE (nimi_se);


--
-- Name: yksikon_elinkaari yksikon_elinkaari_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikon_elinkaari
    ADD CONSTRAINT yksikon_elinkaari_pkey PRIMARY KEY (id);


--
-- Name: kayttaja_salasanaresetionti_avain_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kayttaja_salasanaresetionti_avain_index ON public.kayttaja_salasanaresetointi USING btree (avain);


--
-- Name: logged_actions_action_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX logged_actions_action_idx ON public.logged_actions USING btree (action);


--
-- Name: logged_actions_action_tstamp_tx_stm_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX logged_actions_action_tstamp_tx_stm_idx ON public.logged_actions USING btree (action_tstamp_stm);


--
-- Name: logged_actions_relid_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX logged_actions_relid_idx ON public.logged_actions USING btree (relid);


--
-- Name: alue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.alue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: alue_kyla audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.alue_kyla FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: aluetyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.aluetyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_alakohde_ajoitus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_alakohde_ajoitus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_alakohde_sijainti audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_alakohde_sijainti FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kartta FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_asiasana audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kartta_asiasana FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_loyto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kartta_loyto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_nayte audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kartta_nayte FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_yksikko audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kartta_yksikko FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_ajoitus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_ajoitus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_alakohde audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_alakohde FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_kiinteistorakennus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_kiinteistorakennus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_kuntakyla audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_kuntakyla FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_mjrtutkimus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_mjrtutkimus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_osoite audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_osoite FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_rekisterilinkki audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_rekisterilinkki FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_sijainti audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_sijainti FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_suojelutiedot audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_suojelutiedot FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_tutkimus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_tutkimus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_tyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_tyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_vanhakunta audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kohde_vanhakunta FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_kasittely audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kons_kasittely FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_kasittelytapahtumat audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kons_kasittelytapahtumat FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_materiaali audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kons_materiaali FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_menetelma audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kons_menetelma FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_toimenpide audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kons_toimenpide FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_toimenpiteet audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kons_toimenpiteet FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_asiasana audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva_asiasana FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_kohde audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva_kohde FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_loyto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva_loyto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_nayte audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva_nayte FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_tutkimus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva_tutkimus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_tutkimusalue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva_tutkimusalue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_yksikko audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_kuva_yksikko FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_loyto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_asiasanat audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_loyto_asiasanat FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_ensisijaiset_materiaalit audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_loyto_ensisijaiset_materiaalit FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_materiaalit audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_loyto_materiaalit FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_merkinnat audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_loyto_merkinnat FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_tyyppi_tarkenteet audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_loyto_tyyppi_tarkenteet FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_nayte audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_nayte FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_rontgenkuva audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_rontgenkuva FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_rontgenkuva_loyto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_rontgenkuva_loyto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_rontgenkuva_nayte audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_rontgenkuva_nayte FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tarkastus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tarkastus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tiedosto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tiedosto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tiedosto_rontgenkuva audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tiedosto_rontgenkuva FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_kayttaja audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimus_kayttaja FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_kiinteistorakennus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimus_kiinteistorakennus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_kuntakyla audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimus_kuntakyla FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_osoite audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimus_osoite FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimusalue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimusalue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimusalue_yksikko audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimusalue_yksikko FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.ark_tutkimusalue_yksikko_tyovaihe FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.arvoalue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue_arvoaluekulttuurihistoriallinenarvo audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.arvoalue_arvoaluekulttuurihistoriallinenarvo FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue_kyla audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.arvoalue_kyla FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue_suojelutyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.arvoalue_suojelutyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoaluekulttuurihistoriallinenarvo audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.arvoaluekulttuurihistoriallinenarvo FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvotustyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.arvotustyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointijulkaisu audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointijulkaisu FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointijulkaisu_inventointiprojekti audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointijulkaisu_inventointiprojekti FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointijulkaisu_taso audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointijulkaisu_taso FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_ajanjakso audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti_ajanjakso FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_alue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti_alue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_arvoalue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti_arvoalue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_inventoija audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti_inventoija FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_kiinteisto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti_kiinteisto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_kunta audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti_kunta FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_laji audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojekti_laji FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojektityyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.inventointiprojektityyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: jarjestelma_roolit audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.jarjestelma_roolit FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: katetyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.katetyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kattotyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kattotyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kayttaja audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kayttaja FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kayttaja_salasanaresetointi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kayttaja_salasanaresetointi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kayttotarkoitus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kayttotarkoitus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kiinteisto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_aluetyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kiinteisto_aluetyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_historiallinen_tilatyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kiinteisto_historiallinen_tilatyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_kiinteistokulttuurihistoriallinenarvo audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kiinteisto_kiinteistokulttuurihistoriallinenarvo FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_suojelutyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kiinteisto_suojelutyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteistokulttuurihistoriallinenarvo audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kiinteistokulttuurihistoriallinenarvo FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kunta audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kunta FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuntotyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuntotyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_alue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva_alue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_arvoalue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva_arvoalue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_kiinteisto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva_kiinteisto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_kyla audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva_kyla FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_porrashuone audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva_porrashuone FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_rakennus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva_rakennus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_suunnittelija audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kuva_suunnittelija FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kyla audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.kyla FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: liite_tyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.liite_tyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: matkaraportti audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.matkaraportti FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: matkaraportti_syy audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.matkaraportti_syy FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: perustustyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.perustustyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: porrashuone audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.porrashuone FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: porrashuonetyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.porrashuonetyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_alkuperainenkaytto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_alkuperainenkaytto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_katetyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_katetyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_kattotyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_kattotyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_muutosvuosi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_muutosvuosi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_nykykaytto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_nykykaytto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_omistaja audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_omistaja FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_osoite audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_osoite FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_perustustyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_perustustyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_rakennuskulttuurihistoriallinenarvo audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_rakennuskulttuurihistoriallinenarvo FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_rakennustyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_rakennustyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_runkotyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_runkotyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_suojelutyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_suojelutyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_vuoraustyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennus_vuoraustyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennuskulttuurihistoriallinenarvo audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennuskulttuurihistoriallinenarvo FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennustyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakennustyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakentaja_vanha audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.rakentaja_vanha FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: runkotyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.runkotyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suojelutyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.suojelutyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suojelutyyppi_ryhma audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.suojelutyyppi_ryhma FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.suunnittelija FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_porrashuone_vanha audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.suunnittelija_porrashuone_vanha FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_rakennus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.suunnittelija_rakennus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_rakennus_vanha audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.suunnittelija_rakennus_vanha FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_vanha audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.suunnittelija_vanha FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tekijanoikeuslauseke audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tekijanoikeuslauseke FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_alue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto_alue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_arvoalue audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto_arvoalue FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_kiinteisto audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto_kiinteisto FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_kunta audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto_kunta FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_porrashuone audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto_porrashuone FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_rakennus audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto_rakennus FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_suunnittelija audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tiedosto_suunnittelija FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tilatyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tilatyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tyylisuunta audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.tyylisuunta FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: vuoraustyyppi audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.vuoraustyyppi FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: wms_rajapinta audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.wms_rajapinta FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: yksikko_muut_maalajit audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.yksikko_muut_maalajit FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: yksikko_paasekoitteet audit_trigger_row; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_row AFTER INSERT OR DELETE OR UPDATE ON public.yksikko_paasekoitteet FOR EACH ROW EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: alue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.alue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: alue_kyla audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.alue_kyla FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: aluetyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.aluetyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_alakohde_ajoitus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_alakohde_ajoitus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_alakohde_sijainti audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_alakohde_sijainti FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kartta FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_asiasana audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kartta_asiasana FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_loyto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kartta_loyto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_nayte audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kartta_nayte FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kartta_yksikko audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kartta_yksikko FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_ajoitus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_ajoitus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_alakohde audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_alakohde FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_kiinteistorakennus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_kiinteistorakennus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_kuntakyla audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_kuntakyla FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_mjrtutkimus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_mjrtutkimus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_osoite audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_osoite FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_rekisterilinkki audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_rekisterilinkki FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_sijainti audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_sijainti FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_suojelutiedot audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_suojelutiedot FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_tutkimus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_tutkimus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_tyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_tyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kohde_vanhakunta audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kohde_vanhakunta FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_kasittely audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kons_kasittely FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_kasittelytapahtumat audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kons_kasittelytapahtumat FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_materiaali audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kons_materiaali FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_menetelma audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kons_menetelma FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_toimenpide audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kons_toimenpide FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kons_toimenpiteet audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kons_toimenpiteet FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_asiasana audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva_asiasana FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_kohde audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva_kohde FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_loyto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva_loyto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_nayte audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva_nayte FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_tutkimus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva_tutkimus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_tutkimusalue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva_tutkimusalue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_kuva_yksikko audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_kuva_yksikko FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_loyto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_asiasanat audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_loyto_asiasanat FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_ensisijaiset_materiaalit audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_loyto_ensisijaiset_materiaalit FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_materiaalit audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_loyto_materiaalit FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_merkinnat audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_loyto_merkinnat FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_loyto_tyyppi_tarkenteet audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_loyto_tyyppi_tarkenteet FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_nayte audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_nayte FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_rontgenkuva audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_rontgenkuva FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_rontgenkuva_loyto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_rontgenkuva_loyto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_rontgenkuva_nayte audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_rontgenkuva_nayte FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tarkastus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tarkastus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tiedosto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tiedosto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tiedosto_rontgenkuva audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tiedosto_rontgenkuva FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_kayttaja audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimus_kayttaja FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_kiinteistorakennus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimus_kiinteistorakennus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_kuntakyla audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimus_kuntakyla FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimus_osoite audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimus_osoite FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimusalue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimusalue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimusalue_yksikko audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimusalue_yksikko FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.ark_tutkimusalue_yksikko_tyovaihe FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.arvoalue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue_arvoaluekulttuurihistoriallinenarvo audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.arvoalue_arvoaluekulttuurihistoriallinenarvo FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue_kyla audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.arvoalue_kyla FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoalue_suojelutyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.arvoalue_suojelutyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvoaluekulttuurihistoriallinenarvo audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.arvoaluekulttuurihistoriallinenarvo FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: arvotustyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.arvotustyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointijulkaisu audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointijulkaisu FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointijulkaisu_inventointiprojekti audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointijulkaisu_inventointiprojekti FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointijulkaisu_taso audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointijulkaisu_taso FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_ajanjakso audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti_ajanjakso FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_alue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti_alue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_arvoalue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti_arvoalue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_inventoija audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti_inventoija FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_kiinteisto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti_kiinteisto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_kunta audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti_kunta FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojekti_laji audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojekti_laji FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: inventointiprojektityyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.inventointiprojektityyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: jarjestelma_roolit audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.jarjestelma_roolit FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: katetyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.katetyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kattotyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kattotyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kayttaja audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kayttaja FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kayttaja_salasanaresetointi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kayttaja_salasanaresetointi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kayttotarkoitus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kayttotarkoitus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kiinteisto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_aluetyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kiinteisto_aluetyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_historiallinen_tilatyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kiinteisto_historiallinen_tilatyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_kiinteistokulttuurihistoriallinenarvo audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kiinteisto_kiinteistokulttuurihistoriallinenarvo FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteisto_suojelutyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kiinteisto_suojelutyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kiinteistokulttuurihistoriallinenarvo audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kiinteistokulttuurihistoriallinenarvo FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kunta audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kunta FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuntotyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuntotyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_alue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva_alue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_arvoalue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva_arvoalue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_kiinteisto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva_kiinteisto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_kyla audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva_kyla FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_porrashuone audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva_porrashuone FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_rakennus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva_rakennus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kuva_suunnittelija audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kuva_suunnittelija FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: kyla audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.kyla FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: liite_tyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.liite_tyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: matkaraportti audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.matkaraportti FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: matkaraportti_syy audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.matkaraportti_syy FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: perustustyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.perustustyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: porrashuone audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.porrashuone FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: porrashuonetyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.porrashuonetyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_alkuperainenkaytto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_alkuperainenkaytto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_katetyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_katetyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_kattotyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_kattotyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_muutosvuosi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_muutosvuosi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_nykykaytto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_nykykaytto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_omistaja audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_omistaja FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_osoite audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_osoite FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_perustustyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_perustustyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_rakennuskulttuurihistoriallinenarvo audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_rakennuskulttuurihistoriallinenarvo FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_rakennustyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_rakennustyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_runkotyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_runkotyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_suojelutyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_suojelutyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennus_vuoraustyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennus_vuoraustyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennuskulttuurihistoriallinenarvo audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennuskulttuurihistoriallinenarvo FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakennustyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakennustyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: rakentaja_vanha audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.rakentaja_vanha FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: runkotyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.runkotyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suojelutyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.suojelutyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suojelutyyppi_ryhma audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.suojelutyyppi_ryhma FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.suunnittelija FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_porrashuone_vanha audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.suunnittelija_porrashuone_vanha FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_rakennus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.suunnittelija_rakennus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_rakennus_vanha audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.suunnittelija_rakennus_vanha FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: suunnittelija_vanha audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.suunnittelija_vanha FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tekijanoikeuslauseke audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tekijanoikeuslauseke FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_alue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto_alue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_arvoalue audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto_arvoalue FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_kiinteisto audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto_kiinteisto FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_kunta audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto_kunta FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_porrashuone audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto_porrashuone FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_rakennus audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto_rakennus FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tiedosto_suunnittelija audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tiedosto_suunnittelija FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tilatyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tilatyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: tyylisuunta audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.tyylisuunta FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: vuoraustyyppi audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.vuoraustyyppi FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: wms_rajapinta audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.wms_rajapinta FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: yksikko_muut_maalajit audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.yksikko_muut_maalajit FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: yksikko_paasekoitteet audit_trigger_stm; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_trigger_stm AFTER TRUNCATE ON public.yksikko_paasekoitteet FOR EACH STATEMENT EXECUTE PROCEDURE public.if_modified_func('true');


--
-- Name: ajoitus ajoitus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitus
    ADD CONSTRAINT ajoitus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ajoitus ajoitus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitus
    ADD CONSTRAINT ajoitus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ajoitus ajoitus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitus
    ADD CONSTRAINT ajoitus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ajoitustarkenne ajoitustarkenne_ajoitus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitustarkenne
    ADD CONSTRAINT ajoitustarkenne_ajoitus_id_foreign FOREIGN KEY (ajoitus_id) REFERENCES public.ajoitus(id);


--
-- Name: ajoitustarkenne ajoitustarkenne_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitustarkenne
    ADD CONSTRAINT ajoitustarkenne_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ajoitustarkenne ajoitustarkenne_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitustarkenne
    ADD CONSTRAINT ajoitustarkenne_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ajoitustarkenne ajoitustarkenne_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajoitustarkenne
    ADD CONSTRAINT ajoitustarkenne_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: alkuperaisyys alkuperaisyys_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alkuperaisyys
    ADD CONSTRAINT alkuperaisyys_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: alkuperaisyys alkuperaisyys_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alkuperaisyys
    ADD CONSTRAINT alkuperaisyys_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: alkuperaisyys alkuperaisyys_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alkuperaisyys
    ADD CONSTRAINT alkuperaisyys_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: alue_kyla alue_kyla_alue_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alue_kyla
    ADD CONSTRAINT alue_kyla_alue_foreign FOREIGN KEY (alue_id) REFERENCES public.alue(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: alue_kyla alue_kyla_kyla_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alue_kyla
    ADD CONSTRAINT alue_kyla_kyla_foreign FOREIGN KEY (kyla_id) REFERENCES public.kyla(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: aluetyyppi aluetyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aluetyyppi
    ADD CONSTRAINT aluetyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: aluetyyppi aluetyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aluetyyppi
    ADD CONSTRAINT aluetyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_alakohde_ajoitus ark_alakohde_ajoitus_ajoitus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_ajoitus
    ADD CONSTRAINT ark_alakohde_ajoitus_ajoitus_id_foreign FOREIGN KEY (ajoitus_id) REFERENCES public.ajoitus(id);


--
-- Name: ark_alakohde_ajoitus ark_alakohde_ajoitus_ajoitustarkenne_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_ajoitus
    ADD CONSTRAINT ark_alakohde_ajoitus_ajoitustarkenne_id_foreign FOREIGN KEY (ajoitustarkenne_id) REFERENCES public.ajoitustarkenne(id);


--
-- Name: ark_alakohde_ajoitus ark_alakohde_ajoitus_ark_kohde_alakohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_ajoitus
    ADD CONSTRAINT ark_alakohde_ajoitus_ark_kohde_alakohde_id_foreign FOREIGN KEY (ark_kohde_alakohde_id) REFERENCES public.ark_kohde_alakohde(id);


--
-- Name: ark_alakohde_ajoitus ark_alakohde_ajoitus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_ajoitus
    ADD CONSTRAINT ark_alakohde_ajoitus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_alakohde_ajoitus ark_alakohde_ajoitus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_ajoitus
    ADD CONSTRAINT ark_alakohde_ajoitus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_alakohde_sijainti ark_alakohde_sijainti_ark_kohde_alakohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_sijainti
    ADD CONSTRAINT ark_alakohde_sijainti_ark_kohde_alakohde_id_foreign FOREIGN KEY (ark_kohde_alakohde_id) REFERENCES public.ark_kohde_alakohde(id);


--
-- Name: ark_alakohde_sijainti ark_alakohde_sijainti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_sijainti
    ADD CONSTRAINT ark_alakohde_sijainti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_alakohde_sijainti ark_alakohde_sijainti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_alakohde_sijainti
    ADD CONSTRAINT ark_alakohde_sijainti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta ark_kartta_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_kartta_asiasana ark_kartta_asiasana_ark_kartta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_asiasana
    ADD CONSTRAINT ark_kartta_asiasana_ark_kartta_id_foreign FOREIGN KEY (ark_kartta_id) REFERENCES public.ark_kartta(id);


--
-- Name: ark_kartta_asiasana ark_kartta_asiasana_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_asiasana
    ADD CONSTRAINT ark_kartta_asiasana_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_asiasana ark_kartta_asiasana_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_asiasana
    ADD CONSTRAINT ark_kartta_asiasana_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_asiasana ark_kartta_asiasana_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_asiasana
    ADD CONSTRAINT ark_kartta_asiasana_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta ark_kartta_koko_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_koko_foreign FOREIGN KEY (koko) REFERENCES public.ark_karttakoko(id);


--
-- Name: ark_kartta_loyto ark_kartta_loyto_ark_kartta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_loyto
    ADD CONSTRAINT ark_kartta_loyto_ark_kartta_id_foreign FOREIGN KEY (ark_kartta_id) REFERENCES public.ark_kartta(id);


--
-- Name: ark_kartta_loyto ark_kartta_loyto_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_loyto
    ADD CONSTRAINT ark_kartta_loyto_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_kartta_loyto ark_kartta_loyto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_loyto
    ADD CONSTRAINT ark_kartta_loyto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_loyto ark_kartta_loyto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_loyto
    ADD CONSTRAINT ark_kartta_loyto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_loyto ark_kartta_loyto_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_loyto
    ADD CONSTRAINT ark_kartta_loyto_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta ark_kartta_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta ark_kartta_mittakaava_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_mittakaava_foreign FOREIGN KEY (mittakaava) REFERENCES public.ark_mittakaava(id);


--
-- Name: ark_kartta ark_kartta_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_nayte ark_kartta_nayte_ark_kartta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_nayte
    ADD CONSTRAINT ark_kartta_nayte_ark_kartta_id_foreign FOREIGN KEY (ark_kartta_id) REFERENCES public.ark_kartta(id);


--
-- Name: ark_kartta_nayte ark_kartta_nayte_ark_nayte_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_nayte
    ADD CONSTRAINT ark_kartta_nayte_ark_nayte_id_foreign FOREIGN KEY (ark_nayte_id) REFERENCES public.ark_nayte(id);


--
-- Name: ark_kartta_nayte ark_kartta_nayte_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_nayte
    ADD CONSTRAINT ark_kartta_nayte_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_nayte ark_kartta_nayte_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_nayte
    ADD CONSTRAINT ark_kartta_nayte_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_nayte ark_kartta_nayte_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_nayte
    ADD CONSTRAINT ark_kartta_nayte_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta ark_kartta_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta ark_kartta_tyyppi_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta
    ADD CONSTRAINT ark_kartta_tyyppi_foreign FOREIGN KEY (tyyppi) REFERENCES public.ark_karttatyyppi(id);


--
-- Name: ark_kartta_yksikko ark_kartta_yksikko_ark_kartta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_yksikko
    ADD CONSTRAINT ark_kartta_yksikko_ark_kartta_id_foreign FOREIGN KEY (ark_kartta_id) REFERENCES public.ark_kartta(id);


--
-- Name: ark_kartta_yksikko ark_kartta_yksikko_ark_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_yksikko
    ADD CONSTRAINT ark_kartta_yksikko_ark_yksikko_id_foreign FOREIGN KEY (ark_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: ark_kartta_yksikko ark_kartta_yksikko_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_yksikko
    ADD CONSTRAINT ark_kartta_yksikko_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_yksikko ark_kartta_yksikko_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_yksikko
    ADD CONSTRAINT ark_kartta_yksikko_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kartta_yksikko ark_kartta_yksikko_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kartta_yksikko
    ADD CONSTRAINT ark_kartta_yksikko_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_karttakoko ark_karttakoko_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttakoko
    ADD CONSTRAINT ark_karttakoko_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_karttakoko ark_karttakoko_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttakoko
    ADD CONSTRAINT ark_karttakoko_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_karttakoko ark_karttakoko_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttakoko
    ADD CONSTRAINT ark_karttakoko_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_karttatyyppi ark_karttatyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttatyyppi
    ADD CONSTRAINT ark_karttatyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_karttatyyppi ark_karttatyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttatyyppi
    ADD CONSTRAINT ark_karttatyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_karttatyyppi ark_karttatyyppi_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_karttatyyppi
    ADD CONSTRAINT ark_karttatyyppi_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_ajoitus ark_kohde_ajoitus_ajoitus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_ajoitus
    ADD CONSTRAINT ark_kohde_ajoitus_ajoitus_id_foreign FOREIGN KEY (ajoitus_id) REFERENCES public.ajoitus(id);


--
-- Name: ark_kohde_ajoitus ark_kohde_ajoitus_ajoitustarkenne_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_ajoitus
    ADD CONSTRAINT ark_kohde_ajoitus_ajoitustarkenne_id_foreign FOREIGN KEY (ajoitustarkenne_id) REFERENCES public.ajoitustarkenne(id);


--
-- Name: ark_kohde_ajoitus ark_kohde_ajoitus_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_ajoitus
    ADD CONSTRAINT ark_kohde_ajoitus_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_ajoitus ark_kohde_ajoitus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_ajoitus
    ADD CONSTRAINT ark_kohde_ajoitus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_ajoitus ark_kohde_ajoitus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_ajoitus
    ADD CONSTRAINT ark_kohde_ajoitus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_alakohde ark_kohde_alakohde_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_alakohde
    ADD CONSTRAINT ark_kohde_alakohde_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_alakohde ark_kohde_alakohde_ark_kohdetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_alakohde
    ADD CONSTRAINT ark_kohde_alakohde_ark_kohdetyyppi_id_foreign FOREIGN KEY (ark_kohdetyyppi_id) REFERENCES public.ark_kohdetyyppi(id);


--
-- Name: ark_kohde_alakohde ark_kohde_alakohde_ark_kohdetyyppitarkenne_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_alakohde
    ADD CONSTRAINT ark_kohde_alakohde_ark_kohdetyyppitarkenne_id_foreign FOREIGN KEY (ark_kohdetyyppitarkenne_id) REFERENCES public.ark_kohdetyyppitarkenne(id);


--
-- Name: ark_kohde_alakohde ark_kohde_alakohde_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_alakohde
    ADD CONSTRAINT ark_kohde_alakohde_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_alakohde ark_kohde_alakohde_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_alakohde
    ADD CONSTRAINT ark_kohde_alakohde_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde ark_kohde_alkuperaisyys_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_alkuperaisyys_id_foreign FOREIGN KEY (alkuperaisyys_id) REFERENCES public.alkuperaisyys(id);


--
-- Name: ark_kohde ark_kohde_ark_kohdelaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_ark_kohdelaji_id_foreign FOREIGN KEY (ark_kohdelaji_id) REFERENCES public.ark_kohdelaji(id);


--
-- Name: ark_kohde ark_kohde_hoitotarve_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_hoitotarve_id_foreign FOREIGN KEY (hoitotarve_id) REFERENCES public.hoitotarve(id);


--
-- Name: ark_kohde_kiinteistorakennus ark_kohde_kiinteistorakennus_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kiinteistorakennus
    ADD CONSTRAINT ark_kohde_kiinteistorakennus_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_kuntakyla ark_kohde_kuntakyla_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kuntakyla
    ADD CONSTRAINT ark_kohde_kuntakyla_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_kuntakyla ark_kohde_kuntakyla_kunta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kuntakyla
    ADD CONSTRAINT ark_kohde_kuntakyla_kunta_id_foreign FOREIGN KEY (kunta_id) REFERENCES public.kunta(id);


--
-- Name: ark_kohde_kuntakyla ark_kohde_kuntakyla_kyla_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kuntakyla
    ADD CONSTRAINT ark_kohde_kuntakyla_kyla_id_foreign FOREIGN KEY (kyla_id) REFERENCES public.kyla(id);


--
-- Name: ark_kohde_kuntakyla ark_kohde_kuntakyla_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kuntakyla
    ADD CONSTRAINT ark_kohde_kuntakyla_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_kuntakyla ark_kohde_kuntakyla_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_kuntakyla
    ADD CONSTRAINT ark_kohde_kuntakyla_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde ark_kohde_kunto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_kunto_id_foreign FOREIGN KEY (kunto_id) REFERENCES public.kunto(id);


--
-- Name: ark_kohde ark_kohde_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde ark_kohde_maastomerkinta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_maastomerkinta_id_foreign FOREIGN KEY (maastomerkinta_id) REFERENCES public.maastomerkinta(id);


--
-- Name: ark_kohde_mjrtutkimus ark_kohde_mjrtutkimus_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_mjrtutkimus
    ADD CONSTRAINT ark_kohde_mjrtutkimus_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_mjrtutkimus ark_kohde_mjrtutkimus_ark_tutkimuslaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_mjrtutkimus
    ADD CONSTRAINT ark_kohde_mjrtutkimus_ark_tutkimuslaji_id_foreign FOREIGN KEY (ark_tutkimuslaji_id) REFERENCES public.ark_tutkimuslaji(id);


--
-- Name: ark_kohde_mjrtutkimus ark_kohde_mjrtutkimus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_mjrtutkimus
    ADD CONSTRAINT ark_kohde_mjrtutkimus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_mjrtutkimus ark_kohde_mjrtutkimus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_mjrtutkimus
    ADD CONSTRAINT ark_kohde_mjrtutkimus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde ark_kohde_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_osoite ark_kohde_osoite_ark_kohde_kiinteistorakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_osoite
    ADD CONSTRAINT ark_kohde_osoite_ark_kohde_kiinteistorakennus_id_foreign FOREIGN KEY (ark_kohde_kiinteistorakennus_id) REFERENCES public.ark_kohde_kiinteistorakennus(id);


--
-- Name: ark_kohde ark_kohde_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_projekti ark_kohde_projekti_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_projekti
    ADD CONSTRAINT ark_kohde_projekti_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_projekti ark_kohde_projekti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_projekti
    ADD CONSTRAINT ark_kohde_projekti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_projekti ark_kohde_projekti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_projekti
    ADD CONSTRAINT ark_kohde_projekti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_projekti ark_kohde_projekti_projekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_projekti
    ADD CONSTRAINT ark_kohde_projekti_projekti_id_foreign FOREIGN KEY (projekti_id) REFERENCES public.projekti(id);


--
-- Name: ark_kohde ark_kohde_rajaustarkkuus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_rajaustarkkuus_id_foreign FOREIGN KEY (rajaustarkkuus_id) REFERENCES public.rajaustarkkuus(id);


--
-- Name: ark_kohde ark_kohde_rauhoitusluokka_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_rauhoitusluokka_id_foreign FOREIGN KEY (rauhoitusluokka_id) REFERENCES public.rauhoitusluokka(id);


--
-- Name: ark_kohde_rekisterilinkki ark_kohde_rekisterilinkki_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_rekisterilinkki
    ADD CONSTRAINT ark_kohde_rekisterilinkki_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_rekisterilinkki ark_kohde_rekisterilinkki_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_rekisterilinkki
    ADD CONSTRAINT ark_kohde_rekisterilinkki_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_rekisterilinkki ark_kohde_rekisterilinkki_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_rekisterilinkki
    ADD CONSTRAINT ark_kohde_rekisterilinkki_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_sijainti ark_kohde_sijainti_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_sijainti
    ADD CONSTRAINT ark_kohde_sijainti_kohde_id_foreign FOREIGN KEY (kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_sijainti ark_kohde_sijainti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_sijainti
    ADD CONSTRAINT ark_kohde_sijainti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_sijainti ark_kohde_sijainti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_sijainti
    ADD CONSTRAINT ark_kohde_sijainti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_suojelutiedot ark_kohde_suojelutiedot_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_suojelutiedot
    ADD CONSTRAINT ark_kohde_suojelutiedot_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_suojelutiedot ark_kohde_suojelutiedot_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_suojelutiedot
    ADD CONSTRAINT ark_kohde_suojelutiedot_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_suojelutiedot ark_kohde_suojelutiedot_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_suojelutiedot
    ADD CONSTRAINT ark_kohde_suojelutiedot_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_suojelutiedot ark_kohde_suojelutiedot_suojelutyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_suojelutiedot
    ADD CONSTRAINT ark_kohde_suojelutiedot_suojelutyyppi_id_foreign FOREIGN KEY (suojelutyyppi_id) REFERENCES public.suojelutyyppi(id);


--
-- Name: ark_kohde ark_kohde_tuhoutumissyy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde
    ADD CONSTRAINT ark_kohde_tuhoutumissyy_id_foreign FOREIGN KEY (tuhoutumissyy_id) REFERENCES public.ark_tuhoutumissyy(id);


--
-- Name: ark_kohde_tutkimus ark_kohde_tutkimus_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tutkimus
    ADD CONSTRAINT ark_kohde_tutkimus_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_tutkimus ark_kohde_tutkimus_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tutkimus
    ADD CONSTRAINT ark_kohde_tutkimus_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_kohde_tyyppi ark_kohde_tyyppi_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tyyppi
    ADD CONSTRAINT ark_kohde_tyyppi_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_tyyppi ark_kohde_tyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tyyppi
    ADD CONSTRAINT ark_kohde_tyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_tyyppi ark_kohde_tyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tyyppi
    ADD CONSTRAINT ark_kohde_tyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_tyyppi ark_kohde_tyyppi_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tyyppi
    ADD CONSTRAINT ark_kohde_tyyppi_tyyppi_id_foreign FOREIGN KEY (tyyppi_id) REFERENCES public.ark_kohdetyyppi(id);


--
-- Name: ark_kohde_tyyppi ark_kohde_tyyppi_tyyppitarkenne_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_tyyppi
    ADD CONSTRAINT ark_kohde_tyyppi_tyyppitarkenne_id_foreign FOREIGN KEY (tyyppitarkenne_id) REFERENCES public.ark_kohdetyyppitarkenne(id);


--
-- Name: ark_kohde_vanhakunta ark_kohde_vanhakunta_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_vanhakunta
    ADD CONSTRAINT ark_kohde_vanhakunta_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kohde_vanhakunta ark_kohde_vanhakunta_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_vanhakunta
    ADD CONSTRAINT ark_kohde_vanhakunta_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohde_vanhakunta ark_kohde_vanhakunta_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohde_vanhakunta
    ADD CONSTRAINT ark_kohde_vanhakunta_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdelaji ark_kohdelaji_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdelaji
    ADD CONSTRAINT ark_kohdelaji_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdelaji ark_kohdelaji_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdelaji
    ADD CONSTRAINT ark_kohdelaji_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdelaji ark_kohdelaji_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdelaji
    ADD CONSTRAINT ark_kohdelaji_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdetyyppi ark_kohdetyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppi
    ADD CONSTRAINT ark_kohdetyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdetyyppi ark_kohdetyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppi
    ADD CONSTRAINT ark_kohdetyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdetyyppi ark_kohdetyyppi_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppi
    ADD CONSTRAINT ark_kohdetyyppi_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdetyyppitarkenne ark_kohdetyyppitarkenne_ark_kohdetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppitarkenne
    ADD CONSTRAINT ark_kohdetyyppitarkenne_ark_kohdetyyppi_id_foreign FOREIGN KEY (ark_kohdetyyppi_id) REFERENCES public.ark_kohdetyyppi(id);


--
-- Name: ark_kohdetyyppitarkenne ark_kohdetyyppitarkenne_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppitarkenne
    ADD CONSTRAINT ark_kohdetyyppitarkenne_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdetyyppitarkenne ark_kohdetyyppitarkenne_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppitarkenne
    ADD CONSTRAINT ark_kohdetyyppitarkenne_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kohdetyyppitarkenne ark_kohdetyyppitarkenne_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kohdetyyppitarkenne
    ADD CONSTRAINT ark_kohdetyyppitarkenne_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kokoelmalaji ark_kokoelmalaji_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kokoelmalaji
    ADD CONSTRAINT ark_kokoelmalaji_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kokoelmalaji ark_kokoelmalaji_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kokoelmalaji
    ADD CONSTRAINT ark_kokoelmalaji_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kokoelmalaji ark_kokoelmalaji_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kokoelmalaji
    ADD CONSTRAINT ark_kokoelmalaji_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_kasittely ark_kons_kasittely_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittely
    ADD CONSTRAINT ark_kons_kasittely_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_kasittely ark_kons_kasittely_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittely
    ADD CONSTRAINT ark_kons_kasittely_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_kasittely ark_kons_kasittely_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittely
    ADD CONSTRAINT ark_kons_kasittely_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_kasittelytapahtumat ark_kons_kasittelytapahtumat_ark_kons_kasittely_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittelytapahtumat
    ADD CONSTRAINT ark_kons_kasittelytapahtumat_ark_kons_kasittely_id_foreign FOREIGN KEY (ark_kons_kasittely_id) REFERENCES public.ark_kons_kasittely(id);


--
-- Name: ark_kons_kasittelytapahtumat ark_kons_kasittelytapahtumat_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittelytapahtumat
    ADD CONSTRAINT ark_kons_kasittelytapahtumat_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_kasittelytapahtumat ark_kons_kasittelytapahtumat_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittelytapahtumat
    ADD CONSTRAINT ark_kons_kasittelytapahtumat_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_kasittelytapahtumat ark_kons_kasittelytapahtumat_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_kasittelytapahtumat
    ADD CONSTRAINT ark_kons_kasittelytapahtumat_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_loyto ark_kons_loyto_ark_kons_kasittely_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_loyto
    ADD CONSTRAINT ark_kons_loyto_ark_kons_kasittely_id_foreign FOREIGN KEY (ark_kons_kasittely_id) REFERENCES public.ark_kons_kasittely(id);


--
-- Name: ark_kons_loyto ark_kons_loyto_ark_kons_toimenpiteet_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_loyto
    ADD CONSTRAINT ark_kons_loyto_ark_kons_toimenpiteet_id_foreign FOREIGN KEY (ark_kons_toimenpiteet_id) REFERENCES public.ark_kons_toimenpiteet(id);


--
-- Name: ark_kons_loyto ark_kons_loyto_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_loyto
    ADD CONSTRAINT ark_kons_loyto_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_kons_loyto ark_kons_loyto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_loyto
    ADD CONSTRAINT ark_kons_loyto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_materiaali ark_kons_materiaali_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_materiaali
    ADD CONSTRAINT ark_kons_materiaali_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_materiaali ark_kons_materiaali_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_materiaali
    ADD CONSTRAINT ark_kons_materiaali_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_materiaali ark_kons_materiaali_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_materiaali
    ADD CONSTRAINT ark_kons_materiaali_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_menetelma ark_kons_menetelma_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_menetelma
    ADD CONSTRAINT ark_kons_menetelma_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_menetelma ark_kons_menetelma_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_menetelma
    ADD CONSTRAINT ark_kons_menetelma_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_menetelma ark_kons_menetelma_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_menetelma
    ADD CONSTRAINT ark_kons_menetelma_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_nayte ark_kons_nayte_ark_kons_kasittely_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_nayte
    ADD CONSTRAINT ark_kons_nayte_ark_kons_kasittely_id_foreign FOREIGN KEY (ark_kons_kasittely_id) REFERENCES public.ark_kons_kasittely(id);


--
-- Name: ark_kons_nayte ark_kons_nayte_ark_kons_toimenpiteet_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_nayte
    ADD CONSTRAINT ark_kons_nayte_ark_kons_toimenpiteet_id_foreign FOREIGN KEY (ark_kons_toimenpiteet_id) REFERENCES public.ark_kons_toimenpiteet(id);


--
-- Name: ark_kons_nayte ark_kons_nayte_ark_nayte_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_nayte
    ADD CONSTRAINT ark_kons_nayte_ark_nayte_id_foreign FOREIGN KEY (ark_nayte_id) REFERENCES public.ark_nayte(id);


--
-- Name: ark_kons_nayte ark_kons_nayte_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_nayte
    ADD CONSTRAINT ark_kons_nayte_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpide ark_kons_toimenpide_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide
    ADD CONSTRAINT ark_kons_toimenpide_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpide_materiaalit ark_kons_toimenpide_materiaalit_ark_kons_materiaali_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_materiaalit
    ADD CONSTRAINT ark_kons_toimenpide_materiaalit_ark_kons_materiaali_id_foreign FOREIGN KEY (ark_kons_materiaali_id) REFERENCES public.ark_kons_materiaali(id);


--
-- Name: ark_kons_toimenpide_materiaalit ark_kons_toimenpide_materiaalit_ark_kons_toimenpiteet_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_materiaalit
    ADD CONSTRAINT ark_kons_toimenpide_materiaalit_ark_kons_toimenpiteet_id_foreig FOREIGN KEY (ark_kons_toimenpiteet_id) REFERENCES public.ark_kons_toimenpiteet(id);


--
-- Name: ark_kons_toimenpide_materiaalit ark_kons_toimenpide_materiaalit_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_materiaalit
    ADD CONSTRAINT ark_kons_toimenpide_materiaalit_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpide_menetelma ark_kons_toimenpide_menetelma_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_menetelma
    ADD CONSTRAINT ark_kons_toimenpide_menetelma_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpide_menetelma ark_kons_toimenpide_menetelma_menetelma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_menetelma
    ADD CONSTRAINT ark_kons_toimenpide_menetelma_menetelma_id_foreign FOREIGN KEY (menetelma_id) REFERENCES public.ark_kons_menetelma(id);


--
-- Name: ark_kons_toimenpide_menetelma ark_kons_toimenpide_menetelma_toimenpide_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide_menetelma
    ADD CONSTRAINT ark_kons_toimenpide_menetelma_toimenpide_id_foreign FOREIGN KEY (toimenpide_id) REFERENCES public.ark_kons_toimenpide(id);


--
-- Name: ark_kons_toimenpide ark_kons_toimenpide_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide
    ADD CONSTRAINT ark_kons_toimenpide_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpide ark_kons_toimenpide_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpide
    ADD CONSTRAINT ark_kons_toimenpide_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_ark_kons_kasittely_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_ark_kons_kasittely_id_foreign FOREIGN KEY (ark_kons_kasittely_id) REFERENCES public.ark_kons_kasittely(id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_ark_kons_menetelma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_ark_kons_menetelma_id_foreign FOREIGN KEY (ark_kons_menetelma_id) REFERENCES public.ark_kons_menetelma(id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_ark_kons_toimenpide_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_ark_kons_toimenpide_id_foreign FOREIGN KEY (ark_kons_toimenpide_id) REFERENCES public.ark_kons_toimenpide(id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kons_toimenpiteet ark_kons_toimenpiteet_tekija_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kons_toimenpiteet
    ADD CONSTRAINT ark_kons_toimenpiteet_tekija_foreign FOREIGN KEY (tekija) REFERENCES public.kayttaja(id);


--
-- Name: ark_konservointivaihe ark_konservointivaihe_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_konservointivaihe
    ADD CONSTRAINT ark_konservointivaihe_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_konservointivaihe ark_konservointivaihe_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_konservointivaihe
    ADD CONSTRAINT ark_konservointivaihe_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_konservointivaihe ark_konservointivaihe_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_konservointivaihe
    ADD CONSTRAINT ark_konservointivaihe_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva ark_kuva_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva
    ADD CONSTRAINT ark_kuva_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_kuva_asiasana ark_kuva_asiasana_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_asiasana
    ADD CONSTRAINT ark_kuva_asiasana_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_asiasana ark_kuva_asiasana_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_asiasana
    ADD CONSTRAINT ark_kuva_asiasana_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_asiasana ark_kuva_asiasana_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_asiasana
    ADD CONSTRAINT ark_kuva_asiasana_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_kohde ark_kuva_kohde_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_kohde
    ADD CONSTRAINT ark_kuva_kohde_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_kuva_kohde ark_kuva_kohde_ark_kuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_kohde
    ADD CONSTRAINT ark_kuva_kohde_ark_kuva_id_foreign FOREIGN KEY (ark_kuva_id) REFERENCES public.ark_kuva(id);


--
-- Name: ark_kuva_kohde ark_kuva_kohde_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_kohde
    ADD CONSTRAINT ark_kuva_kohde_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_kohde ark_kuva_kohde_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_kohde
    ADD CONSTRAINT ark_kuva_kohde_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_kohde ark_kuva_kohde_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_kohde
    ADD CONSTRAINT ark_kuva_kohde_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva ark_kuva_konservointivaihe_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva
    ADD CONSTRAINT ark_kuva_konservointivaihe_id_foreign FOREIGN KEY (konservointivaihe_id) REFERENCES public.ark_konservointivaihe(id);


--
-- Name: ark_kuva_loyto ark_kuva_loyto_ark_kuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_loyto
    ADD CONSTRAINT ark_kuva_loyto_ark_kuva_id_foreign FOREIGN KEY (ark_kuva_id) REFERENCES public.ark_kuva(id);


--
-- Name: ark_kuva_loyto ark_kuva_loyto_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_loyto
    ADD CONSTRAINT ark_kuva_loyto_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_kuva_loyto ark_kuva_loyto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_loyto
    ADD CONSTRAINT ark_kuva_loyto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_loyto ark_kuva_loyto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_loyto
    ADD CONSTRAINT ark_kuva_loyto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_loyto ark_kuva_loyto_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_loyto
    ADD CONSTRAINT ark_kuva_loyto_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva ark_kuva_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva
    ADD CONSTRAINT ark_kuva_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva ark_kuva_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva
    ADD CONSTRAINT ark_kuva_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_nayte ark_kuva_nayte_ark_kuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_nayte
    ADD CONSTRAINT ark_kuva_nayte_ark_kuva_id_foreign FOREIGN KEY (ark_kuva_id) REFERENCES public.ark_kuva(id);


--
-- Name: ark_kuva_nayte ark_kuva_nayte_ark_nayte_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_nayte
    ADD CONSTRAINT ark_kuva_nayte_ark_nayte_id_foreign FOREIGN KEY (ark_nayte_id) REFERENCES public.ark_nayte(id);


--
-- Name: ark_kuva_nayte ark_kuva_nayte_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_nayte
    ADD CONSTRAINT ark_kuva_nayte_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_nayte ark_kuva_nayte_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_nayte
    ADD CONSTRAINT ark_kuva_nayte_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_nayte ark_kuva_nayte_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_nayte
    ADD CONSTRAINT ark_kuva_nayte_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva ark_kuva_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva
    ADD CONSTRAINT ark_kuva_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_tutkimus ark_kuva_tutkimus_ark_kuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimus
    ADD CONSTRAINT ark_kuva_tutkimus_ark_kuva_id_foreign FOREIGN KEY (ark_kuva_id) REFERENCES public.ark_kuva(id);


--
-- Name: ark_kuva_tutkimus ark_kuva_tutkimus_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimus
    ADD CONSTRAINT ark_kuva_tutkimus_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_kuva_tutkimus ark_kuva_tutkimus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimus
    ADD CONSTRAINT ark_kuva_tutkimus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_tutkimus ark_kuva_tutkimus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimus
    ADD CONSTRAINT ark_kuva_tutkimus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_tutkimus ark_kuva_tutkimus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimus
    ADD CONSTRAINT ark_kuva_tutkimus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_tutkimusalue ark_kuva_tutkimusalue_ark_kuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimusalue
    ADD CONSTRAINT ark_kuva_tutkimusalue_ark_kuva_id_foreign FOREIGN KEY (ark_kuva_id) REFERENCES public.ark_kuva(id);


--
-- Name: ark_kuva_tutkimusalue ark_kuva_tutkimusalue_ark_tutkimusalue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimusalue
    ADD CONSTRAINT ark_kuva_tutkimusalue_ark_tutkimusalue_id_foreign FOREIGN KEY (ark_tutkimusalue_id) REFERENCES public.ark_tutkimusalue(id);


--
-- Name: ark_kuva_tutkimusalue ark_kuva_tutkimusalue_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimusalue
    ADD CONSTRAINT ark_kuva_tutkimusalue_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_tutkimusalue ark_kuva_tutkimusalue_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimusalue
    ADD CONSTRAINT ark_kuva_tutkimusalue_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_tutkimusalue ark_kuva_tutkimusalue_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_tutkimusalue
    ADD CONSTRAINT ark_kuva_tutkimusalue_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_yksikko ark_kuva_yksikko_ark_kuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_yksikko
    ADD CONSTRAINT ark_kuva_yksikko_ark_kuva_id_foreign FOREIGN KEY (ark_kuva_id) REFERENCES public.ark_kuva(id);


--
-- Name: ark_kuva_yksikko ark_kuva_yksikko_ark_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_yksikko
    ADD CONSTRAINT ark_kuva_yksikko_ark_yksikko_id_foreign FOREIGN KEY (ark_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: ark_kuva_yksikko ark_kuva_yksikko_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_yksikko
    ADD CONSTRAINT ark_kuva_yksikko_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_yksikko ark_kuva_yksikko_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_yksikko
    ADD CONSTRAINT ark_kuva_yksikko_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_kuva_yksikko ark_kuva_yksikko_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_kuva_yksikko
    ADD CONSTRAINT ark_kuva_yksikko_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto ark_loyto_ark_loyto_materiaalikoodi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_ark_loyto_materiaalikoodi_id_foreign FOREIGN KEY (ark_loyto_materiaalikoodi_id) REFERENCES public.ark_loyto_materiaalikoodi(id);


--
-- Name: ark_loyto ark_loyto_ark_loyto_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_ark_loyto_tyyppi_id_foreign FOREIGN KEY (ark_loyto_tyyppi_id) REFERENCES public.ark_loyto_tyyppi(id);


--
-- Name: ark_loyto ark_loyto_ark_tutkimusalue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_ark_tutkimusalue_id_foreign FOREIGN KEY (ark_tutkimusalue_id) REFERENCES public.ark_tutkimusalue(id);


--
-- Name: ark_loyto ark_loyto_ark_tutkimusalue_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_ark_tutkimusalue_yksikko_id_foreign FOREIGN KEY (ark_tutkimusalue_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: ark_loyto_asiasanat ark_loyto_asiasanat_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_asiasanat
    ADD CONSTRAINT ark_loyto_asiasanat_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_loyto_asiasanat ark_loyto_asiasanat_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_asiasanat
    ADD CONSTRAINT ark_loyto_asiasanat_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_asiasanat ark_loyto_asiasanat_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_asiasanat
    ADD CONSTRAINT ark_loyto_asiasanat_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_asiasanat ark_loyto_asiasanat_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_asiasanat
    ADD CONSTRAINT ark_loyto_asiasanat_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_ensisijaiset_materiaalit ark_loyto_ensisijaiset_materiaalit_ark_loyto_materiaali_id_fore; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_ensisijaiset_materiaalit
    ADD CONSTRAINT ark_loyto_ensisijaiset_materiaalit_ark_loyto_materiaali_id_fore FOREIGN KEY (ark_loyto_materiaali_id) REFERENCES public.ark_loyto_materiaali(id);


--
-- Name: ark_loyto_ensisijaiset_materiaalit ark_loyto_ensisijaiset_materiaalit_ark_loyto_materiaalikoodi_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_ensisijaiset_materiaalit
    ADD CONSTRAINT ark_loyto_ensisijaiset_materiaalit_ark_loyto_materiaalikoodi_id FOREIGN KEY (ark_loyto_materiaalikoodi_id) REFERENCES public.ark_loyto_materiaalikoodi(id);


--
-- Name: ark_loyto_ensisijaiset_materiaalit ark_loyto_ensisijaiset_materiaalit_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_ensisijaiset_materiaalit
    ADD CONSTRAINT ark_loyto_ensisijaiset_materiaalit_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_ensisijaiset_materiaalit ark_loyto_ensisijaiset_materiaalit_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_ensisijaiset_materiaalit
    ADD CONSTRAINT ark_loyto_ensisijaiset_materiaalit_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_ensisijaiset_materiaalit ark_loyto_ensisijaiset_materiaalit_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_ensisijaiset_materiaalit
    ADD CONSTRAINT ark_loyto_ensisijaiset_materiaalit_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto ark_loyto_loydon_tila_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_loydon_tila_id_foreign FOREIGN KEY (loydon_tila_id) REFERENCES public.ark_loyto_tila(id);


--
-- Name: ark_loyto_luettelonrohistoria ark_loyto_luettelonrohistoria_ark_loyto_tapahtumat_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_luettelonrohistoria
    ADD CONSTRAINT ark_loyto_luettelonrohistoria_ark_loyto_tapahtumat_id_foreign FOREIGN KEY (ark_loyto_tapahtumat_id) REFERENCES public.ark_loyto_tapahtumat(id);


--
-- Name: ark_loyto_luettelonrohistoria ark_loyto_luettelonrohistoria_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_luettelonrohistoria
    ADD CONSTRAINT ark_loyto_luettelonrohistoria_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_luettelonrohistoria ark_loyto_luettelonrohistoria_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_luettelonrohistoria
    ADD CONSTRAINT ark_loyto_luettelonrohistoria_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_luettelonrohistoria ark_loyto_luettelonrohistoria_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_luettelonrohistoria
    ADD CONSTRAINT ark_loyto_luettelonrohistoria_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto ark_loyto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaali ark_loyto_materiaali_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaali
    ADD CONSTRAINT ark_loyto_materiaali_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaali ark_loyto_materiaali_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaali
    ADD CONSTRAINT ark_loyto_materiaali_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaali ark_loyto_materiaali_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaali
    ADD CONSTRAINT ark_loyto_materiaali_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaalikoodi ark_loyto_materiaalikoodi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalikoodi
    ADD CONSTRAINT ark_loyto_materiaalikoodi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaalikoodi ark_loyto_materiaalikoodi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalikoodi
    ADD CONSTRAINT ark_loyto_materiaalikoodi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaalikoodi ark_loyto_materiaalikoodi_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalikoodi
    ADD CONSTRAINT ark_loyto_materiaalikoodi_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaalit ark_loyto_materiaalit_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalit
    ADD CONSTRAINT ark_loyto_materiaalit_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_loyto_materiaalit ark_loyto_materiaalit_ark_loyto_materiaali_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalit
    ADD CONSTRAINT ark_loyto_materiaalit_ark_loyto_materiaali_id_foreign FOREIGN KEY (ark_loyto_materiaali_id) REFERENCES public.ark_loyto_materiaali(id);


--
-- Name: ark_loyto_materiaalit ark_loyto_materiaalit_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalit
    ADD CONSTRAINT ark_loyto_materiaalit_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaalit ark_loyto_materiaalit_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalit
    ADD CONSTRAINT ark_loyto_materiaalit_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_materiaalit ark_loyto_materiaalit_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_materiaalit
    ADD CONSTRAINT ark_loyto_materiaalit_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_merkinnat ark_loyto_merkinnat_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinnat
    ADD CONSTRAINT ark_loyto_merkinnat_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_loyto_merkinnat ark_loyto_merkinnat_ark_loyto_merkinta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinnat
    ADD CONSTRAINT ark_loyto_merkinnat_ark_loyto_merkinta_id_foreign FOREIGN KEY (ark_loyto_merkinta_id) REFERENCES public.ark_loyto_merkinta(id);


--
-- Name: ark_loyto_merkinnat ark_loyto_merkinnat_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinnat
    ADD CONSTRAINT ark_loyto_merkinnat_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_merkinnat ark_loyto_merkinnat_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinnat
    ADD CONSTRAINT ark_loyto_merkinnat_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_merkinnat ark_loyto_merkinnat_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinnat
    ADD CONSTRAINT ark_loyto_merkinnat_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_merkinta ark_loyto_merkinta_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinta
    ADD CONSTRAINT ark_loyto_merkinta_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_merkinta ark_loyto_merkinta_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinta
    ADD CONSTRAINT ark_loyto_merkinta_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_merkinta ark_loyto_merkinta_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_merkinta
    ADD CONSTRAINT ark_loyto_merkinta_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto ark_loyto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto ark_loyto_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tapahtuma ark_loyto_tapahtuma_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtuma
    ADD CONSTRAINT ark_loyto_tapahtuma_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tapahtuma ark_loyto_tapahtuma_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtuma
    ADD CONSTRAINT ark_loyto_tapahtuma_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tapahtuma ark_loyto_tapahtuma_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtuma
    ADD CONSTRAINT ark_loyto_tapahtuma_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tapahtumat ark_loyto_tapahtumat_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtumat
    ADD CONSTRAINT ark_loyto_tapahtumat_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_loyto_tapahtumat ark_loyto_tapahtumat_ark_loyto_tapahtuma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtumat
    ADD CONSTRAINT ark_loyto_tapahtumat_ark_loyto_tapahtuma_id_foreign FOREIGN KEY (ark_loyto_tapahtuma_id) REFERENCES public.ark_loyto_tapahtuma(id);


--
-- Name: ark_loyto_tapahtumat ark_loyto_tapahtumat_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtumat
    ADD CONSTRAINT ark_loyto_tapahtumat_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tapahtumat ark_loyto_tapahtumat_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtumat
    ADD CONSTRAINT ark_loyto_tapahtumat_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tapahtumat ark_loyto_tapahtumat_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tapahtumat
    ADD CONSTRAINT ark_loyto_tapahtumat_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tila ark_loyto_tila_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila
    ADD CONSTRAINT ark_loyto_tila_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tila ark_loyto_tila_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila
    ADD CONSTRAINT ark_loyto_tila_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tila ark_loyto_tila_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila
    ADD CONSTRAINT ark_loyto_tila_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tila_tapahtuma ark_loyto_tila_tapahtuma_ark_loyto_tapahtuma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila_tapahtuma
    ADD CONSTRAINT ark_loyto_tila_tapahtuma_ark_loyto_tapahtuma_id_foreign FOREIGN KEY (ark_loyto_tapahtuma_id) REFERENCES public.ark_loyto_tapahtuma(id);


--
-- Name: ark_loyto_tila_tapahtuma ark_loyto_tila_tapahtuma_ark_loyto_tila_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tila_tapahtuma
    ADD CONSTRAINT ark_loyto_tila_tapahtuma_ark_loyto_tila_id_foreign FOREIGN KEY (ark_loyto_tila_id) REFERENCES public.ark_loyto_tila(id);


--
-- Name: ark_loyto_tyyppi ark_loyto_tyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi
    ADD CONSTRAINT ark_loyto_tyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi ark_loyto_tyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi
    ADD CONSTRAINT ark_loyto_tyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi ark_loyto_tyyppi_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi
    ADD CONSTRAINT ark_loyto_tyyppi_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi_tarkenne ark_loyto_tyyppi_tarkenne_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenne
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenne_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi_tarkenne ark_loyto_tyyppi_tarkenne_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenne
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenne_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi_tarkenne ark_loyto_tyyppi_tarkenne_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenne
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenne_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi_tarkenteet ark_loyto_tyyppi_tarkenteet_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenteet
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenteet_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_loyto_tyyppi_tarkenteet ark_loyto_tyyppi_tarkenteet_ark_loyto_tyyppi_tarkenne_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenteet
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenteet_ark_loyto_tyyppi_tarkenne_id_foreig FOREIGN KEY (ark_loyto_tyyppi_tarkenne_id) REFERENCES public.ark_loyto_tyyppi_tarkenne(id);


--
-- Name: ark_loyto_tyyppi_tarkenteet ark_loyto_tyyppi_tarkenteet_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenteet
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenteet_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi_tarkenteet ark_loyto_tyyppi_tarkenteet_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenteet
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenteet_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto_tyyppi_tarkenteet ark_loyto_tyyppi_tarkenteet_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto_tyyppi_tarkenteet
    ADD CONSTRAINT ark_loyto_tyyppi_tarkenteet_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_loyto ark_loyto_vakituinen_sailytystila_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_loyto
    ADD CONSTRAINT ark_loyto_vakituinen_sailytystila_id_foreign FOREIGN KEY (vakituinen_sailytystila_id) REFERENCES public.ark_sailytystila(id);


--
-- Name: ark_mittakaava ark_mittakaava_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_mittakaava
    ADD CONSTRAINT ark_mittakaava_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_mittakaava ark_mittakaava_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_mittakaava
    ADD CONSTRAINT ark_mittakaava_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_mittakaava ark_mittakaava_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_mittakaava
    ADD CONSTRAINT ark_mittakaava_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte ark_nayte_ark_nayte_tila_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_ark_nayte_tila_id_foreign FOREIGN KEY (ark_nayte_tila_id) REFERENCES public.ark_nayte_tila(id);


--
-- Name: ark_nayte ark_nayte_ark_naytekoodi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_ark_naytekoodi_id_foreign FOREIGN KEY (ark_naytekoodi_id) REFERENCES public.ark_naytekoodi(id);


--
-- Name: ark_nayte ark_nayte_ark_naytetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_ark_naytetyyppi_id_foreign FOREIGN KEY (ark_naytetyyppi_id) REFERENCES public.ark_naytetyyppi(id);


--
-- Name: ark_nayte ark_nayte_ark_talteenottotapa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_ark_talteenottotapa_id_foreign FOREIGN KEY (ark_talteenottotapa_id) REFERENCES public.ark_nayte_talteenottotapa(id);


--
-- Name: ark_nayte ark_nayte_ark_tutkimusalue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_ark_tutkimusalue_id_foreign FOREIGN KEY (ark_tutkimusalue_id) REFERENCES public.ark_tutkimusalue(id);


--
-- Name: ark_nayte ark_nayte_ark_tutkimusalue_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_ark_tutkimusalue_yksikko_id_foreign FOREIGN KEY (ark_tutkimusalue_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: ark_nayte ark_nayte_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte ark_nayte_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte ark_nayte_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_talteenottotapa ark_nayte_talteenottotapa_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_talteenottotapa
    ADD CONSTRAINT ark_nayte_talteenottotapa_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_talteenottotapa ark_nayte_talteenottotapa_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_talteenottotapa
    ADD CONSTRAINT ark_nayte_talteenottotapa_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_talteenottotapa ark_nayte_talteenottotapa_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_talteenottotapa
    ADD CONSTRAINT ark_nayte_talteenottotapa_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tapahtuma ark_nayte_tapahtuma_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtuma
    ADD CONSTRAINT ark_nayte_tapahtuma_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tapahtuma ark_nayte_tapahtuma_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtuma
    ADD CONSTRAINT ark_nayte_tapahtuma_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tapahtuma ark_nayte_tapahtuma_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtuma
    ADD CONSTRAINT ark_nayte_tapahtuma_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tapahtumat ark_nayte_tapahtumat_ark_nayte_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtumat
    ADD CONSTRAINT ark_nayte_tapahtumat_ark_nayte_id_foreign FOREIGN KEY (ark_nayte_id) REFERENCES public.ark_nayte(id);


--
-- Name: ark_nayte_tapahtumat ark_nayte_tapahtumat_ark_nayte_tapahtuma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtumat
    ADD CONSTRAINT ark_nayte_tapahtumat_ark_nayte_tapahtuma_id_foreign FOREIGN KEY (ark_nayte_tapahtuma_id) REFERENCES public.ark_nayte_tapahtuma(id);


--
-- Name: ark_nayte_tapahtumat ark_nayte_tapahtumat_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtumat
    ADD CONSTRAINT ark_nayte_tapahtumat_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tapahtumat ark_nayte_tapahtumat_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtumat
    ADD CONSTRAINT ark_nayte_tapahtumat_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tapahtumat ark_nayte_tapahtumat_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tapahtumat
    ADD CONSTRAINT ark_nayte_tapahtumat_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tila ark_nayte_tila_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila
    ADD CONSTRAINT ark_nayte_tila_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tila ark_nayte_tila_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila
    ADD CONSTRAINT ark_nayte_tila_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tila ark_nayte_tila_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila
    ADD CONSTRAINT ark_nayte_tila_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_nayte_tila_tapahtuma ark_nayte_tila_tapahtuma_ark_nayte_tapahtuma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila_tapahtuma
    ADD CONSTRAINT ark_nayte_tila_tapahtuma_ark_nayte_tapahtuma_id_foreign FOREIGN KEY (ark_nayte_tapahtuma_id) REFERENCES public.ark_nayte_tapahtuma(id);


--
-- Name: ark_nayte_tila_tapahtuma ark_nayte_tila_tapahtuma_ark_nayte_tila_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte_tila_tapahtuma
    ADD CONSTRAINT ark_nayte_tila_tapahtuma_ark_nayte_tila_id_foreign FOREIGN KEY (ark_nayte_tila_id) REFERENCES public.ark_nayte_tila(id);


--
-- Name: ark_nayte ark_nayte_vakituinen_sailytystila_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_nayte
    ADD CONSTRAINT ark_nayte_vakituinen_sailytystila_id_foreign FOREIGN KEY (vakituinen_sailytystila_id) REFERENCES public.ark_sailytystila(id);


--
-- Name: ark_naytekoodi ark_naytekoodi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytekoodi
    ADD CONSTRAINT ark_naytekoodi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytekoodi ark_naytekoodi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytekoodi
    ADD CONSTRAINT ark_naytekoodi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytekoodi ark_naytekoodi_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytekoodi
    ADD CONSTRAINT ark_naytekoodi_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytetyypit ark_naytetyypit_ark_naytekoodi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyypit
    ADD CONSTRAINT ark_naytetyypit_ark_naytekoodi_id_foreign FOREIGN KEY (ark_naytekoodi_id) REFERENCES public.ark_naytekoodi(id);


--
-- Name: ark_naytetyypit ark_naytetyypit_ark_naytetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyypit
    ADD CONSTRAINT ark_naytetyypit_ark_naytetyyppi_id_foreign FOREIGN KEY (ark_naytetyyppi_id) REFERENCES public.ark_naytetyyppi(id);


--
-- Name: ark_naytetyypit ark_naytetyypit_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyypit
    ADD CONSTRAINT ark_naytetyypit_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytetyypit ark_naytetyypit_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyypit
    ADD CONSTRAINT ark_naytetyypit_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytetyypit ark_naytetyypit_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyypit
    ADD CONSTRAINT ark_naytetyypit_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytetyyppi ark_naytetyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyyppi
    ADD CONSTRAINT ark_naytetyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytetyyppi ark_naytetyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyyppi
    ADD CONSTRAINT ark_naytetyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_naytetyyppi ark_naytetyyppi_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_naytetyyppi
    ADD CONSTRAINT ark_naytetyyppi_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva_loyto ark_rontgenkuva_loyto_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_loyto
    ADD CONSTRAINT ark_rontgenkuva_loyto_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_rontgenkuva_loyto ark_rontgenkuva_loyto_ark_rontgenkuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_loyto
    ADD CONSTRAINT ark_rontgenkuva_loyto_ark_rontgenkuva_id_foreign FOREIGN KEY (ark_rontgenkuva_id) REFERENCES public.ark_rontgenkuva(id);


--
-- Name: ark_rontgenkuva_loyto ark_rontgenkuva_loyto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_loyto
    ADD CONSTRAINT ark_rontgenkuva_loyto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva_loyto ark_rontgenkuva_loyto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_loyto
    ADD CONSTRAINT ark_rontgenkuva_loyto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva_loyto ark_rontgenkuva_loyto_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_loyto
    ADD CONSTRAINT ark_rontgenkuva_loyto_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva ark_rontgenkuva_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva
    ADD CONSTRAINT ark_rontgenkuva_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva ark_rontgenkuva_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva
    ADD CONSTRAINT ark_rontgenkuva_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva_nayte ark_rontgenkuva_nayte_ark_nayte_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_nayte
    ADD CONSTRAINT ark_rontgenkuva_nayte_ark_nayte_id_foreign FOREIGN KEY (ark_nayte_id) REFERENCES public.ark_nayte(id);


--
-- Name: ark_rontgenkuva_nayte ark_rontgenkuva_nayte_ark_rontgenkuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_nayte
    ADD CONSTRAINT ark_rontgenkuva_nayte_ark_rontgenkuva_id_foreign FOREIGN KEY (ark_rontgenkuva_id) REFERENCES public.ark_rontgenkuva(id);


--
-- Name: ark_rontgenkuva_nayte ark_rontgenkuva_nayte_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_nayte
    ADD CONSTRAINT ark_rontgenkuva_nayte_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva_nayte ark_rontgenkuva_nayte_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_nayte
    ADD CONSTRAINT ark_rontgenkuva_nayte_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva_nayte ark_rontgenkuva_nayte_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva_nayte
    ADD CONSTRAINT ark_rontgenkuva_nayte_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_rontgenkuva ark_rontgenkuva_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_rontgenkuva
    ADD CONSTRAINT ark_rontgenkuva_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_sailytystila ark_sailytystila_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_sailytystila
    ADD CONSTRAINT ark_sailytystila_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_sailytystila ark_sailytystila_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_sailytystila
    ADD CONSTRAINT ark_sailytystila_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_sailytystila ark_sailytystila_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_sailytystila
    ADD CONSTRAINT ark_sailytystila_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tarkastus ark_tarkastus_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tarkastus
    ADD CONSTRAINT ark_tarkastus_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_tarkastus ark_tarkastus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tarkastus
    ADD CONSTRAINT ark_tarkastus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tarkastus ark_tarkastus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tarkastus
    ADD CONSTRAINT ark_tarkastus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tarkastus ark_tarkastus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tarkastus
    ADD CONSTRAINT ark_tarkastus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tarkastus ark_tarkastus_tarkastaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tarkastus
    ADD CONSTRAINT ark_tarkastus_tarkastaja_foreign FOREIGN KEY (tarkastaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kohde ark_tiedosto_kohde_ark_kohde_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kohde
    ADD CONSTRAINT ark_tiedosto_kohde_ark_kohde_id_foreign FOREIGN KEY (ark_kohde_id) REFERENCES public.ark_kohde(id);


--
-- Name: ark_tiedosto_kohde ark_tiedosto_kohde_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kohde
    ADD CONSTRAINT ark_tiedosto_kohde_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_kohde ark_tiedosto_kohde_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kohde
    ADD CONSTRAINT ark_tiedosto_kohde_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kohde ark_tiedosto_kohde_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kohde
    ADD CONSTRAINT ark_tiedosto_kohde_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kohde ark_tiedosto_kohde_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kohde
    ADD CONSTRAINT ark_tiedosto_kohde_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kons_kasittely ark_tiedosto_kons_kasittely_ark_kons_kasittely_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_kasittely
    ADD CONSTRAINT ark_tiedosto_kons_kasittely_ark_kons_kasittely_id_foreign FOREIGN KEY (ark_kons_kasittely_id) REFERENCES public.ark_kons_kasittely(id);


--
-- Name: ark_tiedosto_kons_kasittely ark_tiedosto_kons_kasittely_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_kasittely
    ADD CONSTRAINT ark_tiedosto_kons_kasittely_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_kons_kasittely ark_tiedosto_kons_kasittely_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_kasittely
    ADD CONSTRAINT ark_tiedosto_kons_kasittely_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kons_kasittely ark_tiedosto_kons_kasittely_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_kasittely
    ADD CONSTRAINT ark_tiedosto_kons_kasittely_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kons_kasittely ark_tiedosto_kons_kasittely_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_kasittely
    ADD CONSTRAINT ark_tiedosto_kons_kasittely_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kons_toimenpiteet ark_tiedosto_kons_toimenpiteet_ark_kons_toimenpiteet_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_toimenpiteet
    ADD CONSTRAINT ark_tiedosto_kons_toimenpiteet_ark_kons_toimenpiteet_id_foreign FOREIGN KEY (ark_kons_toimenpiteet_id) REFERENCES public.ark_kons_toimenpiteet(id);


--
-- Name: ark_tiedosto_kons_toimenpiteet ark_tiedosto_kons_toimenpiteet_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_toimenpiteet
    ADD CONSTRAINT ark_tiedosto_kons_toimenpiteet_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_kons_toimenpiteet ark_tiedosto_kons_toimenpiteet_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_toimenpiteet
    ADD CONSTRAINT ark_tiedosto_kons_toimenpiteet_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kons_toimenpiteet ark_tiedosto_kons_toimenpiteet_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_toimenpiteet
    ADD CONSTRAINT ark_tiedosto_kons_toimenpiteet_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_kons_toimenpiteet ark_tiedosto_kons_toimenpiteet_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_kons_toimenpiteet
    ADD CONSTRAINT ark_tiedosto_kons_toimenpiteet_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_loyto ark_tiedosto_loyto_ark_loyto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_loyto
    ADD CONSTRAINT ark_tiedosto_loyto_ark_loyto_id_foreign FOREIGN KEY (ark_loyto_id) REFERENCES public.ark_loyto(id);


--
-- Name: ark_tiedosto_loyto ark_tiedosto_loyto_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_loyto
    ADD CONSTRAINT ark_tiedosto_loyto_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_loyto ark_tiedosto_loyto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_loyto
    ADD CONSTRAINT ark_tiedosto_loyto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_loyto ark_tiedosto_loyto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_loyto
    ADD CONSTRAINT ark_tiedosto_loyto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_loyto ark_tiedosto_loyto_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_loyto
    ADD CONSTRAINT ark_tiedosto_loyto_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto ark_tiedosto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto
    ADD CONSTRAINT ark_tiedosto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto ark_tiedosto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto
    ADD CONSTRAINT ark_tiedosto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_nayte ark_tiedosto_nayte_ark_nayte_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_nayte
    ADD CONSTRAINT ark_tiedosto_nayte_ark_nayte_id_foreign FOREIGN KEY (ark_nayte_id) REFERENCES public.ark_nayte(id);


--
-- Name: ark_tiedosto_nayte ark_tiedosto_nayte_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_nayte
    ADD CONSTRAINT ark_tiedosto_nayte_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_nayte ark_tiedosto_nayte_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_nayte
    ADD CONSTRAINT ark_tiedosto_nayte_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_nayte ark_tiedosto_nayte_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_nayte
    ADD CONSTRAINT ark_tiedosto_nayte_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_nayte ark_tiedosto_nayte_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_nayte
    ADD CONSTRAINT ark_tiedosto_nayte_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto ark_tiedosto_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto
    ADD CONSTRAINT ark_tiedosto_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_rontgenkuva ark_tiedosto_rontgenkuva_ark_rontgenkuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_rontgenkuva
    ADD CONSTRAINT ark_tiedosto_rontgenkuva_ark_rontgenkuva_id_foreign FOREIGN KEY (ark_rontgenkuva_id) REFERENCES public.ark_rontgenkuva(id);


--
-- Name: ark_tiedosto_rontgenkuva ark_tiedosto_rontgenkuva_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_rontgenkuva
    ADD CONSTRAINT ark_tiedosto_rontgenkuva_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_rontgenkuva ark_tiedosto_rontgenkuva_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_rontgenkuva
    ADD CONSTRAINT ark_tiedosto_rontgenkuva_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_rontgenkuva ark_tiedosto_rontgenkuva_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_rontgenkuva
    ADD CONSTRAINT ark_tiedosto_rontgenkuva_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_rontgenkuva ark_tiedosto_rontgenkuva_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_rontgenkuva
    ADD CONSTRAINT ark_tiedosto_rontgenkuva_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_tutkimus ark_tiedosto_tutkimus_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_tutkimus
    ADD CONSTRAINT ark_tiedosto_tutkimus_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_tutkimus ark_tiedosto_tutkimus_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_tutkimus
    ADD CONSTRAINT ark_tiedosto_tutkimus_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_tiedosto_tutkimus ark_tiedosto_tutkimus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_tutkimus
    ADD CONSTRAINT ark_tiedosto_tutkimus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_tutkimus ark_tiedosto_tutkimus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_tutkimus
    ADD CONSTRAINT ark_tiedosto_tutkimus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_tutkimus ark_tiedosto_tutkimus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_tutkimus
    ADD CONSTRAINT ark_tiedosto_tutkimus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_yksikko ark_tiedosto_yksikko_ark_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_yksikko
    ADD CONSTRAINT ark_tiedosto_yksikko_ark_tiedosto_id_foreign FOREIGN KEY (ark_tiedosto_id) REFERENCES public.ark_tiedosto(id);


--
-- Name: ark_tiedosto_yksikko ark_tiedosto_yksikko_ark_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_yksikko
    ADD CONSTRAINT ark_tiedosto_yksikko_ark_yksikko_id_foreign FOREIGN KEY (ark_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: ark_tiedosto_yksikko ark_tiedosto_yksikko_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_yksikko
    ADD CONSTRAINT ark_tiedosto_yksikko_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_yksikko ark_tiedosto_yksikko_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_yksikko
    ADD CONSTRAINT ark_tiedosto_yksikko_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tiedosto_yksikko ark_tiedosto_yksikko_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tiedosto_yksikko
    ADD CONSTRAINT ark_tiedosto_yksikko_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tuhoutumissyy ark_tuhoutumissyy_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tuhoutumissyy
    ADD CONSTRAINT ark_tuhoutumissyy_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tuhoutumissyy ark_tuhoutumissyy_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tuhoutumissyy
    ADD CONSTRAINT ark_tuhoutumissyy_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tuhoutumissyy ark_tuhoutumissyy_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tuhoutumissyy
    ADD CONSTRAINT ark_tuhoutumissyy_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus ark_tutkimus_ark_kartta_kokoelmalaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_ark_kartta_kokoelmalaji_id_foreign FOREIGN KEY (ark_kartta_kokoelmalaji_id) REFERENCES public.ark_kokoelmalaji(id);


--
-- Name: ark_tutkimus ark_tutkimus_ark_loyto_kokoelmalaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_ark_loyto_kokoelmalaji_id_foreign FOREIGN KEY (ark_loyto_kokoelmalaji_id) REFERENCES public.ark_kokoelmalaji(id);


--
-- Name: ark_tutkimus ark_tutkimus_ark_nayte_kokoelmalaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_ark_nayte_kokoelmalaji_id_foreign FOREIGN KEY (ark_nayte_kokoelmalaji_id) REFERENCES public.ark_kokoelmalaji(id);


--
-- Name: ark_tutkimus ark_tutkimus_ark_raportti_kokoelmalaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_ark_raportti_kokoelmalaji_id_foreign FOREIGN KEY (ark_raportti_kokoelmalaji_id) REFERENCES public.ark_kokoelmalaji(id);


--
-- Name: ark_tutkimus ark_tutkimus_ark_tutkimuslaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_ark_tutkimuslaji_id_foreign FOREIGN KEY (ark_tutkimuslaji_id) REFERENCES public.ark_tutkimuslaji(id);


--
-- Name: ark_tutkimus ark_tutkimus_ark_valokuva_kokoelmalaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_ark_valokuva_kokoelmalaji_id_foreign FOREIGN KEY (ark_valokuva_kokoelmalaji_id) REFERENCES public.ark_kokoelmalaji(id);


--
-- Name: ark_tutkimus_kayttaja ark_tutkimus_kayttaja_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kayttaja
    ADD CONSTRAINT ark_tutkimus_kayttaja_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_tutkimus_kayttaja ark_tutkimus_kayttaja_kayttaja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kayttaja
    ADD CONSTRAINT ark_tutkimus_kayttaja_kayttaja_id_foreign FOREIGN KEY (kayttaja_id) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus_kayttaja ark_tutkimus_kayttaja_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kayttaja
    ADD CONSTRAINT ark_tutkimus_kayttaja_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus_kayttaja ark_tutkimus_kayttaja_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kayttaja
    ADD CONSTRAINT ark_tutkimus_kayttaja_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus_kayttaja ark_tutkimus_kayttaja_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kayttaja
    ADD CONSTRAINT ark_tutkimus_kayttaja_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus_kiinteistorakennus ark_tutkimus_kiinteistorakennus_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kiinteistorakennus
    ADD CONSTRAINT ark_tutkimus_kiinteistorakennus_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_tutkimus_kuntakyla ark_tutkimus_kuntakyla_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kuntakyla
    ADD CONSTRAINT ark_tutkimus_kuntakyla_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_tutkimus_kuntakyla ark_tutkimus_kuntakyla_kunta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kuntakyla
    ADD CONSTRAINT ark_tutkimus_kuntakyla_kunta_id_foreign FOREIGN KEY (kunta_id) REFERENCES public.kunta(id);


--
-- Name: ark_tutkimus_kuntakyla ark_tutkimus_kuntakyla_kyla_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kuntakyla
    ADD CONSTRAINT ark_tutkimus_kuntakyla_kyla_id_foreign FOREIGN KEY (kyla_id) REFERENCES public.kyla(id);


--
-- Name: ark_tutkimus_kuntakyla ark_tutkimus_kuntakyla_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kuntakyla
    ADD CONSTRAINT ark_tutkimus_kuntakyla_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus_kuntakyla ark_tutkimus_kuntakyla_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_kuntakyla
    ADD CONSTRAINT ark_tutkimus_kuntakyla_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimuslaji ark_tutkimus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimuslaji
    ADD CONSTRAINT ark_tutkimus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus ark_tutkimus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimuslaji ark_tutkimus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimuslaji
    ADD CONSTRAINT ark_tutkimus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus ark_tutkimus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus_osoite ark_tutkimus_osoite_ark_tutkimus_kiinteistorakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus_osoite
    ADD CONSTRAINT ark_tutkimus_osoite_ark_tutkimus_kiinteistorakennus_id_foreign FOREIGN KEY (ark_tutkimus_kiinteistorakennus_id) REFERENCES public.ark_tutkimus_kiinteistorakennus(id);


--
-- Name: ark_tutkimuslaji ark_tutkimus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimuslaji
    ADD CONSTRAINT ark_tutkimus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimus ark_tutkimus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimus
    ADD CONSTRAINT ark_tutkimus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue ark_tutkimusalue_ark_tutkimus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue
    ADD CONSTRAINT ark_tutkimusalue_ark_tutkimus_id_foreign FOREIGN KEY (ark_tutkimus_id) REFERENCES public.ark_tutkimus(id);


--
-- Name: ark_tutkimusalue ark_tutkimusalue_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue
    ADD CONSTRAINT ark_tutkimusalue_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue ark_tutkimusalue_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue
    ADD CONSTRAINT ark_tutkimusalue_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue ark_tutkimusalue_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue
    ADD CONSTRAINT ark_tutkimusalue_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_ark_tutkimusalue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_ark_tutkimusalue_id_foreign FOREIGN KEY (ark_tutkimusalue_id) REFERENCES public.ark_tutkimusalue(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe ark_tutkimusalue_yksikko_tyovaihe_ark_tutkimusalue_yksikko_id_f; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko_tyovaihe
    ADD CONSTRAINT ark_tutkimusalue_yksikko_tyovaihe_ark_tutkimusalue_yksikko_id_f FOREIGN KEY (ark_tutkimusalue_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe ark_tutkimusalue_yksikko_tyovaihe_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko_tyovaihe
    ADD CONSTRAINT ark_tutkimusalue_yksikko_tyovaihe_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe ark_tutkimusalue_yksikko_tyovaihe_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko_tyovaihe
    ADD CONSTRAINT ark_tutkimusalue_yksikko_tyovaihe_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue_yksikko_tyovaihe ark_tutkimusalue_yksikko_tyovaihe_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko_tyovaihe
    ADD CONSTRAINT ark_tutkimusalue_yksikko_tyovaihe_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_yksikko_kaivaustapa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_yksikko_kaivaustapa_id_foreign FOREIGN KEY (yksikko_kaivaustapa_id) REFERENCES public.yksikko_kaivaustapa(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_yksikko_paamaalaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_yksikko_paamaalaji_id_foreign FOREIGN KEY (yksikko_paamaalaji_id) REFERENCES public.yksikko_maalaji(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_yksikko_seulontatapa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_yksikko_seulontatapa_id_foreign FOREIGN KEY (yksikko_seulontatapa_id) REFERENCES public.yksikko_seulontatapa(id);


--
-- Name: ark_tutkimusalue_yksikko ark_tutkimusalue_yksikko_yksikko_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ark_tutkimusalue_yksikko
    ADD CONSTRAINT ark_tutkimusalue_yksikko_yksikko_tyyppi_id_foreign FOREIGN KEY (yksikko_tyyppi_id) REFERENCES public.yksikko_tyyppi(id);


--
-- Name: arvoalue arvoalue_alue_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue
    ADD CONSTRAINT arvoalue_alue_foreign FOREIGN KEY (alue_id) REFERENCES public.alue(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: arvoalue arvoalue_aluetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue
    ADD CONSTRAINT arvoalue_aluetyyppi_id_foreign FOREIGN KEY (aluetyyppi_id) REFERENCES public.aluetyyppi(id);


--
-- Name: arvoalue_arvoaluekulttuurihistoriallinenarvo arvoalue_arvoaluekulttuurihistoriallinenarvo_arvoalue_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_arvoaluekulttuurihistoriallinenarvo
    ADD CONSTRAINT arvoalue_arvoaluekulttuurihistoriallinenarvo_arvoalue_id_foreig FOREIGN KEY (arvoalue_id) REFERENCES public.arvoalue(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: arvoalue_arvoaluekulttuurihistoriallinenarvo arvoalue_arvoaluekulttuurihistoriallinenarvo_kulttuurihistorial; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_arvoaluekulttuurihistoriallinenarvo
    ADD CONSTRAINT arvoalue_arvoaluekulttuurihistoriallinenarvo_kulttuurihistorial FOREIGN KEY (kulttuurihistoriallinenarvo_id) REFERENCES public.arvoaluekulttuurihistoriallinenarvo(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: arvoalue arvoalue_arvotustyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue
    ADD CONSTRAINT arvoalue_arvotustyyppi_id_foreign FOREIGN KEY (arvotustyyppi_id) REFERENCES public.arvotustyyppi(id);


--
-- Name: arvoalue_kyla arvoalue_kyla_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_kyla
    ADD CONSTRAINT arvoalue_kyla_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: arvoalue_kyla arvoalue_kyla_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_kyla
    ADD CONSTRAINT arvoalue_kyla_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: arvoalue_suojelutyyppi arvoalue_suojelutyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_suojelutyyppi
    ADD CONSTRAINT arvoalue_suojelutyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: arvoalue_suojelutyyppi arvoalue_suojelutyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvoalue_suojelutyyppi
    ADD CONSTRAINT arvoalue_suojelutyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: arvotustyyppi arvotustyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvotustyyppi
    ADD CONSTRAINT arvotustyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: arvotustyyppi arvotustyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arvotustyyppi
    ADD CONSTRAINT arvotustyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: asiasana asiasana_asiasanasto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasana
    ADD CONSTRAINT asiasana_asiasanasto_id_foreign FOREIGN KEY (asiasanasto_id) REFERENCES public.asiasanasto(id);


--
-- Name: asiasana asiasana_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasana
    ADD CONSTRAINT asiasana_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: asiasana asiasana_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasana
    ADD CONSTRAINT asiasana_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: asiasanasto asiasanasto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasanasto
    ADD CONSTRAINT asiasanasto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: asiasanasto asiasanasto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiasanasto
    ADD CONSTRAINT asiasanasto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: hoitotarve hoitotarve_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hoitotarve
    ADD CONSTRAINT hoitotarve_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: hoitotarve hoitotarve_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hoitotarve
    ADD CONSTRAINT hoitotarve_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: hoitotarve hoitotarve_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hoitotarve
    ADD CONSTRAINT hoitotarve_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_inventointiprojekti inventointijulkaisu_inventointiprojekti_inventointijulkaisu_id_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_inventointiprojekti
    ADD CONSTRAINT inventointijulkaisu_inventointiprojekti_inventointijulkaisu_id_ FOREIGN KEY (inventointijulkaisu_id) REFERENCES public.inventointijulkaisu(id);


--
-- Name: inventointijulkaisu_inventointiprojekti inventointijulkaisu_inventointiprojekti_inventointiprojekti_id_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_inventointiprojekti
    ADD CONSTRAINT inventointijulkaisu_inventointiprojekti_inventointiprojekti_id_ FOREIGN KEY (inventointiprojekti_id) REFERENCES public.inventointiprojekti(id);


--
-- Name: inventointijulkaisu_inventointiprojekti inventointijulkaisu_inventointiprojekti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_inventointiprojekti
    ADD CONSTRAINT inventointijulkaisu_inventointiprojekti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_inventointiprojekti inventointijulkaisu_inventointiprojekti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_inventointiprojekti
    ADD CONSTRAINT inventointijulkaisu_inventointiprojekti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_inventointiprojekti inventointijulkaisu_inventointiprojekti_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_inventointiprojekti
    ADD CONSTRAINT inventointijulkaisu_inventointiprojekti_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_kuntakyla inventointijulkaisu_kuntakyla_inventointijulkaisu_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_kuntakyla
    ADD CONSTRAINT inventointijulkaisu_kuntakyla_inventointijulkaisu_id_foreign FOREIGN KEY (inventointijulkaisu_id) REFERENCES public.inventointijulkaisu(id);


--
-- Name: inventointijulkaisu_kuntakyla inventointijulkaisu_kuntakyla_kunta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_kuntakyla
    ADD CONSTRAINT inventointijulkaisu_kuntakyla_kunta_id_foreign FOREIGN KEY (kunta_id) REFERENCES public.kunta(id);


--
-- Name: inventointijulkaisu_kuntakyla inventointijulkaisu_kuntakyla_kyla_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_kuntakyla
    ADD CONSTRAINT inventointijulkaisu_kuntakyla_kyla_id_foreign FOREIGN KEY (kyla_id) REFERENCES public.kyla(id);


--
-- Name: inventointijulkaisu_kuntakyla inventointijulkaisu_kuntakyla_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_kuntakyla
    ADD CONSTRAINT inventointijulkaisu_kuntakyla_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_kuntakyla inventointijulkaisu_kuntakyla_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_kuntakyla
    ADD CONSTRAINT inventointijulkaisu_kuntakyla_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu inventointijulkaisu_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu
    ADD CONSTRAINT inventointijulkaisu_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu inventointijulkaisu_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu
    ADD CONSTRAINT inventointijulkaisu_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu inventointijulkaisu_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu
    ADD CONSTRAINT inventointijulkaisu_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_taso inventointijulkaisu_taso_entiteetti_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_taso
    ADD CONSTRAINT inventointijulkaisu_taso_entiteetti_tyyppi_id_foreign FOREIGN KEY (entiteetti_tyyppi_id) REFERENCES public.entiteetti_tyyppi(id);


--
-- Name: inventointijulkaisu_taso inventointijulkaisu_taso_inventointijulkaisu_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_taso
    ADD CONSTRAINT inventointijulkaisu_taso_inventointijulkaisu_id_foreign FOREIGN KEY (inventointijulkaisu_id) REFERENCES public.inventointijulkaisu(id);


--
-- Name: inventointijulkaisu_taso inventointijulkaisu_taso_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_taso
    ADD CONSTRAINT inventointijulkaisu_taso_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_taso inventointijulkaisu_taso_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_taso
    ADD CONSTRAINT inventointijulkaisu_taso_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointijulkaisu_taso inventointijulkaisu_taso_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointijulkaisu_taso
    ADD CONSTRAINT inventointijulkaisu_taso_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojekti_ajanjakso inventointiprojekti_ajanjakso_inventointiprojekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_ajanjakso
    ADD CONSTRAINT inventointiprojekti_ajanjakso_inventointiprojekti_id_foreign FOREIGN KEY (inventointiprojekti_id) REFERENCES public.inventointiprojekti(id) ON DELETE CASCADE;


--
-- Name: inventointiprojekti_ajanjakso inventointiprojekti_ajanjakso_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_ajanjakso
    ADD CONSTRAINT inventointiprojekti_ajanjakso_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojekti_ajanjakso inventointiprojekti_ajanjakso_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_ajanjakso
    ADD CONSTRAINT inventointiprojekti_ajanjakso_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojekti_ajanjakso inventointiprojekti_ajanjakso_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_ajanjakso
    ADD CONSTRAINT inventointiprojekti_ajanjakso_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojekti_alue inventointiprojekti_alue_alue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_alue
    ADD CONSTRAINT inventointiprojekti_alue_alue_id_foreign FOREIGN KEY (alue_id) REFERENCES public.alue(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_alue inventointiprojekti_alue_inventoija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_alue
    ADD CONSTRAINT inventointiprojekti_alue_inventoija_id_foreign FOREIGN KEY (inventoija_id) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_alue inventointiprojekti_alue_inventointiprojekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_alue
    ADD CONSTRAINT inventointiprojekti_alue_inventointiprojekti_id_foreign FOREIGN KEY (inventointiprojekti_id) REFERENCES public.inventointiprojekti(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_arvoalue inventointiprojekti_arvoalue_arvoalue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_arvoalue
    ADD CONSTRAINT inventointiprojekti_arvoalue_arvoalue_id_foreign FOREIGN KEY (arvoalue_id) REFERENCES public.arvoalue(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_arvoalue inventointiprojekti_arvoalue_inventoija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_arvoalue
    ADD CONSTRAINT inventointiprojekti_arvoalue_inventoija_id_foreign FOREIGN KEY (inventoija_id) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_arvoalue inventointiprojekti_arvoalue_inventointiprojekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_arvoalue
    ADD CONSTRAINT inventointiprojekti_arvoalue_inventointiprojekti_id_foreign FOREIGN KEY (inventointiprojekti_id) REFERENCES public.inventointiprojekti(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti inventointiprojekti_id_luonti_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti
    ADD CONSTRAINT inventointiprojekti_id_luonti_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti inventointiprojekti_id_muokkaus_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti
    ADD CONSTRAINT inventointiprojekti_id_muokkaus_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti inventointiprojekti_id_poisto_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti
    ADD CONSTRAINT inventointiprojekti_id_poisto_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_inventoija inventointiprojekti_inventoija_inventoija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_inventoija
    ADD CONSTRAINT inventointiprojekti_inventoija_inventoija_id_foreign FOREIGN KEY (inventoija_id) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_inventoija inventointiprojekti_inventoija_inventointiprojekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_inventoija
    ADD CONSTRAINT inventointiprojekti_inventoija_inventointiprojekti_id_foreign FOREIGN KEY (inventointiprojekti_id) REFERENCES public.inventointiprojekti(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_kiinteisto inventointiprojekti_kiinteisto_inventoija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_kiinteisto
    ADD CONSTRAINT inventointiprojekti_kiinteisto_inventoija_id_foreign FOREIGN KEY (inventoija_id) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_kiinteisto inventointiprojekti_kiinteisto_inventointiprojekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_kiinteisto
    ADD CONSTRAINT inventointiprojekti_kiinteisto_inventointiprojekti_id_foreign FOREIGN KEY (inventointiprojekti_id) REFERENCES public.inventointiprojekti(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_kiinteisto inventointiprojekti_kiinteisto_kiinteisto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_kiinteisto
    ADD CONSTRAINT inventointiprojekti_kiinteisto_kiinteisto_id_foreign FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_kunta inventointiprojekti_kunta_inventointiprojekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_kunta
    ADD CONSTRAINT inventointiprojekti_kunta_inventointiprojekti_id_foreign FOREIGN KEY (inventointiprojekti_id) REFERENCES public.inventointiprojekti(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti_kunta inventointiprojekti_kunta_kunta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_kunta
    ADD CONSTRAINT inventointiprojekti_kunta_kunta_id_foreign FOREIGN KEY (kunta_id) REFERENCES public.kunta(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inventointiprojekti inventointiprojekti_laji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti
    ADD CONSTRAINT inventointiprojekti_laji_id_foreign FOREIGN KEY (laji_id) REFERENCES public.inventointiprojekti_laji(id);


--
-- Name: inventointiprojekti_laji inventointiprojekti_laji_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_laji
    ADD CONSTRAINT inventointiprojekti_laji_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojekti_laji inventointiprojekti_laji_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_laji
    ADD CONSTRAINT inventointiprojekti_laji_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojekti_laji inventointiprojekti_laji_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti_laji
    ADD CONSTRAINT inventointiprojekti_laji_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojekti inventointiprojekti_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojekti
    ADD CONSTRAINT inventointiprojekti_tyyppi_id_foreign FOREIGN KEY (tyyppi_id) REFERENCES public.inventointiprojektityyppi(id);


--
-- Name: inventointiprojektityyppi inventointiprojektityyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojektityyppi
    ADD CONSTRAINT inventointiprojektityyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: inventointiprojektityyppi inventointiprojektityyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventointiprojektityyppi
    ADD CONSTRAINT inventointiprojektityyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: julkaisu julkaisu_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.julkaisu
    ADD CONSTRAINT julkaisu_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: julkaisu julkaisu_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.julkaisu
    ADD CONSTRAINT julkaisu_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: katetyyppi katetyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.katetyyppi
    ADD CONSTRAINT katetyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: katetyyppi katetyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.katetyyppi
    ADD CONSTRAINT katetyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kattotyyppi kattotyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kattotyyppi
    ADD CONSTRAINT kattotyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kattotyyppi kattotyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kattotyyppi
    ADD CONSTRAINT kattotyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kayttotarkoitus kayttotarkoitus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kayttotarkoitus
    ADD CONSTRAINT kayttotarkoitus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kayttotarkoitus kayttotarkoitus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kayttotarkoitus
    ADD CONSTRAINT kayttotarkoitus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: keramiikkatyyppi keramiikkatyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.keramiikkatyyppi
    ADD CONSTRAINT keramiikkatyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: keramiikkatyyppi keramiikkatyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.keramiikkatyyppi
    ADD CONSTRAINT keramiikkatyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kiinteisto kiinteist_kyla_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto
    ADD CONSTRAINT kiinteist_kyla_foreign FOREIGN KEY (kyla_id) REFERENCES public.kyla(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: kiinteisto_aluetyyppi kiinteisto_aluetyyppi_aluetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_aluetyyppi
    ADD CONSTRAINT kiinteisto_aluetyyppi_aluetyyppi_id_foreign FOREIGN KEY (aluetyyppi_id) REFERENCES public.aluetyyppi(id);


--
-- Name: kiinteisto_aluetyyppi kiinteisto_aluetyyppi_kiinteisto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_aluetyyppi
    ADD CONSTRAINT kiinteisto_aluetyyppi_kiinteisto_id_foreign FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id);


--
-- Name: kiinteisto_aluetyyppi kiinteisto_aluetyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_aluetyyppi
    ADD CONSTRAINT kiinteisto_aluetyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kiinteisto_aluetyyppi kiinteisto_aluetyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_aluetyyppi
    ADD CONSTRAINT kiinteisto_aluetyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kiinteisto kiinteisto_arvotustyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto
    ADD CONSTRAINT kiinteisto_arvotustyyppi_id_foreign FOREIGN KEY (arvotustyyppi_id) REFERENCES public.arvotustyyppi(id);


--
-- Name: kiinteisto_historiallinen_tilatyyppi kiinteisto_historiallinen_tilatyyppi_kiinteisto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_historiallinen_tilatyyppi
    ADD CONSTRAINT kiinteisto_historiallinen_tilatyyppi_kiinteisto_id_foreign FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id);


--
-- Name: kiinteisto_historiallinen_tilatyyppi kiinteisto_historiallinen_tilatyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_historiallinen_tilatyyppi
    ADD CONSTRAINT kiinteisto_historiallinen_tilatyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kiinteisto_historiallinen_tilatyyppi kiinteisto_historiallinen_tilatyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_historiallinen_tilatyyppi
    ADD CONSTRAINT kiinteisto_historiallinen_tilatyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kiinteisto_historiallinen_tilatyyppi kiinteisto_historiallinen_tilatyyppi_tilatyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_historiallinen_tilatyyppi
    ADD CONSTRAINT kiinteisto_historiallinen_tilatyyppi_tilatyyppi_id_foreign FOREIGN KEY (tilatyyppi_id) REFERENCES public.tilatyyppi(id);


--
-- Name: kiinteisto_kiinteistokulttuurihistoriallinenarvo kiinteisto_kiinteistokulttuurihistoriallinenarvo_kiinteisto_id_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_kiinteistokulttuurihistoriallinenarvo
    ADD CONSTRAINT kiinteisto_kiinteistokulttuurihistoriallinenarvo_kiinteisto_id_ FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: kiinteisto_kiinteistokulttuurihistoriallinenarvo kiinteisto_kiinteistokulttuurihistoriallinenarvo_kulttuurihisto; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_kiinteistokulttuurihistoriallinenarvo
    ADD CONSTRAINT kiinteisto_kiinteistokulttuurihistoriallinenarvo_kulttuurihisto FOREIGN KEY (kulttuurihistoriallinenarvo_id) REFERENCES public.kiinteistokulttuurihistoriallinenarvo(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: kiinteisto_suojelutyyppi kiinteisto_suojelutyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_suojelutyyppi
    ADD CONSTRAINT kiinteisto_suojelutyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kiinteisto_suojelutyyppi kiinteisto_suojelutyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiinteisto_suojelutyyppi
    ADD CONSTRAINT kiinteisto_suojelutyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kokoelma kokoelma_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kokoelma
    ADD CONSTRAINT kokoelma_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kokoelma kokoelma_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kokoelma
    ADD CONSTRAINT kokoelma_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kokoelma kokoelma_museo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kokoelma
    ADD CONSTRAINT kokoelma_museo_id_foreign FOREIGN KEY (museo_id) REFERENCES public.museo(id);


--
-- Name: kokoelma kokoelma_paakokoelma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kokoelma
    ADD CONSTRAINT kokoelma_paakokoelma_id_foreign FOREIGN KEY (paakokoelma_id) REFERENCES public.kokoelma(id);


--
-- Name: kori kori_korityyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kori
    ADD CONSTRAINT kori_korityyppi_id_foreign FOREIGN KEY (korityyppi_id) REFERENCES public.korityyppi(id);


--
-- Name: kori kori_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kori
    ADD CONSTRAINT kori_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kori kori_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kori
    ADD CONSTRAINT kori_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: korityyppi korityyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.korityyppi
    ADD CONSTRAINT korityyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: korityyppi korityyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.korityyppi
    ADD CONSTRAINT korityyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kunto kunto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunto
    ADD CONSTRAINT kunto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kunto kunto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunto
    ADD CONSTRAINT kunto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kunto kunto_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kunto
    ADD CONSTRAINT kunto_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: kuntotyyppi kuntotyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuntotyyppi
    ADD CONSTRAINT kuntotyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: kuntotyyppi kuntotyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuntotyyppi
    ADD CONSTRAINT kuntotyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: kuva_alue kuva_alue_alue_id_alue_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_alue
    ADD CONSTRAINT kuva_alue_alue_id_alue_foreign FOREIGN KEY (alue_id) REFERENCES public.alue(id);


--
-- Name: kuva_alue kuva_alue_kuva_id_kuva_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_alue
    ADD CONSTRAINT kuva_alue_kuva_id_kuva_foreign FOREIGN KEY (kuva_id) REFERENCES public.kuva(id);


--
-- Name: kuva_arvoalue kuva_arvoalue_arvoalue_id_porrashuone_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_arvoalue
    ADD CONSTRAINT kuva_arvoalue_arvoalue_id_porrashuone_foreign FOREIGN KEY (arvoalue_id) REFERENCES public.arvoalue(id);


--
-- Name: kuva_arvoalue kuva_arvoalue_kuva_id_porrashuone_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_arvoalue
    ADD CONSTRAINT kuva_arvoalue_kuva_id_porrashuone_foreign FOREIGN KEY (kuva_id) REFERENCES public.kuva(id);


--
-- Name: kuva_kiinteisto kuva_kiinteisto_kiinteisto_id_kiinteisto_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_kiinteisto
    ADD CONSTRAINT kuva_kiinteisto_kiinteisto_id_kiinteisto_foreign FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id);


--
-- Name: kuva_kiinteisto kuva_kiinteisto_kuva_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_kiinteisto
    ADD CONSTRAINT kuva_kiinteisto_kuva_foreign FOREIGN KEY (kuva_id) REFERENCES public.kuva(id);


--
-- Name: kuva_kyla kuva_kyla_kuva_id_kuva_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_kyla
    ADD CONSTRAINT kuva_kyla_kuva_id_kuva_foreign FOREIGN KEY (kuva_id) REFERENCES public.kuva(id);


--
-- Name: kuva_kyla kuva_kyla_kyla_id_kyla_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_kyla
    ADD CONSTRAINT kuva_kyla_kyla_id_kyla_foreign FOREIGN KEY (kyla_id) REFERENCES public.kyla(id);


--
-- Name: kuva_porrashuone kuva_porrashuone_kuva_id_kuva_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_porrashuone
    ADD CONSTRAINT kuva_porrashuone_kuva_id_kuva_foreign FOREIGN KEY (kuva_id) REFERENCES public.kuva(id);


--
-- Name: kuva_porrashuone kuva_porrashuone_porrashuone_id_porrashuone_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_porrashuone
    ADD CONSTRAINT kuva_porrashuone_porrashuone_id_porrashuone_foreign FOREIGN KEY (porrashuone_id) REFERENCES public.porrashuone(id);


--
-- Name: kuva_rakennus kuva_rakennus_kuva_id_kuva_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_rakennus
    ADD CONSTRAINT kuva_rakennus_kuva_id_kuva_foreign FOREIGN KEY (kuva_id) REFERENCES public.kuva(id);


--
-- Name: kuva_rakennus kuva_rakennus_rakennus_id_rakennus_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_rakennus
    ADD CONSTRAINT kuva_rakennus_rakennus_id_rakennus_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: kuva_suunnittelija kuva_suunnittelija_kuva_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_suunnittelija
    ADD CONSTRAINT kuva_suunnittelija_kuva_id_foreign FOREIGN KEY (kuva_id) REFERENCES public.kuva(id);


--
-- Name: kuva_suunnittelija kuva_suunnittelija_suunnittelija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kuva_suunnittelija
    ADD CONSTRAINT kuva_suunnittelija_suunnittelija_id_foreign FOREIGN KEY (suunnittelija_id) REFERENCES public.suunnittelija(id);


--
-- Name: kyla kyla_kunta_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kyla
    ADD CONSTRAINT kyla_kunta_foreign FOREIGN KEY (kunta_id) REFERENCES public.kunta(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: laatu laatu_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.laatu
    ADD CONSTRAINT laatu_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: laatu laatu_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.laatu
    ADD CONSTRAINT laatu_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: liite_tyyppi liite_tyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liite_tyyppi
    ADD CONSTRAINT liite_tyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: liite_tyyppi liite_tyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liite_tyyppi
    ADD CONSTRAINT liite_tyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: loyto_kategoria loyto_kategoria_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_kategoria
    ADD CONSTRAINT loyto_kategoria_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: loyto_kategoria loyto_kategoria_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_kategoria
    ADD CONSTRAINT loyto_kategoria_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: loyto_tyyppi loyto_tyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_tyyppi
    ADD CONSTRAINT loyto_tyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: loyto_tyyppi loyto_tyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.loyto_tyyppi
    ADD CONSTRAINT loyto_tyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: maastomerkinta maastomerkinta_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maastomerkinta
    ADD CONSTRAINT maastomerkinta_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: maastomerkinta maastomerkinta_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maastomerkinta
    ADD CONSTRAINT maastomerkinta_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: maastomerkinta maastomerkinta_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maastomerkinta
    ADD CONSTRAINT maastomerkinta_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: materiaali materiaali_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.materiaali
    ADD CONSTRAINT materiaali_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: materiaali materiaali_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.materiaali
    ADD CONSTRAINT materiaali_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportinsyy matkaraportinsyy_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportinsyy
    ADD CONSTRAINT matkaraportinsyy_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportinsyy matkaraportinsyy_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportinsyy
    ADD CONSTRAINT matkaraportinsyy_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportinsyy matkaraportinsyy_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportinsyy
    ADD CONSTRAINT matkaraportinsyy_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportti matkaraportti_kiinteisto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti
    ADD CONSTRAINT matkaraportti_kiinteisto_id_foreign FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id);


--
-- Name: matkaraportti matkaraportti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti
    ADD CONSTRAINT matkaraportti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportti matkaraportti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti
    ADD CONSTRAINT matkaraportti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportti matkaraportti_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti
    ADD CONSTRAINT matkaraportti_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportti_syy matkaraportti_syy_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti_syy
    ADD CONSTRAINT matkaraportti_syy_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportti_syy matkaraportti_syy_matkaraportinsyy_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti_syy
    ADD CONSTRAINT matkaraportti_syy_matkaraportinsyy_id_foreign FOREIGN KEY (matkaraportinsyy_id) REFERENCES public.matkaraportinsyy(id);


--
-- Name: matkaraportti_syy matkaraportti_syy_matkaraportti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti_syy
    ADD CONSTRAINT matkaraportti_syy_matkaraportti_id_foreign FOREIGN KEY (matkaraportti_id) REFERENCES public.matkaraportti(id);


--
-- Name: matkaraportti_syy matkaraportti_syy_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti_syy
    ADD CONSTRAINT matkaraportti_syy_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: matkaraportti_syy matkaraportti_syy_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matkaraportti_syy
    ADD CONSTRAINT matkaraportti_syy_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: museo museo_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.museo
    ADD CONSTRAINT museo_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: museo museo_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.museo
    ADD CONSTRAINT museo_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: muutoshistoria muutoshistoria_kayttaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.muutoshistoria
    ADD CONSTRAINT muutoshistoria_kayttaja_foreign FOREIGN KEY (kayttaja_id) REFERENCES public.kayttaja(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: nayttely nayttely_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nayttely
    ADD CONSTRAINT nayttely_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: nayttely nayttely_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nayttely
    ADD CONSTRAINT nayttely_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: ocm_luokka ocm_luokka_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ocm_luokka
    ADD CONSTRAINT ocm_luokka_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: ocm_luokka ocm_luokka_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ocm_luokka
    ADD CONSTRAINT ocm_luokka_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: perustustyyppi perustustyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perustustyyppi
    ADD CONSTRAINT perustustyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: perustustyyppi perustustyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perustustyyppi
    ADD CONSTRAINT perustustyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: porrashuone porrashuone_porrashuonetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuone
    ADD CONSTRAINT porrashuone_porrashuonetyyppi_id_foreign FOREIGN KEY (porrashuonetyyppi_id) REFERENCES public.porrashuonetyyppi(id);


--
-- Name: porrashuone porrashuone_rakennus_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuone
    ADD CONSTRAINT porrashuone_rakennus_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: porrashuonetyyppi porrashuonetyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuonetyyppi
    ADD CONSTRAINT porrashuonetyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: porrashuonetyyppi porrashuonetyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.porrashuonetyyppi
    ADD CONSTRAINT porrashuonetyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: projekti_kayttaja_rooli projekti_kayttaja_rooli_kayttaja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_kayttaja_rooli
    ADD CONSTRAINT projekti_kayttaja_rooli_kayttaja_id_foreign FOREIGN KEY (kayttaja_id) REFERENCES public.kayttaja(id);


--
-- Name: projekti_kayttaja_rooli projekti_kayttaja_rooli_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_kayttaja_rooli
    ADD CONSTRAINT projekti_kayttaja_rooli_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: projekti_kayttaja_rooli projekti_kayttaja_rooli_projekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_kayttaja_rooli
    ADD CONSTRAINT projekti_kayttaja_rooli_projekti_id_foreign FOREIGN KEY (projekti_id) REFERENCES public.projekti(id);


--
-- Name: projekti_kayttaja_rooli projekti_kayttaja_rooli_projekti_rooli_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_kayttaja_rooli
    ADD CONSTRAINT projekti_kayttaja_rooli_projekti_rooli_id_foreign FOREIGN KEY (projekti_rooli_id) REFERENCES public.projekti_rooli(id);


--
-- Name: projekti projekti_kunta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti
    ADD CONSTRAINT projekti_kunta_id_foreign FOREIGN KEY (kunta_id) REFERENCES public.kunta(id);


--
-- Name: projekti projekti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti
    ADD CONSTRAINT projekti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: projekti projekti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti
    ADD CONSTRAINT projekti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: projekti projekti_projekti_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti
    ADD CONSTRAINT projekti_projekti_tyyppi_id_foreign FOREIGN KEY (projekti_tyyppi_id) REFERENCES public.projekti_tyyppi(id);


--
-- Name: projekti_sijainti projekti_sijainti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_sijainti
    ADD CONSTRAINT projekti_sijainti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: projekti_sijainti projekti_sijainti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_sijainti
    ADD CONSTRAINT projekti_sijainti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: projekti_sijainti projekti_sijainti_projekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_sijainti
    ADD CONSTRAINT projekti_sijainti_projekti_id_foreign FOREIGN KEY (projekti_id) REFERENCES public.projekti(id);


--
-- Name: projekti_tyyppi projekti_tyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_tyyppi
    ADD CONSTRAINT projekti_tyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: projekti_tyyppi projekti_tyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projekti_tyyppi
    ADD CONSTRAINT projekti_tyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rajaustarkkuus rajaustarkkuus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rajaustarkkuus
    ADD CONSTRAINT rajaustarkkuus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rajaustarkkuus rajaustarkkuus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rajaustarkkuus
    ADD CONSTRAINT rajaustarkkuus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rajaustarkkuus rajaustarkkuus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rajaustarkkuus
    ADD CONSTRAINT rajaustarkkuus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_alkuperainenkaytto rakennus_alkuperainenkaytto_kayttotarkoitus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_alkuperainenkaytto
    ADD CONSTRAINT rakennus_alkuperainenkaytto_kayttotarkoitus_id_foreign FOREIGN KEY (kayttotarkoitus_id) REFERENCES public.kayttotarkoitus(id);


--
-- Name: rakennus_alkuperainenkaytto rakennus_alkuperainenkaytto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_alkuperainenkaytto
    ADD CONSTRAINT rakennus_alkuperainenkaytto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_alkuperainenkaytto rakennus_alkuperainenkaytto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_alkuperainenkaytto
    ADD CONSTRAINT rakennus_alkuperainenkaytto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_alkuperainenkaytto rakennus_alkuperainenkaytto_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_alkuperainenkaytto
    ADD CONSTRAINT rakennus_alkuperainenkaytto_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus rakennus_arvotustyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus
    ADD CONSTRAINT rakennus_arvotustyyppi_id_foreign FOREIGN KEY (arvotustyyppi_id) REFERENCES public.arvotustyyppi(id);


--
-- Name: rakennus_katetyyppi rakennus_katetyyppi_katetyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_katetyyppi
    ADD CONSTRAINT rakennus_katetyyppi_katetyyppi_id_foreign FOREIGN KEY (katetyyppi_id) REFERENCES public.katetyyppi(id);


--
-- Name: rakennus_katetyyppi rakennus_katetyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_katetyyppi
    ADD CONSTRAINT rakennus_katetyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_katetyyppi rakennus_katetyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_katetyyppi
    ADD CONSTRAINT rakennus_katetyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_katetyyppi rakennus_katetyyppi_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_katetyyppi
    ADD CONSTRAINT rakennus_katetyyppi_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus_kattotyyppi rakennus_kattotyyppi_kattotyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_kattotyyppi
    ADD CONSTRAINT rakennus_kattotyyppi_kattotyyppi_id_foreign FOREIGN KEY (kattotyyppi_id) REFERENCES public.kattotyyppi(id);


--
-- Name: rakennus_kattotyyppi rakennus_kattotyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_kattotyyppi
    ADD CONSTRAINT rakennus_kattotyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_kattotyyppi rakennus_kattotyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_kattotyyppi
    ADD CONSTRAINT rakennus_kattotyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_kattotyyppi rakennus_kattotyyppi_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_kattotyyppi
    ADD CONSTRAINT rakennus_kattotyyppi_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus rakennus_kiinteisto_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus
    ADD CONSTRAINT rakennus_kiinteisto_foreign FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rakennus rakennus_kuntotyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus
    ADD CONSTRAINT rakennus_kuntotyyppi_id_foreign FOREIGN KEY (kuntotyyppi_id) REFERENCES public.kuntotyyppi(id);


--
-- Name: rakennus_muutosvuosi rakennus_muutosvuosi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_muutosvuosi
    ADD CONSTRAINT rakennus_muutosvuosi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_muutosvuosi rakennus_muutosvuosi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_muutosvuosi
    ADD CONSTRAINT rakennus_muutosvuosi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_muutosvuosi rakennus_muutosvuosi_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_muutosvuosi
    ADD CONSTRAINT rakennus_muutosvuosi_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus rakennus_nykyinen_tyyli_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus
    ADD CONSTRAINT rakennus_nykyinen_tyyli_id_foreign FOREIGN KEY (nykyinen_tyyli_id) REFERENCES public.tyylisuunta(id);


--
-- Name: rakennus_nykykaytto rakennus_nykykaytto_kayttotarkoitus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_nykykaytto
    ADD CONSTRAINT rakennus_nykykaytto_kayttotarkoitus_id_foreign FOREIGN KEY (kayttotarkoitus_id) REFERENCES public.kayttotarkoitus(id);


--
-- Name: rakennus_nykykaytto rakennus_nykykaytto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_nykykaytto
    ADD CONSTRAINT rakennus_nykykaytto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_nykykaytto rakennus_nykykaytto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_nykykaytto
    ADD CONSTRAINT rakennus_nykykaytto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_nykykaytto rakennus_nykykaytto_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_nykykaytto
    ADD CONSTRAINT rakennus_nykykaytto_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus_omistaja rakennus_omistaja_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_omistaja
    ADD CONSTRAINT rakennus_omistaja_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_omistaja rakennus_omistaja_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_omistaja
    ADD CONSTRAINT rakennus_omistaja_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_omistaja rakennus_omistaja_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_omistaja
    ADD CONSTRAINT rakennus_omistaja_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id) ON DELETE CASCADE;


--
-- Name: rakennus_osoite rakennus_osoite_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_osoite
    ADD CONSTRAINT rakennus_osoite_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_osoite rakennus_osoite_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_osoite
    ADD CONSTRAINT rakennus_osoite_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_osoite rakennus_osoite_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_osoite
    ADD CONSTRAINT rakennus_osoite_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id) ON DELETE CASCADE;


--
-- Name: rakennus_perustustyyppi rakennus_perustustyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_perustustyyppi
    ADD CONSTRAINT rakennus_perustustyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_perustustyyppi rakennus_perustustyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_perustustyyppi
    ADD CONSTRAINT rakennus_perustustyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_perustustyyppi rakennus_perustustyyppi_perustustyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_perustustyyppi
    ADD CONSTRAINT rakennus_perustustyyppi_perustustyyppi_id_foreign FOREIGN KEY (perustustyyppi_id) REFERENCES public.perustustyyppi(id);


--
-- Name: rakennus_perustustyyppi rakennus_perustustyyppi_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_perustustyyppi
    ADD CONSTRAINT rakennus_perustustyyppi_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus_rakennuskulttuurihistoriallinenarvo rakennus_rakennuskulttuurihistoriallinenarvo_kulttuurihistorial; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennuskulttuurihistoriallinenarvo
    ADD CONSTRAINT rakennus_rakennuskulttuurihistoriallinenarvo_kulttuurihistorial FOREIGN KEY (kulttuurihistoriallinenarvo_id) REFERENCES public.rakennuskulttuurihistoriallinenarvo(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rakennus_rakennuskulttuurihistoriallinenarvo rakennus_rakennuskulttuurihistoriallinenarvo_rakennus_id_foreig; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennuskulttuurihistoriallinenarvo
    ADD CONSTRAINT rakennus_rakennuskulttuurihistoriallinenarvo_rakennus_id_foreig FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: rakennus_rakennustyyppi rakennus_rakennustyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennustyyppi
    ADD CONSTRAINT rakennus_rakennustyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_rakennustyyppi rakennus_rakennustyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennustyyppi
    ADD CONSTRAINT rakennus_rakennustyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_rakennustyyppi rakennus_rakennustyyppi_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennustyyppi
    ADD CONSTRAINT rakennus_rakennustyyppi_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus_rakennustyyppi rakennus_rakennustyyppi_rakennustyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_rakennustyyppi
    ADD CONSTRAINT rakennus_rakennustyyppi_rakennustyyppi_id_foreign FOREIGN KEY (rakennustyyppi_id) REFERENCES public.rakennustyyppi(id);


--
-- Name: rakennus_runkotyyppi rakennus_runkotyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_runkotyyppi
    ADD CONSTRAINT rakennus_runkotyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_runkotyyppi rakennus_runkotyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_runkotyyppi
    ADD CONSTRAINT rakennus_runkotyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_runkotyyppi rakennus_runkotyyppi_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_runkotyyppi
    ADD CONSTRAINT rakennus_runkotyyppi_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus_runkotyyppi rakennus_runkotyyppi_runkotyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_runkotyyppi
    ADD CONSTRAINT rakennus_runkotyyppi_runkotyyppi_id_foreign FOREIGN KEY (runkotyyppi_id) REFERENCES public.runkotyyppi(id);


--
-- Name: rakennus_suojelutyyppi rakennus_suojelutyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_suojelutyyppi
    ADD CONSTRAINT rakennus_suojelutyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_suojelutyyppi rakennus_suojelutyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_suojelutyyppi
    ADD CONSTRAINT rakennus_suojelutyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_vuoraustyyppi rakennus_vuoraustyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_vuoraustyyppi
    ADD CONSTRAINT rakennus_vuoraustyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_vuoraustyyppi rakennus_vuoraustyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_vuoraustyyppi
    ADD CONSTRAINT rakennus_vuoraustyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rakennus_vuoraustyyppi rakennus_vuoraustyyppi_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_vuoraustyyppi
    ADD CONSTRAINT rakennus_vuoraustyyppi_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: rakennus_vuoraustyyppi rakennus_vuoraustyyppi_vuoraustyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennus_vuoraustyyppi
    ADD CONSTRAINT rakennus_vuoraustyyppi_vuoraustyyppi_id_foreign FOREIGN KEY (vuoraustyyppi_id) REFERENCES public.vuoraustyyppi(id);


--
-- Name: rakennustyyppi rakennustyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennustyyppi
    ADD CONSTRAINT rakennustyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rakennustyyppi rakennustyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rakennustyyppi
    ADD CONSTRAINT rakennustyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rauhoitusluokka rauhoitusluokka_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rauhoitusluokka
    ADD CONSTRAINT rauhoitusluokka_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: rauhoitusluokka rauhoitusluokka_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rauhoitusluokka
    ADD CONSTRAINT rauhoitusluokka_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: rauhoitusluokka rauhoitusluokka_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rauhoitusluokka
    ADD CONSTRAINT rauhoitusluokka_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: runkotyyppi runkotyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.runkotyyppi
    ADD CONSTRAINT runkotyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: runkotyyppi runkotyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.runkotyyppi
    ADD CONSTRAINT runkotyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suojelutyyppi suojelutyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suojelutyyppi
    ADD CONSTRAINT suojelutyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suojelutyyppi suojelutyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suojelutyyppi
    ADD CONSTRAINT suojelutyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suojelutyyppi_ryhma suojelutyyppi_ryhma_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suojelutyyppi_ryhma
    ADD CONSTRAINT suojelutyyppi_ryhma_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suojelutyyppi_ryhma suojelutyyppi_ryhma_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suojelutyyppi_ryhma
    ADD CONSTRAINT suojelutyyppi_ryhma_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_ammattiarvo suunnittelija_ammattiarvo_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_ammattiarvo
    ADD CONSTRAINT suunnittelija_ammattiarvo_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_ammattiarvo suunnittelija_ammattiarvo_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_ammattiarvo
    ADD CONSTRAINT suunnittelija_ammattiarvo_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_ammattiarvo suunnittelija_ammattiarvo_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_ammattiarvo
    ADD CONSTRAINT suunnittelija_ammattiarvo_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_laji suunnittelija_laji_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_laji
    ADD CONSTRAINT suunnittelija_laji_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_laji suunnittelija_laji_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_laji
    ADD CONSTRAINT suunnittelija_laji_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_laji suunnittelija_laji_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_laji
    ADD CONSTRAINT suunnittelija_laji_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija suunnittelija_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija
    ADD CONSTRAINT suunnittelija_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija suunnittelija_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija
    ADD CONSTRAINT suunnittelija_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija suunnittelija_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija
    ADD CONSTRAINT suunnittelija_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_porrashuone suunnittelija_porrashuone_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone
    ADD CONSTRAINT suunnittelija_porrashuone_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_porrashuone suunnittelija_porrashuone_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone
    ADD CONSTRAINT suunnittelija_porrashuone_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_porrashuone suunnittelija_porrashuone_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone
    ADD CONSTRAINT suunnittelija_porrashuone_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_porrashuone_vanha suunnittelija_porrashuone_porrashuone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone_vanha
    ADD CONSTRAINT suunnittelija_porrashuone_porrashuone_id_foreign FOREIGN KEY (porrashuone_id) REFERENCES public.porrashuone(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: suunnittelija_porrashuone suunnittelija_porrashuone_porrashuone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone
    ADD CONSTRAINT suunnittelija_porrashuone_porrashuone_id_foreign FOREIGN KEY (porrashuone_id) REFERENCES public.porrashuone(id);


--
-- Name: suunnittelija_porrashuone_vanha suunnittelija_porrashuone_suunnittelija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone_vanha
    ADD CONSTRAINT suunnittelija_porrashuone_suunnittelija_id_foreign FOREIGN KEY (suunnittelija_id) REFERENCES public.suunnittelija_vanha(id);


--
-- Name: suunnittelija_porrashuone suunnittelija_porrashuone_suunnittelija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone
    ADD CONSTRAINT suunnittelija_porrashuone_suunnittelija_id_foreign FOREIGN KEY (suunnittelija_id) REFERENCES public.suunnittelija(id);


--
-- Name: suunnittelija_porrashuone suunnittelija_porrashuone_suunnittelija_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_porrashuone
    ADD CONSTRAINT suunnittelija_porrashuone_suunnittelija_tyyppi_id_foreign FOREIGN KEY (suunnittelija_tyyppi_id) REFERENCES public.suunnittelija_tyyppi(id);


--
-- Name: suunnittelija_rakennus suunnittelija_rakennus_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus
    ADD CONSTRAINT suunnittelija_rakennus_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_rakennus suunnittelija_rakennus_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus
    ADD CONSTRAINT suunnittelija_rakennus_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_rakennus suunnittelija_rakennus_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus
    ADD CONSTRAINT suunnittelija_rakennus_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_rakennus_vanha suunnittelija_rakennus_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus_vanha
    ADD CONSTRAINT suunnittelija_rakennus_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: suunnittelija_rakennus suunnittelija_rakennus_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus
    ADD CONSTRAINT suunnittelija_rakennus_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: suunnittelija_rakennus_vanha suunnittelija_rakennus_suunnittelija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus_vanha
    ADD CONSTRAINT suunnittelija_rakennus_suunnittelija_id_foreign FOREIGN KEY (suunnittelija_id) REFERENCES public.suunnittelija_vanha(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: suunnittelija_rakennus suunnittelija_rakennus_suunnittelija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus
    ADD CONSTRAINT suunnittelija_rakennus_suunnittelija_id_foreign FOREIGN KEY (suunnittelija_id) REFERENCES public.suunnittelija(id);


--
-- Name: suunnittelija_rakennus suunnittelija_rakennus_suunnittelija_tyyppi_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_rakennus
    ADD CONSTRAINT suunnittelija_rakennus_suunnittelija_tyyppi_id_foreign FOREIGN KEY (suunnittelija_tyyppi_id) REFERENCES public.suunnittelija_tyyppi(id);


--
-- Name: suunnittelija suunnittelija_suunnittelija_ammattiarvo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija
    ADD CONSTRAINT suunnittelija_suunnittelija_ammattiarvo_id_foreign FOREIGN KEY (suunnittelija_ammattiarvo_id) REFERENCES public.suunnittelija_ammattiarvo(id);


--
-- Name: suunnittelija suunnittelija_suunnittelija_laji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija
    ADD CONSTRAINT suunnittelija_suunnittelija_laji_id_foreign FOREIGN KEY (suunnittelija_laji_id) REFERENCES public.suunnittelija_laji(id);


--
-- Name: suunnittelija_tyyppi suunnittelija_tyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_tyyppi
    ADD CONSTRAINT suunnittelija_tyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_tyyppi suunnittelija_tyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_tyyppi
    ADD CONSTRAINT suunnittelija_tyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: suunnittelija_tyyppi suunnittelija_tyyppi_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suunnittelija_tyyppi
    ADD CONSTRAINT suunnittelija_tyyppi_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: talteenottotapa talteenottotapa_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talteenottotapa
    ADD CONSTRAINT talteenottotapa_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: talteenottotapa talteenottotapa_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talteenottotapa
    ADD CONSTRAINT talteenottotapa_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: tekijanoikeuslauseke tekijanoikeuslauseke_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tekijanoikeuslauseke
    ADD CONSTRAINT tekijanoikeuslauseke_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: tekijanoikeuslauseke tekijanoikeuslauseke_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tekijanoikeuslauseke
    ADD CONSTRAINT tekijanoikeuslauseke_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: tekijanoikeuslauseke tekijanoikeuslauseke_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tekijanoikeuslauseke
    ADD CONSTRAINT tekijanoikeuslauseke_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: tiedosto_alue tiedosto_alue_alue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_alue
    ADD CONSTRAINT tiedosto_alue_alue_id_foreign FOREIGN KEY (alue_id) REFERENCES public.alue(id);


--
-- Name: tiedosto_alue tiedosto_alue_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_alue
    ADD CONSTRAINT tiedosto_alue_tiedosto_id_foreign FOREIGN KEY (tiedosto_id) REFERENCES public.tiedosto(id);


--
-- Name: tiedosto_arvoalue tiedosto_arvoalue_arvoalue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_arvoalue
    ADD CONSTRAINT tiedosto_arvoalue_arvoalue_id_foreign FOREIGN KEY (arvoalue_id) REFERENCES public.arvoalue(id);


--
-- Name: tiedosto_arvoalue tiedosto_arvoalue_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_arvoalue
    ADD CONSTRAINT tiedosto_arvoalue_tiedosto_id_foreign FOREIGN KEY (tiedosto_id) REFERENCES public.tiedosto(id);


--
-- Name: tiedosto_kiinteisto tiedosto_kiinteisto_kiinteisto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_kiinteisto
    ADD CONSTRAINT tiedosto_kiinteisto_kiinteisto_id_foreign FOREIGN KEY (kiinteisto_id) REFERENCES public.kiinteisto(id);


--
-- Name: tiedosto_kiinteisto tiedosto_kiinteisto_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_kiinteisto
    ADD CONSTRAINT tiedosto_kiinteisto_tiedosto_id_foreign FOREIGN KEY (tiedosto_id) REFERENCES public.tiedosto(id);


--
-- Name: tiedosto_kunta tiedosto_kunta_kunta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_kunta
    ADD CONSTRAINT tiedosto_kunta_kunta_id_foreign FOREIGN KEY (kunta_id) REFERENCES public.kunta(id);


--
-- Name: tiedosto_kunta tiedosto_kunta_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_kunta
    ADD CONSTRAINT tiedosto_kunta_tiedosto_id_foreign FOREIGN KEY (tiedosto_id) REFERENCES public.tiedosto(id);


--
-- Name: tiedosto_porrashuone tiedosto_porrashuone_porrashuone_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_porrashuone
    ADD CONSTRAINT tiedosto_porrashuone_porrashuone_id_foreign FOREIGN KEY (porrashuone_id) REFERENCES public.porrashuone(id);


--
-- Name: tiedosto_porrashuone tiedosto_porrashuone_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_porrashuone
    ADD CONSTRAINT tiedosto_porrashuone_tiedosto_id_foreign FOREIGN KEY (tiedosto_id) REFERENCES public.tiedosto(id);


--
-- Name: tiedosto_rakennus tiedosto_rakennus_rakennus_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_rakennus
    ADD CONSTRAINT tiedosto_rakennus_rakennus_id_foreign FOREIGN KEY (rakennus_id) REFERENCES public.rakennus(id);


--
-- Name: tiedosto_rakennus tiedosto_rakennus_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_rakennus
    ADD CONSTRAINT tiedosto_rakennus_tiedosto_id_foreign FOREIGN KEY (tiedosto_id) REFERENCES public.tiedosto(id);


--
-- Name: tiedosto_suunnittelija tiedosto_suunnittelija_suunnittelija_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_suunnittelija
    ADD CONSTRAINT tiedosto_suunnittelija_suunnittelija_id_foreign FOREIGN KEY (suunnittelija_id) REFERENCES public.suunnittelija(id);


--
-- Name: tiedosto_suunnittelija tiedosto_suunnittelija_tiedosto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tiedosto_suunnittelija
    ADD CONSTRAINT tiedosto_suunnittelija_tiedosto_id_foreign FOREIGN KEY (tiedosto_id) REFERENCES public.tiedosto(id);


--
-- Name: tilatyyppi tilatyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tilatyyppi
    ADD CONSTRAINT tilatyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: tilatyyppi tilatyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tilatyyppi
    ADD CONSTRAINT tilatyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: tyylisuunta tyylisuunta_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tyylisuunta
    ADD CONSTRAINT tyylisuunta_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: tyylisuunta tyylisuunta_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tyylisuunta
    ADD CONSTRAINT tyylisuunta_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: varasto varasto_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.varasto
    ADD CONSTRAINT varasto_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: varasto varasto_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.varasto
    ADD CONSTRAINT varasto_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: vuoraustyyppi vuoraustyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vuoraustyyppi
    ADD CONSTRAINT vuoraustyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: vuoraustyyppi vuoraustyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vuoraustyyppi
    ADD CONSTRAINT vuoraustyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_asiasana yksikko_asiasana_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_asiasana
    ADD CONSTRAINT yksikko_asiasana_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_asiasana yksikko_asiasana_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_asiasana
    ADD CONSTRAINT yksikko_asiasana_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_asiasana yksikko_asiasana_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_asiasana
    ADD CONSTRAINT yksikko_asiasana_yksikko_id_foreign FOREIGN KEY (yksikko_id) REFERENCES public.yksikko(id) ON DELETE CASCADE;


--
-- Name: yksikko_kaivaustapa yksikko_kaivaustapa_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_kaivaustapa
    ADD CONSTRAINT yksikko_kaivaustapa_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_kaivaustapa yksikko_kaivaustapa_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_kaivaustapa
    ADD CONSTRAINT yksikko_kaivaustapa_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_kaivaustapa yksikko_kaivaustapa_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_kaivaustapa
    ADD CONSTRAINT yksikko_kaivaustapa_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko yksikko_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_maalaji yksikko_maalaji_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_maalaji
    ADD CONSTRAINT yksikko_maalaji_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_maalaji yksikko_maalaji_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_maalaji
    ADD CONSTRAINT yksikko_maalaji_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_maalaji yksikko_maalaji_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_maalaji
    ADD CONSTRAINT yksikko_maalaji_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko yksikko_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_muut_maalajit yksikko_muut_maalajit_ark_tutkimusalue_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_muut_maalajit
    ADD CONSTRAINT yksikko_muut_maalajit_ark_tutkimusalue_yksikko_id_foreign FOREIGN KEY (ark_tutkimusalue_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: yksikko_muut_maalajit yksikko_muut_maalajit_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_muut_maalajit
    ADD CONSTRAINT yksikko_muut_maalajit_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_muut_maalajit yksikko_muut_maalajit_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_muut_maalajit
    ADD CONSTRAINT yksikko_muut_maalajit_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_muut_maalajit yksikko_muut_maalajit_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_muut_maalajit
    ADD CONSTRAINT yksikko_muut_maalajit_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_muut_maalajit yksikko_muut_maalajit_yksikko_muu_maalaji_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_muut_maalajit
    ADD CONSTRAINT yksikko_muut_maalajit_yksikko_muu_maalaji_id_foreign FOREIGN KEY (yksikko_muu_maalaji_id) REFERENCES public.yksikko_maalaji(id);


--
-- Name: yksikko_paasekoitteet yksikko_paasekoitteet_ark_tutkimusalue_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_paasekoitteet
    ADD CONSTRAINT yksikko_paasekoitteet_ark_tutkimusalue_yksikko_id_foreign FOREIGN KEY (ark_tutkimusalue_yksikko_id) REFERENCES public.ark_tutkimusalue_yksikko(id);


--
-- Name: yksikko_paasekoitteet yksikko_paasekoitteet_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_paasekoitteet
    ADD CONSTRAINT yksikko_paasekoitteet_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_paasekoitteet yksikko_paasekoitteet_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_paasekoitteet
    ADD CONSTRAINT yksikko_paasekoitteet_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_paasekoitteet yksikko_paasekoitteet_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_paasekoitteet
    ADD CONSTRAINT yksikko_paasekoitteet_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_paasekoitteet yksikko_paasekoitteet_yksikko_paasekoite_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_paasekoitteet
    ADD CONSTRAINT yksikko_paasekoitteet_yksikko_paasekoite_id_foreign FOREIGN KEY (yksikko_paasekoite_id) REFERENCES public.yksikko_maalaji(id);


--
-- Name: yksikko yksikko_projekti_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_projekti_id_foreign FOREIGN KEY (projekti_id) REFERENCES public.projekti(id);


--
-- Name: yksikko_seulontatapa yksikko_seulontatapa_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_seulontatapa
    ADD CONSTRAINT yksikko_seulontatapa_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_seulontatapa yksikko_seulontatapa_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_seulontatapa
    ADD CONSTRAINT yksikko_seulontatapa_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_seulontatapa yksikko_seulontatapa_poistaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_seulontatapa
    ADD CONSTRAINT yksikko_seulontatapa_poistaja_foreign FOREIGN KEY (poistaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_sijainti yksikko_sijainti_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_sijainti
    ADD CONSTRAINT yksikko_sijainti_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_sijainti yksikko_sijainti_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_sijainti
    ADD CONSTRAINT yksikko_sijainti_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_sijainti yksikko_sijainti_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_sijainti
    ADD CONSTRAINT yksikko_sijainti_yksikko_id_foreign FOREIGN KEY (yksikko_id) REFERENCES public.yksikko(id) ON DELETE CASCADE;


--
-- Name: yksikko_talteenottotapa yksikko_talteenottotapa_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_talteenottotapa
    ADD CONSTRAINT yksikko_talteenottotapa_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_talteenottotapa yksikko_talteenottotapa_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_talteenottotapa
    ADD CONSTRAINT yksikko_talteenottotapa_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_talteenottotapa yksikko_talteenottotapa_talteenottotapa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_talteenottotapa
    ADD CONSTRAINT yksikko_talteenottotapa_talteenottotapa_id_foreign FOREIGN KEY (talteenottotapa_id) REFERENCES public.talteenottotapa(id);


--
-- Name: yksikko_talteenottotapa yksikko_talteenottotapa_yksikko_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_talteenottotapa
    ADD CONSTRAINT yksikko_talteenottotapa_yksikko_id_foreign FOREIGN KEY (yksikko_id) REFERENCES public.yksikko(id) ON DELETE CASCADE;


--
-- Name: yksikko yksikko_tutkija_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_tutkija_foreign FOREIGN KEY (tutkija) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_tyyppi yksikko_tyyppi_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_tyyppi
    ADD CONSTRAINT yksikko_tyyppi_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko_tyyppi yksikko_tyyppi_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko_tyyppi
    ADD CONSTRAINT yksikko_tyyppi_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: yksikko yksikko_uusi_yksikko_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_uusi_yksikko_foreign FOREIGN KEY (uusi_yksikko) REFERENCES public.yksikko(id);


--
-- Name: yksikko yksikko_yhdistetty_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_yhdistetty_foreign FOREIGN KEY (yhdistetty) REFERENCES public.yksikko(id);


--
-- Name: yksikko yksikko_yksikon_elinkaari_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikko
    ADD CONSTRAINT yksikko_yksikon_elinkaari_id_foreign FOREIGN KEY (yksikon_elinkaari_id) REFERENCES public.yksikon_elinkaari(id);


--
-- Name: yksikon_elinkaari yksikon_elinkaari_luoja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikon_elinkaari
    ADD CONSTRAINT yksikon_elinkaari_luoja_foreign FOREIGN KEY (luoja) REFERENCES public.kayttaja(id);


--
-- Name: yksikon_elinkaari yksikon_elinkaari_muokkaaja_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yksikon_elinkaari
    ADD CONSTRAINT yksikon_elinkaari_muokkaaja_foreign FOREIGN KEY (muokkaaja) REFERENCES public.kayttaja(id);


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: -
--

GRANT USAGE ON SCHEMA public TO <application_database_owner>;


--
-- Name: TABLE "MAPINFO_MAPCATALOG"; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public."MAPINFO_MAPCATALOG" TO <application_database_reader>;


--
-- Name: TABLE ajoitus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ajoitus TO <application_database_reader>;


--
-- Name: TABLE ajoitustarkenne; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ajoitustarkenne TO <application_database_reader>;


--
-- Name: TABLE alkuperaisyys; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.alkuperaisyys TO <application_database_reader>;


--
-- Name: TABLE alue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.alue TO <application_database_reader>;


--
-- Name: TABLE alue_kyla; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.alue_kyla TO <application_database_reader>;


--
-- Name: TABLE aluetyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.aluetyyppi TO <application_database_reader>;


--
-- Name: TABLE ark_alakohde_ajoitus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_alakohde_ajoitus TO <application_database_reader>;


--
-- Name: TABLE ark_alakohde_sijainti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_alakohde_sijainti TO <application_database_reader>;


--
-- Name: TABLE ark_kartta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kartta TO <application_database_reader>;


--
-- Name: TABLE ark_kartta_asiasana; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kartta_asiasana TO <application_database_reader>;


--
-- Name: TABLE ark_kartta_loyto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kartta_loyto TO <application_database_reader>;


--
-- Name: TABLE ark_kartta_nayte; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kartta_nayte TO <application_database_reader>;


--
-- Name: TABLE ark_kartta_yksikko; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kartta_yksikko TO <application_database_reader>;


--
-- Name: TABLE ark_karttakoko; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_karttakoko TO <application_database_reader>;


--
-- Name: TABLE ark_karttatyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_karttatyyppi TO <application_database_reader>;


--
-- Name: TABLE ark_kohde; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_ajoitus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_ajoitus TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_alakohde; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_alakohde TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_kiinteistorakennus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_kiinteistorakennus TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_kuntakyla; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_kuntakyla TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_mjrtutkimus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_mjrtutkimus TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_nightly; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_nightly TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_osoite; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_osoite TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_projekti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_projekti TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_rekisterilinkki; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_rekisterilinkki TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_sijainti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_sijainti TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_suojelutiedot; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_suojelutiedot TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_tutkimus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_tutkimus TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_tyyppi TO <application_database_reader>;


--
-- Name: TABLE ark_kohde_vanhakunta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohde_vanhakunta TO <application_database_reader>;


--
-- Name: TABLE ark_kohdelaji; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohdelaji TO <application_database_reader>;


--
-- Name: TABLE ark_kohdetyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohdetyyppi TO <application_database_reader>;


--
-- Name: TABLE ark_kohdetyyppitarkenne; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kohdetyyppitarkenne TO <application_database_reader>;


--
-- Name: TABLE ark_kokoelmalaji; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kokoelmalaji TO <application_database_reader>;


--
-- Name: TABLE ark_kons_kasittely; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_kasittely TO <application_database_reader>;


--
-- Name: TABLE ark_kons_kasittelytapahtumat; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_kasittelytapahtumat TO <application_database_reader>;


--
-- Name: TABLE ark_kons_loyto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_loyto TO <application_database_reader>;


--
-- Name: TABLE ark_kons_materiaali; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_materiaali TO <application_database_reader>;


--
-- Name: TABLE ark_kons_menetelma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_menetelma TO <application_database_reader>;


--
-- Name: TABLE ark_kons_nayte; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_nayte TO <application_database_reader>;


--
-- Name: TABLE ark_kons_toimenpide; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_toimenpide TO <application_database_reader>;


--
-- Name: TABLE ark_kons_toimenpide_materiaalit; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_toimenpide_materiaalit TO <application_database_reader>;


--
-- Name: TABLE ark_kons_toimenpide_menetelma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_toimenpide_menetelma TO <application_database_reader>;


--
-- Name: TABLE ark_kons_toimenpiteet; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kons_toimenpiteet TO <application_database_reader>;


--
-- Name: TABLE ark_konservointivaihe; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_konservointivaihe TO <application_database_reader>;


--
-- Name: TABLE ark_kuva; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva TO <application_database_reader>;


--
-- Name: TABLE ark_kuva_asiasana; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva_asiasana TO <application_database_reader>;


--
-- Name: TABLE ark_kuva_kohde; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva_kohde TO <application_database_reader>;


--
-- Name: TABLE ark_kuva_loyto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva_loyto TO <application_database_reader>;


--
-- Name: TABLE ark_kuva_nayte; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva_nayte TO <application_database_reader>;


--
-- Name: TABLE ark_kuva_tutkimus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva_tutkimus TO <application_database_reader>;


--
-- Name: TABLE ark_kuva_tutkimusalue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva_tutkimusalue TO <application_database_reader>;


--
-- Name: TABLE ark_kuva_yksikko; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_kuva_yksikko TO <application_database_reader>;


--
-- Name: TABLE ark_loyto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_asiasanat; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_asiasanat TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_ensisijaiset_materiaalit; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_ensisijaiset_materiaalit TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_luettelonrohistoria; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_luettelonrohistoria TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_materiaali; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_materiaali TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_materiaalikoodi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_materiaalikoodi TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_materiaalit; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_materiaalit TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_merkinnat; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_merkinnat TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_merkinta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_merkinta TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_tapahtuma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_tapahtuma TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_tapahtumat; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_tapahtumat TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_tila; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_tila TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_tila_tapahtuma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_tila_tapahtuma TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_tyyppi TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_tyyppi_tarkenne; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_tyyppi_tarkenne TO <application_database_reader>;


--
-- Name: TABLE ark_loyto_tyyppi_tarkenteet; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_loyto_tyyppi_tarkenteet TO <application_database_reader>;


--
-- Name: TABLE ark_mittakaava; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_mittakaava TO <application_database_reader>;


--
-- Name: TABLE ark_nayte; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_nayte TO <application_database_reader>;


--
-- Name: TABLE ark_nayte_talteenottotapa; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_nayte_talteenottotapa TO <application_database_reader>;


--
-- Name: TABLE ark_nayte_tapahtuma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_nayte_tapahtuma TO <application_database_reader>;


--
-- Name: TABLE ark_nayte_tapahtumat; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_nayte_tapahtumat TO <application_database_reader>;


--
-- Name: TABLE ark_nayte_tila; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_nayte_tila TO <application_database_reader>;


--
-- Name: TABLE ark_nayte_tila_tapahtuma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_nayte_tila_tapahtuma TO <application_database_reader>;


--
-- Name: TABLE ark_naytekoodi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_naytekoodi TO <application_database_reader>;


--
-- Name: TABLE ark_naytetyypit; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_naytetyypit TO <application_database_reader>;


--
-- Name: TABLE ark_naytetyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_naytetyyppi TO <application_database_reader>;


--
-- Name: TABLE ark_rontgenkuva; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_rontgenkuva TO <application_database_reader>;


--
-- Name: TABLE ark_rontgenkuva_loyto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_rontgenkuva_loyto TO <application_database_reader>;


--
-- Name: TABLE ark_rontgenkuva_nayte; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_rontgenkuva_nayte TO <application_database_reader>;


--
-- Name: TABLE ark_sailytystila; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_sailytystila TO <application_database_reader>;


--
-- Name: TABLE ark_tarkastus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tarkastus TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_kohde; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_kohde TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_kons_kasittely; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_kons_kasittely TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_kons_toimenpiteet; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_kons_toimenpiteet TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_loyto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_loyto TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_nayte; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_nayte TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_rontgenkuva; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_rontgenkuva TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_tutkimus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_tutkimus TO <application_database_reader>;


--
-- Name: TABLE ark_tiedosto_yksikko; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tiedosto_yksikko TO <application_database_reader>;


--
-- Name: TABLE ark_tuhoutumissyy; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tuhoutumissyy TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimus TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimuslaji; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimuslaji TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimus_kayttaja; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimus_kayttaja TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimus_kiinteistorakennus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimus_kiinteistorakennus TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimus_kuntakyla; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimus_kuntakyla TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimus_osoite; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimus_osoite TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimusalue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimusalue TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimusalue_yksikko; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimusalue_yksikko TO <application_database_reader>;


--
-- Name: TABLE ark_tutkimusalue_yksikko_tyovaihe; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ark_tutkimusalue_yksikko_tyovaihe TO <application_database_reader>;


--
-- Name: TABLE arvoalue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.arvoalue TO <application_database_reader>;


--
-- Name: TABLE arvoalue_arvoaluekulttuurihistoriallinenarvo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.arvoalue_arvoaluekulttuurihistoriallinenarvo TO <application_database_reader>;


--
-- Name: TABLE arvoalue_kyla; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.arvoalue_kyla TO <application_database_reader>;


--
-- Name: TABLE arvoalue_suojelutyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.arvoalue_suojelutyyppi TO <application_database_reader>;


--
-- Name: TABLE arvoaluekulttuurihistoriallinenarvo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.arvoaluekulttuurihistoriallinenarvo TO <application_database_reader>;


--
-- Name: TABLE arvotustyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.arvotustyyppi TO <application_database_reader>;


--
-- Name: TABLE asiasana; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.asiasana TO <application_database_reader>;


--
-- Name: TABLE asiasanasto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.asiasanasto TO <application_database_reader>;


--
-- Name: TABLE entiteetti_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.entiteetti_tyyppi TO <application_database_reader>;


--
-- Name: TABLE hoitotarve; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.hoitotarve TO <application_database_reader>;


--
-- Name: TABLE inventointijulkaisu; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointijulkaisu TO <application_database_reader>;


--
-- Name: TABLE inventointijulkaisu_inventointiprojekti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointijulkaisu_inventointiprojekti TO <application_database_reader>;


--
-- Name: TABLE inventointijulkaisu_taso; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointijulkaisu_taso TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti_ajanjakso; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti_ajanjakso TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti_alue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti_alue TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti_arvoalue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti_arvoalue TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti_inventoija; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti_inventoija TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti_kiinteisto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti_kiinteisto TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti_kunta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti_kunta TO <application_database_reader>;


--
-- Name: TABLE inventointiprojekti_laji; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojekti_laji TO <application_database_reader>;


--
-- Name: TABLE inventointiprojektityyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.inventointiprojektityyppi TO <application_database_reader>;


--
-- Name: TABLE jarjestelma_roolit; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.jarjestelma_roolit TO <application_database_reader>;


--
-- Name: TABLE julkaisu; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.julkaisu TO <application_database_reader>;


--
-- Name: TABLE katetyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.katetyyppi TO <application_database_reader>;


--
-- Name: TABLE kattotyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kattotyyppi TO <application_database_reader>;


--
-- Name: TABLE kayttaja; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kayttaja TO <application_database_reader>;


--
-- Name: TABLE kayttaja_salasanaresetointi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kayttaja_salasanaresetointi TO <application_database_reader>;


--
-- Name: TABLE kayttotarkoitus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kayttotarkoitus TO <application_database_reader>;


--
-- Name: TABLE keramiikkatyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.keramiikkatyyppi TO <application_database_reader>;


--
-- Name: TABLE kiinteisto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kiinteisto TO <application_database_reader>;


--
-- Name: TABLE kiinteisto_aluetyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kiinteisto_aluetyyppi TO <application_database_reader>;


--
-- Name: TABLE kiinteisto_historiallinen_tilatyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kiinteisto_historiallinen_tilatyyppi TO <application_database_reader>;


--
-- Name: TABLE kiinteisto_kiinteistokulttuurihistoriallinenarvo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kiinteisto_kiinteistokulttuurihistoriallinenarvo TO <application_database_reader>;


--
-- Name: TABLE kiinteisto_suojelutyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kiinteisto_suojelutyyppi TO <application_database_reader>;


--
-- Name: TABLE kiinteistokulttuurihistoriallinenarvo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kiinteistokulttuurihistoriallinenarvo TO <application_database_reader>;


--
-- Name: TABLE kokoelma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kokoelma TO <application_database_reader>;


--
-- Name: TABLE kori; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kori TO <application_database_reader>;


--
-- Name: TABLE korityyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.korityyppi TO <application_database_reader>;


--
-- Name: TABLE kunnat_kylat_2013; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kunnat_kylat_2013 TO <application_database_reader>;


--
-- Name: TABLE kunta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kunta TO <application_database_reader>;


--
-- Name: TABLE kunto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kunto TO <application_database_reader>;


--
-- Name: TABLE kuntotyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuntotyyppi TO <application_database_reader>;


--
-- Name: TABLE kuva; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva TO <application_database_reader>;


--
-- Name: TABLE kuva_alue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva_alue TO <application_database_reader>;


--
-- Name: TABLE kuva_arvoalue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva_arvoalue TO <application_database_reader>;


--
-- Name: TABLE kuva_kiinteisto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva_kiinteisto TO <application_database_reader>;


--
-- Name: TABLE kuva_kyla; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva_kyla TO <application_database_reader>;


--
-- Name: TABLE kuva_porrashuone; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva_porrashuone TO <application_database_reader>;


--
-- Name: TABLE kuva_rakennus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva_rakennus TO <application_database_reader>;


--
-- Name: TABLE kuva_suunnittelija; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kuva_suunnittelija TO <application_database_reader>;


--
-- Name: TABLE kyla; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.kyla TO <application_database_reader>;


--
-- Name: TABLE laatu; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.laatu TO <application_database_reader>;


--
-- Name: TABLE liite_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.liite_tyyppi TO <application_database_reader>;


--
-- Name: TABLE logged_actions; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.logged_actions TO <application_database_reader>;


--
-- Name: TABLE loyto_kategoria; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.loyto_kategoria TO <application_database_reader>;


--
-- Name: TABLE loyto_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.loyto_tyyppi TO <application_database_reader>;


--
-- Name: TABLE maastomerkinta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.maastomerkinta TO <application_database_reader>;


--
-- Name: TABLE materiaali; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.materiaali TO <application_database_reader>;


--
-- Name: TABLE matkaraportinsyy; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.matkaraportinsyy TO <application_database_reader>;


--
-- Name: TABLE matkaraportti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.matkaraportti TO <application_database_reader>;


--
-- Name: TABLE matkaraportti_syy; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.matkaraportti_syy TO <application_database_reader>;


--
-- Name: TABLE migrations; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.migrations TO <application_database_reader>;


--
-- Name: TABLE museo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.museo TO <application_database_reader>;


--
-- Name: TABLE muutoshistoria; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.muutoshistoria TO <application_database_reader>;


--
-- Name: TABLE muutoshistoria_tieto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.muutoshistoria_tieto TO <application_database_reader>;


--
-- Name: TABLE nayttely; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.nayttely TO <application_database_reader>;


--
-- Name: TABLE ocm_luokka; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.ocm_luokka TO <application_database_reader>;


--
-- Name: TABLE perustustyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.perustustyyppi TO <application_database_reader>;


--
-- Name: TABLE porrashuone; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.porrashuone TO <application_database_reader>;


--
-- Name: TABLE porrashuonetyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.porrashuonetyyppi TO <application_database_reader>;


--
-- Name: TABLE projekti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.projekti TO <application_database_reader>;


--
-- Name: TABLE projekti_kayttaja_rooli; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.projekti_kayttaja_rooli TO <application_database_reader>;


--
-- Name: TABLE projekti_rooli; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.projekti_rooli TO <application_database_reader>;


--
-- Name: TABLE projekti_sijainti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.projekti_sijainti TO <application_database_reader>;


--
-- Name: TABLE projekti_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.projekti_tyyppi TO <application_database_reader>;


--
-- Name: TABLE rajaustarkkuus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rajaustarkkuus TO <application_database_reader>;


--
-- Name: TABLE rakennus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus TO <application_database_reader>;


--
-- Name: TABLE rakennus_alkuperainenkaytto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_alkuperainenkaytto TO <application_database_reader>;


--
-- Name: TABLE rakennus_katetyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_katetyyppi TO <application_database_reader>;


--
-- Name: TABLE rakennus_kattotyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_kattotyyppi TO <application_database_reader>;


--
-- Name: TABLE rakennus_muutosvuosi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_muutosvuosi TO <application_database_reader>;


--
-- Name: TABLE rakennus_nykykaytto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_nykykaytto TO <application_database_reader>;


--
-- Name: TABLE rakennus_omistaja; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_omistaja TO <application_database_reader>;


--
-- Name: TABLE rakennus_osoite; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_osoite TO <application_database_reader>;


--
-- Name: TABLE rakennus_perustustyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_perustustyyppi TO <application_database_reader>;


--
-- Name: TABLE rakennus_rakennuskulttuurihistoriallinenarvo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_rakennuskulttuurihistoriallinenarvo TO <application_database_reader>;


--
-- Name: TABLE rakennus_rakennustyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_rakennustyyppi TO <application_database_reader>;


--
-- Name: TABLE rakennus_runkotyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_runkotyyppi TO <application_database_reader>;


--
-- Name: TABLE rakennus_suojelutyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_suojelutyyppi TO <application_database_reader>;


--
-- Name: TABLE rakennus_vuoraustyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennus_vuoraustyyppi TO <application_database_reader>;


--
-- Name: TABLE rakennuskulttuurihistoriallinenarvo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennuskulttuurihistoriallinenarvo TO <application_database_reader>;


--
-- Name: TABLE rakennustyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakennustyyppi TO <application_database_reader>;


--
-- Name: TABLE rakentaja_vanha; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rakentaja_vanha TO <application_database_reader>;


--
-- Name: TABLE rauhoitusluokka; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.rauhoitusluokka TO <application_database_reader>;


--
-- Name: TABLE runkotyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.runkotyyppi TO <application_database_reader>;


--
-- Name: TABLE suojelumerkinta_kohde; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suojelumerkinta_kohde TO <application_database_reader>;


--
-- Name: TABLE suojelutyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suojelutyyppi TO <application_database_reader>;


--
-- Name: TABLE suojelutyyppi_ryhma; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suojelutyyppi_ryhma TO <application_database_reader>;


--
-- Name: TABLE suunnittelija; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_ammattiarvo; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_ammattiarvo TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_vanha; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_vanha TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_laji; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_laji TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_porrashuone; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_porrashuone TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_porrashuone_vanha; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_porrashuone_vanha TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_rakennus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_rakennus TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_rakennus_vanha; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_rakennus_vanha TO <application_database_reader>;


--
-- Name: TABLE suunnittelija_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.suunnittelija_tyyppi TO <application_database_reader>;


--
-- Name: TABLE talteenottotapa; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.talteenottotapa TO <application_database_reader>;


--
-- Name: TABLE tekijanoikeuslauseke; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tekijanoikeuslauseke TO <application_database_reader>;


--
-- Name: TABLE tiedosto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto TO <application_database_reader>;


--
-- Name: TABLE tiedosto_alue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto_alue TO <application_database_reader>;


--
-- Name: TABLE tiedosto_arvoalue; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto_arvoalue TO <application_database_reader>;


--
-- Name: TABLE tiedosto_kiinteisto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto_kiinteisto TO <application_database_reader>;


--
-- Name: TABLE tiedosto_kunta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto_kunta TO <application_database_reader>;


--
-- Name: TABLE tiedosto_porrashuone; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto_porrashuone TO <application_database_reader>;


--
-- Name: TABLE tiedosto_rakennus; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto_rakennus TO <application_database_reader>;


--
-- Name: TABLE tiedosto_suunnittelija; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tiedosto_suunnittelija TO <application_database_reader>;


--
-- Name: TABLE tilatyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tilatyyppi TO <application_database_reader>;


--
-- Name: TABLE tyylisuunta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.tyylisuunta TO <application_database_reader>;


--
-- Name: TABLE valinnat; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.valinnat TO <application_database_reader>;


--
-- Name: TABLE varasto; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.varasto TO <application_database_reader>;


--
-- Name: TABLE vuoraustyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.vuoraustyyppi TO <application_database_reader>;


--
-- Name: TABLE wms_rajapinta; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.wms_rajapinta TO <application_database_reader>;


--
-- Name: TABLE yksikko; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko TO <application_database_reader>;


--
-- Name: TABLE yksikko_asiasana; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_asiasana TO <application_database_reader>;


--
-- Name: TABLE yksikko_kaivaustapa; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_kaivaustapa TO <application_database_reader>;


--
-- Name: TABLE yksikko_maalaji; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_maalaji TO <application_database_reader>;


--
-- Name: TABLE yksikko_muut_maalajit; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_muut_maalajit TO <application_database_reader>;


--
-- Name: TABLE yksikko_paasekoitteet; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_paasekoitteet TO <application_database_reader>;


--
-- Name: TABLE yksikko_seulontatapa; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_seulontatapa TO <application_database_reader>;


--
-- Name: TABLE yksikko_sijainti; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_sijainti TO <application_database_reader>;


--
-- Name: TABLE yksikko_talteenottotapa; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_talteenottotapa TO <application_database_reader>;


--
-- Name: TABLE yksikko_tyyppi; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikko_tyyppi TO <application_database_reader>;


--
-- Name: TABLE yksikon_elinkaari; Type: ACL; Schema: public; Owner: -
--

GRANT ALL ON TABLE public.yksikon_elinkaari TO <application_database_reader>;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: -
--

ALTER DEFAULT PRIVILEGES FOR ROLE <application_database_reader> IN SCHEMA public REVOKE ALL ON TABLES  FROM <application_database_reader>;
ALTER DEFAULT PRIVILEGES FOR ROLE <application_database_reader> IN SCHEMA public GRANT SELECT ON TABLES  TO <application_database_reader>;


--
-- PostgreSQL database dump complete
--

