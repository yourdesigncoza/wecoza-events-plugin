--
-- PostgreSQL database dump
--

\restrict BnhJZleNWpPd8wrXYf6qJY5ukCzweobMkTQdImaOSVPNCJdpdG22wgb2C8QSTbt

-- Dumped from database version 16.10
-- Dumped by pg_dump version 18.0 (Ubuntu 18.0-1.pgdg22.04+3)

-- Started on 2025-10-20 06:21:12 SAST

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 7 (class 2615 OID 19431)
-- Name: archive; Type: SCHEMA; Schema: -; Owner: doadmin
--

CREATE SCHEMA archive;


ALTER SCHEMA archive OWNER TO doadmin;

--
-- TOC entry 5 (class 2615 OID 17817)
-- Name: public; Type: SCHEMA; Schema: -; Owner: doadmin
--

-- *not* creating schema, since initdb creates it


ALTER SCHEMA public OWNER TO doadmin;

--
-- TOC entry 4951 (class 0 OID 0)
-- Dependencies: 5
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: doadmin
--

COMMENT ON SCHEMA public IS '';


--
-- TOC entry 6 (class 2615 OID 19083)
-- Name: wecoza_events; Type: SCHEMA; Schema: -; Owner: doadmin
--

CREATE SCHEMA wecoza_events;


ALTER SCHEMA wecoza_events OWNER TO doadmin;

--
-- TOC entry 4953 (class 0 OID 0)
-- Dependencies: 6
-- Name: SCHEMA wecoza_events; Type: COMMENT; Schema: -; Owner: doadmin
--

COMMENT ON SCHEMA wecoza_events IS 'WeCoza Events Plugin schema for notifications, events, and dashboard management';


--
-- TOC entry 318 (class 1255 OID 19420)
-- Name: fn_sites_same_client(); Type: FUNCTION; Schema: public; Owner: doadmin
--

CREATE FUNCTION public.fn_sites_same_client() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE parent_client_id INT;
BEGIN
  IF NEW.parent_site_id IS NULL THEN
    RETURN NEW;
  END IF;

  SELECT client_id INTO parent_client_id
  FROM public.sites
  WHERE site_id = NEW.parent_site_id;

  IF parent_client_id IS NULL THEN
    RAISE EXCEPTION 'Parent site % does not exist', NEW.parent_site_id;
  END IF;

  IF NEW.client_id <> parent_client_id THEN
    RAISE EXCEPTION 'Child (client_id=%) must match parent (client_id=%)',
      NEW.client_id, parent_client_id;
  END IF;

  RETURN NEW;
END$$;


ALTER FUNCTION public.fn_sites_same_client() OWNER TO doadmin;

--
-- TOC entry 317 (class 1255 OID 19349)
-- Name: log_class_change(); Type: FUNCTION; Schema: public; Owner: doadmin
--

CREATE FUNCTION public.log_class_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    op TEXT := TG_OP;
    event_time TIMESTAMP WITHOUT TIME ZONE := NOW();
    new_data JSONB := to_jsonb(NEW);
    old_data JSONB := CASE WHEN TG_OP = 'UPDATE' THEN to_jsonb(OLD) ELSE NULL END;
    diff JSONB := '{}'::jsonb;
BEGIN
    IF op = 'UPDATE' THEN
        diff := (
            SELECT COALESCE(
                jsonb_object_agg(key, jsonb_build_object('old', old_data -> key, 'new', new_data -> key)),
                '{}'::jsonb
            )
            FROM (
                SELECT key FROM jsonb_object_keys(new_data) AS new_keys(key)
                UNION
                SELECT key FROM jsonb_object_keys(COALESCE(old_data, '{}'::jsonb)) AS old_keys(key)
            ) AS keys(key)
            WHERE (old_data -> key) IS DISTINCT FROM (new_data -> key)
        );
    ELSE
        diff := new_data;
    END IF;

    INSERT INTO public.class_change_logs (class_id, operation, changed_at, new_row, old_row, diff)
    VALUES (NEW.class_id, op, event_time, new_data, old_data, diff);

    PERFORM pg_notify(
        'class_change_channel',
        json_build_object(
            'operation', op,
            'class_id', NEW.class_id,
            'class_code', NEW.class_code,
            'class_subject', NEW.class_subject,
            'changed_at', event_time,
            'diff', diff
        )::text
    );

    RETURN NEW;
END;
$$;


ALTER FUNCTION public.log_class_change() OWNER TO doadmin;

--
-- TOC entry 312 (class 1255 OID 18926)
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: doadmin
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_updated_at_column() OWNER TO doadmin;

--
-- TOC entry 316 (class 1255 OID 19234)
-- Name: get_dashboard_statistics(); Type: FUNCTION; Schema: wecoza_events; Owner: doadmin
--

CREATE FUNCTION wecoza_events.get_dashboard_statistics() RETURNS TABLE(total_supervisors bigint, active_supervisors bigint, pending_notifications bigint, processed_events bigint, pending_tasks bigint, overdue_tasks bigint)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        (SELECT COUNT(*) FROM supervisors) as total_supervisors,
        (SELECT COUNT(*) FROM supervisors WHERE is_active = TRUE) as active_supervisors,
        (SELECT COUNT(*) FROM notification_queue WHERE status = 'pending') as pending_notifications,
        (SELECT COUNT(*) FROM events_log WHERE processed = TRUE) as processed_events,
        (SELECT COUNT(*) FROM dashboard_status WHERE task_status = 'pending') as pending_tasks,
        (SELECT COUNT(*) FROM dashboard_status WHERE task_status = 'pending' AND due_date < CURRENT_TIMESTAMP) as overdue_tasks;
END;
$$;


ALTER FUNCTION wecoza_events.get_dashboard_statistics() OWNER TO doadmin;

--
-- TOC entry 314 (class 1255 OID 19232)
-- Name: get_pending_notifications(integer); Type: FUNCTION; Schema: wecoza_events; Owner: doadmin
--

CREATE FUNCTION wecoza_events.get_pending_notifications(limit_count integer DEFAULT 50) RETURNS TABLE(id integer, event_name character varying, recipient_email character varying, template_name character varying, payload jsonb, scheduled_at timestamp with time zone)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        nq.id,
        nq.event_name,
        nq.recipient_email,
        nq.template_name,
        nq.payload,
        nq.scheduled_at
    FROM notification_queue nq
    WHERE nq.status = 'pending'
        AND nq.scheduled_at <= CURRENT_TIMESTAMP
        AND nq.attempts < nq.max_attempts
    ORDER BY nq.scheduled_at ASC
    LIMIT limit_count;
END;
$$;


ALTER FUNCTION wecoza_events.get_pending_notifications(limit_count integer) OWNER TO doadmin;

--
-- TOC entry 315 (class 1255 OID 19233)
-- Name: get_unprocessed_events(integer); Type: FUNCTION; Schema: wecoza_events; Owner: doadmin
--

CREATE FUNCTION wecoza_events.get_unprocessed_events(limit_count integer DEFAULT 50) RETURNS TABLE(id integer, event_name character varying, event_payload jsonb, class_id integer, actor_id integer, idempotency_key character varying, occurred_at timestamp with time zone)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        el.id,
        el.event_name,
        el.event_payload,
        el.class_id,
        el.actor_id,
        el.idempotency_key,
        el.occurred_at
    FROM events_log el
    WHERE el.processed = FALSE
    ORDER BY el.occurred_at ASC
    LIMIT limit_count;
END;
$$;


ALTER FUNCTION wecoza_events.get_unprocessed_events(limit_count integer) OWNER TO doadmin;

--
-- TOC entry 313 (class 1255 OID 19227)
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: wecoza_events; Owner: doadmin
--

CREATE FUNCTION wecoza_events.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION wecoza_events.update_updated_at_column() OWNER TO doadmin;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 261 (class 1259 OID 18053)
-- Name: agent_absences; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.agent_absences (
    absence_id integer NOT NULL,
    agent_id integer,
    class_id integer,
    absence_date date,
    reason text,
    reported_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.agent_absences OWNER TO doadmin;

--
-- TOC entry 4954 (class 0 OID 0)
-- Dependencies: 261
-- Name: TABLE agent_absences; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.agent_absences IS 'Records instances when agents are absent from classes';


--
-- TOC entry 4955 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN agent_absences.absence_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_absences.absence_id IS 'Unique internal absence ID';


--
-- TOC entry 4956 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN agent_absences.agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_absences.agent_id IS 'Reference to the absent agent';


--
-- TOC entry 4957 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN agent_absences.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_absences.class_id IS 'Reference to the class affected by the absence';


--
-- TOC entry 4958 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN agent_absences.absence_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_absences.absence_date IS 'Date of the agent''s absence';


--
-- TOC entry 4959 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN agent_absences.reason; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_absences.reason IS 'Reason for the agent''s absence';


--
-- TOC entry 4960 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN agent_absences.reported_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_absences.reported_at IS 'Timestamp when the absence was reported';


--
-- TOC entry 260 (class 1259 OID 18052)
-- Name: agent_absences_absence_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.agent_absences_absence_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_absences_absence_id_seq OWNER TO doadmin;

--
-- TOC entry 4961 (class 0 OID 0)
-- Dependencies: 260
-- Name: agent_absences_absence_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.agent_absences_absence_id_seq OWNED BY public.agent_absences.absence_id;


--
-- TOC entry 241 (class 1259 OID 17932)
-- Name: agent_notes; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.agent_notes (
    note_id integer NOT NULL,
    agent_id integer,
    note text,
    note_date timestamp without time zone DEFAULT now()
);


ALTER TABLE public.agent_notes OWNER TO doadmin;

--
-- TOC entry 4962 (class 0 OID 0)
-- Dependencies: 241
-- Name: TABLE agent_notes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.agent_notes IS 'Stores historical notes and remarks about agents';


--
-- TOC entry 4963 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN agent_notes.note_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_notes.note_id IS 'Unique internal note ID';


--
-- TOC entry 4964 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN agent_notes.agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_notes.agent_id IS 'Reference to the agent';


--
-- TOC entry 4965 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN agent_notes.note; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_notes.note IS 'Content of the note regarding the agent';


--
-- TOC entry 4966 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN agent_notes.note_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_notes.note_date IS 'Timestamp when the note was created';


--
-- TOC entry 240 (class 1259 OID 17931)
-- Name: agent_notes_note_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.agent_notes_note_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_notes_note_id_seq OWNER TO doadmin;

--
-- TOC entry 4967 (class 0 OID 0)
-- Dependencies: 240
-- Name: agent_notes_note_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.agent_notes_note_id_seq OWNED BY public.agent_notes.note_id;


--
-- TOC entry 255 (class 1259 OID 18011)
-- Name: agent_orders; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.agent_orders (
    order_id integer NOT NULL,
    agent_id integer,
    class_id integer,
    order_number character varying(50),
    class_time time without time zone,
    class_days character varying(50),
    order_hours integer,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.agent_orders OWNER TO doadmin;

--
-- TOC entry 4968 (class 0 OID 0)
-- Dependencies: 255
-- Name: TABLE agent_orders; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.agent_orders IS 'Stores order information related to agents and classes';


--
-- TOC entry 4969 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.order_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.order_id IS 'Unique internal order ID';


--
-- TOC entry 4970 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.agent_id IS 'Reference to the agent';


--
-- TOC entry 4971 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.class_id IS 'Reference to the class';


--
-- TOC entry 4972 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.order_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.order_number IS 'Valid order number associated with the agent';


--
-- TOC entry 4973 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.class_time; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.class_time IS 'Time when the class is scheduled';


--
-- TOC entry 4974 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.class_days; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.class_days IS 'Days when the class is scheduled';


--
-- TOC entry 4975 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.order_hours; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.order_hours IS 'Number of hours linked to the agent''s order for a specific class';


--
-- TOC entry 4976 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.created_at IS 'Timestamp when the order record was created';


--
-- TOC entry 4977 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN agent_orders.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_orders.updated_at IS 'Timestamp when the order record was last updated';


--
-- TOC entry 254 (class 1259 OID 18010)
-- Name: agent_orders_order_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.agent_orders_order_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_orders_order_id_seq OWNER TO doadmin;

--
-- TOC entry 4978 (class 0 OID 0)
-- Dependencies: 254
-- Name: agent_orders_order_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.agent_orders_order_id_seq OWNED BY public.agent_orders.order_id;


--
-- TOC entry 263 (class 1259 OID 18063)
-- Name: agent_replacements; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.agent_replacements (
    replacement_id integer NOT NULL,
    class_id integer,
    original_agent_id integer,
    replacement_agent_id integer,
    start_date date,
    end_date date,
    reason text
);


ALTER TABLE public.agent_replacements OWNER TO doadmin;

--
-- TOC entry 4979 (class 0 OID 0)
-- Dependencies: 263
-- Name: TABLE agent_replacements; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.agent_replacements IS 'Records instances of agent replacements in classes';


--
-- TOC entry 4980 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN agent_replacements.replacement_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_replacements.replacement_id IS 'Unique internal replacement ID';


--
-- TOC entry 4981 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN agent_replacements.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_replacements.class_id IS 'Reference to the class';


--
-- TOC entry 4982 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN agent_replacements.original_agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_replacements.original_agent_id IS 'Reference to the original agent';


--
-- TOC entry 4983 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN agent_replacements.replacement_agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_replacements.replacement_agent_id IS 'Reference to the replacement agent';


--
-- TOC entry 4984 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN agent_replacements.start_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_replacements.start_date IS 'Date when the replacement starts';


--
-- TOC entry 4985 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN agent_replacements.end_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_replacements.end_date IS 'Date when the replacement ends';


--
-- TOC entry 4986 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN agent_replacements.reason; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agent_replacements.reason IS 'Reason for the agent''s replacement';


--
-- TOC entry 262 (class 1259 OID 18062)
-- Name: agent_replacements_replacement_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.agent_replacements_replacement_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_replacements_replacement_id_seq OWNER TO doadmin;

--
-- TOC entry 4987 (class 0 OID 0)
-- Dependencies: 262
-- Name: agent_replacements_replacement_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.agent_replacements_replacement_id_seq OWNED BY public.agent_replacements.replacement_id;


--
-- TOC entry 224 (class 1259 OID 17845)
-- Name: agents; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.agents (
    agent_id integer NOT NULL,
    first_name character varying(50),
    initials character varying(10),
    surname character varying(50),
    gender character varying(10),
    race character varying(20),
    sa_id_no character varying(20),
    passport_number character varying(20),
    tel_number character varying(20),
    email_address character varying(100),
    residential_address_line character varying(100),
    residential_suburb character varying(50),
    residential_postal_code character varying(10),
    preferred_working_area_1 integer,
    preferred_working_area_2 integer,
    preferred_working_area_3 integer,
    highest_qualification character varying(100),
    sace_number character varying(50),
    sace_registration_date date,
    sace_expiry_date date,
    quantum_assessment numeric(5,2),
    agent_training_date date,
    bank_name character varying(50),
    bank_branch_code character varying(20),
    bank_account_number character varying(30),
    signed_agreement_date date,
    agent_notes text,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    title character varying(50),
    id_type character varying(20) DEFAULT 'sa_id'::character varying,
    address_line_2 character varying(255),
    criminal_record_date date,
    criminal_record_file character varying(500),
    province character varying(100),
    city character varying(100),
    phase_registered character varying(50),
    subjects_registered text,
    account_holder character varying(100),
    account_type character varying(50),
    status character varying(50) DEFAULT 'active'::character varying,
    created_by integer,
    updated_by integer,
    second_name character varying(50),
    signed_agreement_file character varying(255),
    quantum_maths_score integer DEFAULT 0,
    quantum_science_score integer DEFAULT 0,
    CONSTRAINT agents_account_type_check CHECK (((account_type)::text = ANY ((ARRAY['Savings'::character varying, 'Current'::character varying, 'Transmission'::character varying])::text[]))),
    CONSTRAINT agents_gender_check CHECK (((gender)::text = ANY ((ARRAY['M'::character varying, 'F'::character varying, 'Male'::character varying, 'Female'::character varying])::text[]))),
    CONSTRAINT agents_id_type_check CHECK (((id_type)::text = ANY ((ARRAY['sa_id'::character varying, 'passport'::character varying])::text[]))),
    CONSTRAINT agents_phase_registered_check CHECK (((phase_registered)::text = ANY ((ARRAY['Foundation'::character varying, 'Intermediate'::character varying, 'Senior'::character varying, 'FET'::character varying])::text[]))),
    CONSTRAINT agents_race_check CHECK (((race)::text = ANY ((ARRAY['African'::character varying, 'Coloured'::character varying, 'White'::character varying, 'Indian'::character varying])::text[]))),
    CONSTRAINT agents_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying, 'suspended'::character varying, 'deleted'::character varying])::text[]))),
    CONSTRAINT agents_title_check CHECK (((title)::text = ANY ((ARRAY['Mr'::character varying, 'Mrs'::character varying, 'Ms'::character varying, 'Miss'::character varying, 'Dr'::character varying, 'Prof'::character varying])::text[]))),
    CONSTRAINT quantum_maths_score_range CHECK (((quantum_maths_score >= 0) AND (quantum_maths_score <= 100))),
    CONSTRAINT quantum_science_score_range CHECK (((quantum_science_score >= 0) AND (quantum_science_score <= 100)))
);


ALTER TABLE public.agents OWNER TO doadmin;

--
-- TOC entry 4988 (class 0 OID 0)
-- Dependencies: 224
-- Name: TABLE agents; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.agents IS 'Stores information about agents (instructors or facilitators)';


--
-- TOC entry 4989 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.agent_id IS 'Unique internal agent ID';


--
-- TOC entry 4990 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.first_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.first_name IS 'Agent''s first name';


--
-- TOC entry 4991 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.initials; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.initials IS 'Agent''s initials';


--
-- TOC entry 4992 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.surname; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.surname IS 'Agent''s surname';


--
-- TOC entry 4993 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.gender; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.gender IS 'Agent''s gender';


--
-- TOC entry 4994 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.race; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.race IS 'Agent''s race; options include ''African'', ''Coloured'', ''White'', ''Indian''';


--
-- TOC entry 4995 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.sa_id_no; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.sa_id_no IS 'Agent''s South African ID number';


--
-- TOC entry 4996 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.passport_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.passport_number IS 'Agent''s passport number if they are a foreigner';


--
-- TOC entry 4997 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.tel_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.tel_number IS 'Agent''s primary telephone number';


--
-- TOC entry 4998 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.email_address; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.email_address IS 'Agent''s email address';


--
-- TOC entry 4999 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.residential_address_line; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.residential_address_line IS 'Agent''s residential street address';


--
-- TOC entry 5000 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.residential_suburb; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.residential_suburb IS 'Agent''s residential suburb';


--
-- TOC entry 5001 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.residential_postal_code; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.residential_postal_code IS 'Postal code of the agent''s residential area';


--
-- TOC entry 5002 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.preferred_working_area_1; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.preferred_working_area_1 IS 'Agent''s first preferred working area';


--
-- TOC entry 5003 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.preferred_working_area_2; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.preferred_working_area_2 IS 'Agent''s second preferred working area';


--
-- TOC entry 5004 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.preferred_working_area_3; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.preferred_working_area_3 IS 'Agent''s third preferred working area';


--
-- TOC entry 5005 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.highest_qualification; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.highest_qualification IS 'Highest qualification the agent has achieved';


--
-- TOC entry 5006 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.sace_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.sace_number IS 'Agent''s SACE (South African Council for Educators) registration number';


--
-- TOC entry 5007 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.sace_registration_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.sace_registration_date IS 'Date when the agent''s SACE registration became effective';


--
-- TOC entry 5008 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.sace_expiry_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.sace_expiry_date IS 'Expiry date of the agent''s provisional SACE registration';


--
-- TOC entry 5009 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.quantum_assessment; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.quantum_assessment IS 'Agent''s competence score in Communications (percentage)';


--
-- TOC entry 5010 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.agent_training_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.agent_training_date IS 'Date when the agent received induction training';


--
-- TOC entry 5011 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.bank_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.bank_name IS 'Name of the agent''s bank';


--
-- TOC entry 5012 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.bank_branch_code; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.bank_branch_code IS 'Branch code of the agent''s bank';


--
-- TOC entry 5013 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.bank_account_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.bank_account_number IS 'Agent''s bank account number';


--
-- TOC entry 5014 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.signed_agreement_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.signed_agreement_date IS 'Date when the agent signed the agreement';


--
-- TOC entry 5015 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.agent_notes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.agent_notes IS 'Notes regarding the agent''s performance, issues, or other relevant information';


--
-- TOC entry 5016 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.created_at IS 'Timestamp when the agent record was created';


--
-- TOC entry 5017 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.updated_at IS 'Timestamp when the agent record was last updated';


--
-- TOC entry 5018 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.title; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.title IS 'Agent''s title (Mr, Mrs, Ms, etc)';


--
-- TOC entry 5019 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.id_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.id_type IS 'Type of identification: sa_id or passport';


--
-- TOC entry 5020 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.address_line_2; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.address_line_2 IS 'Additional address information';


--
-- TOC entry 5021 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.criminal_record_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.criminal_record_date IS 'Date of criminal record check';


--
-- TOC entry 5022 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.criminal_record_file; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.criminal_record_file IS 'Path to criminal record check file';


--
-- TOC entry 5023 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.province; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.province IS 'Province where the agent resides';


--
-- TOC entry 5024 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.city; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.city IS 'City where the agent resides';


--
-- TOC entry 5025 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.phase_registered; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.phase_registered IS 'Educational phase the agent is registered for';


--
-- TOC entry 5026 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.subjects_registered; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.subjects_registered IS 'Subjects the agent is registered to teach';


--
-- TOC entry 5027 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.account_holder; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.account_holder IS 'Name of the bank account holder';


--
-- TOC entry 5028 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.account_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.account_type IS 'Type of bank account (Savings, Current, etc)';


--
-- TOC entry 5029 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.status; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.status IS 'Current status of the agent';


--
-- TOC entry 5030 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.created_by; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.created_by IS 'User ID who created the record';


--
-- TOC entry 5031 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.updated_by; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.updated_by IS 'User ID who last updated the record';


--
-- TOC entry 5032 (class 0 OID 0)
-- Dependencies: 224
-- Name: COLUMN agents.second_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.agents.second_name IS 'Second name of the agent (middle name)';


--
-- TOC entry 223 (class 1259 OID 17844)
-- Name: agents_agent_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.agents_agent_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agents_agent_id_seq OWNER TO doadmin;

--
-- TOC entry 5033 (class 0 OID 0)
-- Dependencies: 223
-- Name: agents_agent_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.agents_agent_id_seq OWNED BY public.agents.agent_id;


--
-- TOC entry 245 (class 1259 OID 17952)
-- Name: attendance_registers; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.attendance_registers (
    register_id integer NOT NULL,
    class_id integer,
    date date,
    agent_id integer,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.attendance_registers OWNER TO doadmin;

--
-- TOC entry 5034 (class 0 OID 0)
-- Dependencies: 245
-- Name: TABLE attendance_registers; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.attendance_registers IS 'Records attendance registers for classes';


--
-- TOC entry 5035 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN attendance_registers.register_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.attendance_registers.register_id IS 'Unique internal attendance register ID';


--
-- TOC entry 5036 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN attendance_registers.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.attendance_registers.class_id IS 'Reference to the class';


--
-- TOC entry 5037 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN attendance_registers.date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.attendance_registers.date IS 'Date of the attendance';


--
-- TOC entry 5038 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN attendance_registers.agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.attendance_registers.agent_id IS 'Reference to the agent who conducted the attendance';


--
-- TOC entry 5039 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN attendance_registers.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.attendance_registers.created_at IS 'Timestamp when the attendance register was created';


--
-- TOC entry 5040 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN attendance_registers.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.attendance_registers.updated_at IS 'Timestamp when the attendance register was last updated';


--
-- TOC entry 244 (class 1259 OID 17951)
-- Name: attendance_registers_register_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.attendance_registers_register_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_registers_register_id_seq OWNER TO doadmin;

--
-- TOC entry 5041 (class 0 OID 0)
-- Dependencies: 244
-- Name: attendance_registers_register_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.attendance_registers_register_id_seq OWNED BY public.attendance_registers.register_id;


--
-- TOC entry 239 (class 1259 OID 17926)
-- Name: class_agents; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.class_agents (
    class_id integer NOT NULL,
    agent_id integer NOT NULL,
    start_date date NOT NULL,
    end_date date,
    role character varying(50)
);


ALTER TABLE public.class_agents OWNER TO doadmin;

--
-- TOC entry 5042 (class 0 OID 0)
-- Dependencies: 239
-- Name: TABLE class_agents; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.class_agents IS 'Associates agents with classes they facilitate, including their roles and durations';


--
-- TOC entry 5043 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN class_agents.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_agents.class_id IS 'Reference to the class';


--
-- TOC entry 5044 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN class_agents.agent_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_agents.agent_id IS 'Reference to the agent facilitating the class';


--
-- TOC entry 5045 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN class_agents.start_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_agents.start_date IS 'Date when the agent started facilitating the class';


--
-- TOC entry 5046 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN class_agents.end_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_agents.end_date IS 'Date when the agent stopped facilitating the class';


--
-- TOC entry 5047 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN class_agents.role; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_agents.role IS 'Role of the agent in the class (e.g., ''Original'', ''Backup'', ''Replacement'')';


--
-- TOC entry 296 (class 1259 OID 19451)
-- Name: class_change_logs; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.class_change_logs (
    log_id bigint NOT NULL,
    class_id integer,
    operation text NOT NULL,
    changed_at timestamp without time zone DEFAULT now() NOT NULL,
    new_row jsonb,
    old_row jsonb,
    diff jsonb,
    tasks jsonb DEFAULT '[]'::jsonb
);


ALTER TABLE public.class_change_logs OWNER TO doadmin;

--
-- TOC entry 295 (class 1259 OID 19450)
-- Name: class_change_logs_log_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.class_change_logs_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_change_logs_log_id_seq OWNER TO doadmin;

--
-- TOC entry 5048 (class 0 OID 0)
-- Dependencies: 295
-- Name: class_change_logs_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.class_change_logs_log_id_seq OWNED BY public.class_change_logs.log_id;


--
-- TOC entry 243 (class 1259 OID 17942)
-- Name: class_notes; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.class_notes (
    note_id integer NOT NULL,
    class_id integer,
    note text,
    note_date timestamp without time zone DEFAULT now()
);


ALTER TABLE public.class_notes OWNER TO doadmin;

--
-- TOC entry 5049 (class 0 OID 0)
-- Dependencies: 243
-- Name: TABLE class_notes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.class_notes IS 'Stores historical notes and remarks about classes';


--
-- TOC entry 5050 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN class_notes.note_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_notes.note_id IS 'Unique internal note ID';


--
-- TOC entry 5051 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN class_notes.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_notes.class_id IS 'Reference to the class';


--
-- TOC entry 5052 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN class_notes.note; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_notes.note IS 'Content of the note regarding the class';


--
-- TOC entry 5053 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN class_notes.note_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_notes.note_date IS 'Timestamp when the note was created';


--
-- TOC entry 242 (class 1259 OID 17941)
-- Name: class_notes_note_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.class_notes_note_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_notes_note_id_seq OWNER TO doadmin;

--
-- TOC entry 5054 (class 0 OID 0)
-- Dependencies: 242
-- Name: class_notes_note_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.class_notes_note_id_seq OWNED BY public.class_notes.note_id;


--
-- TOC entry 237 (class 1259 OID 17915)
-- Name: class_schedules; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.class_schedules (
    schedule_id integer NOT NULL,
    class_id integer,
    day_of_week character varying(10),
    start_time time without time zone,
    end_time time without time zone
);


ALTER TABLE public.class_schedules OWNER TO doadmin;

--
-- TOC entry 5055 (class 0 OID 0)
-- Dependencies: 237
-- Name: TABLE class_schedules; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.class_schedules IS 'Stores scheduling information for classes';


--
-- TOC entry 5056 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN class_schedules.schedule_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_schedules.schedule_id IS 'Unique internal schedule ID';


--
-- TOC entry 5057 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN class_schedules.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_schedules.class_id IS 'Reference to the class';


--
-- TOC entry 5058 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN class_schedules.day_of_week; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_schedules.day_of_week IS 'Day of the week when the class occurs (e.g., ''Monday'')';


--
-- TOC entry 5059 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN class_schedules.start_time; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_schedules.start_time IS 'Class start time';


--
-- TOC entry 5060 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN class_schedules.end_time; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_schedules.end_time IS 'Class end time';


--
-- TOC entry 236 (class 1259 OID 17914)
-- Name: class_schedules_schedule_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.class_schedules_schedule_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_schedules_schedule_id_seq OWNER TO doadmin;

--
-- TOC entry 5061 (class 0 OID 0)
-- Dependencies: 236
-- Name: class_schedules_schedule_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.class_schedules_schedule_id_seq OWNED BY public.class_schedules.schedule_id;


--
-- TOC entry 238 (class 1259 OID 17921)
-- Name: class_subjects; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.class_subjects (
    class_id integer NOT NULL,
    product_id integer NOT NULL
);


ALTER TABLE public.class_subjects OWNER TO doadmin;

--
-- TOC entry 5062 (class 0 OID 0)
-- Dependencies: 238
-- Name: TABLE class_subjects; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.class_subjects IS 'Associates classes with the subjects or products being taught';


--
-- TOC entry 5063 (class 0 OID 0)
-- Dependencies: 238
-- Name: COLUMN class_subjects.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_subjects.class_id IS 'Reference to the class';


--
-- TOC entry 5064 (class 0 OID 0)
-- Dependencies: 238
-- Name: COLUMN class_subjects.product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.class_subjects.product_id IS 'Reference to the subject or product taught in the class';


--
-- TOC entry 226 (class 1259 OID 17856)
-- Name: classes; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.classes (
    class_id integer NOT NULL,
    client_id integer,
    class_address_line character varying(100),
    class_type character varying(50),
    original_start_date date,
    seta_funded boolean,
    seta character varying(100),
    exam_class boolean,
    exam_type character varying(50),
    project_supervisor_id integer,
    delivery_date date,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    site_id integer,
    class_subject character varying(100),
    class_code character varying(50),
    class_duration integer,
    class_agent integer,
    learner_ids jsonb DEFAULT '[]'::jsonb,
    backup_agent_ids jsonb DEFAULT '[]'::jsonb,
    schedule_data jsonb DEFAULT '[]'::jsonb,
    stop_restart_dates jsonb DEFAULT '[]'::jsonb,
    class_notes_data jsonb DEFAULT '[]'::jsonb,
    initial_class_agent integer,
    initial_agent_start_date date,
    exam_learners jsonb DEFAULT '[]'::jsonb,
    order_nr character varying
);


ALTER TABLE public.classes OWNER TO doadmin;

--
-- TOC entry 5065 (class 0 OID 0)
-- Dependencies: 226
-- Name: TABLE classes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.classes IS 'Stores information about classes, including scheduling and associations';


--
-- TOC entry 5066 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.class_id IS 'Unique internal class ID';


--
-- TOC entry 5067 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.client_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.client_id IS 'Reference to the client associated with the class';


--
-- TOC entry 5068 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.class_address_line; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.class_address_line IS 'Street address where the class takes place';


--
-- TOC entry 5069 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.class_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.class_type IS 'Type of class; determines the ''rules'' (e.g., ''Employed'', ''Community'')';


--
-- TOC entry 5070 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.original_start_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.original_start_date IS 'Original start date of the class';


--
-- TOC entry 5071 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.seta_funded; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.seta_funded IS 'Indicates if the project is SETA funded (true) or not (false)';


--
-- TOC entry 5072 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.seta; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.seta IS 'Name of the SETA (Sector Education and Training Authority) the client belongs to';


--
-- TOC entry 5073 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.exam_class; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.exam_class IS 'Indicates if this is an exam project (true) or not (false)';


--
-- TOC entry 5074 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.exam_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.exam_type IS 'Type of exam associated with the class';


--
-- TOC entry 5075 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.project_supervisor_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.project_supervisor_id IS 'Reference to the project supervisor managing the class';


--
-- TOC entry 5076 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.delivery_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.delivery_date IS 'Date when materials or resources must be delivered to the class';


--
-- TOC entry 5077 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.created_at IS 'Timestamp when the class record was created';


--
-- TOC entry 5078 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.updated_at IS 'Timestamp when the class record was last updated';


--
-- TOC entry 5079 (class 0 OID 0)
-- Dependencies: 226
-- Name: COLUMN classes.exam_learners; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.exam_learners IS 'JSON array storing exam learner IDs and 
  metadata for learners taking exams';


--
-- TOC entry 225 (class 1259 OID 17855)
-- Name: classes_class_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.classes_class_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.classes_class_id_seq OWNER TO doadmin;

--
-- TOC entry 5080 (class 0 OID 0)
-- Dependencies: 225
-- Name: classes_class_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.classes_class_id_seq OWNED BY public.classes.class_id;


--
-- TOC entry 265 (class 1259 OID 18072)
-- Name: client_communications; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.client_communications (
    communication_id integer NOT NULL,
    client_id integer,
    communication_type character varying(50),
    subject character varying(100),
    content text,
    communication_date timestamp without time zone DEFAULT now(),
    user_id integer,
    site_id integer
);


ALTER TABLE public.client_communications OWNER TO doadmin;

--
-- TOC entry 5081 (class 0 OID 0)
-- Dependencies: 265
-- Name: TABLE client_communications; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.client_communications IS 'Stores records of communications with clients';


--
-- TOC entry 5082 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN client_communications.communication_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.client_communications.communication_id IS 'Unique internal communication ID';


--
-- TOC entry 5083 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN client_communications.client_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.client_communications.client_id IS 'Reference to the client';


--
-- TOC entry 5084 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN client_communications.communication_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.client_communications.communication_type IS 'Type of communication (e.g., ''Email'', ''Phone Call'')';


--
-- TOC entry 5085 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN client_communications.subject; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.client_communications.subject IS 'Subject of the communication';


--
-- TOC entry 5086 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN client_communications.content; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.client_communications.content IS 'Content or summary of the communication';


--
-- TOC entry 5087 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN client_communications.communication_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.client_communications.communication_date IS 'Date and time when the communication occurred';


--
-- TOC entry 5088 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN client_communications.user_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.client_communications.user_id IS 'Reference to the user who communicated with the client';


--
-- TOC entry 264 (class 1259 OID 18071)
-- Name: client_communications_communication_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.client_communications_communication_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_communications_communication_id_seq OWNER TO doadmin;

--
-- TOC entry 5089 (class 0 OID 0)
-- Dependencies: 264
-- Name: client_communications_communication_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.client_communications_communication_id_seq OWNED BY public.client_communications.communication_id;


--
-- TOC entry 228 (class 1259 OID 17867)
-- Name: clients; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.clients (
    client_id integer NOT NULL,
    client_name character varying(100),
    company_registration_number character varying(50),
    seta character varying(100),
    client_status character varying(50),
    financial_year_end date,
    bbbee_verification_date date,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    main_client_id integer,
    contact_person character varying(100),
    contact_person_email character varying(100),
    contact_person_cellphone character varying(20),
    contact_person_tel character varying(20),
    contact_person_position character varying(50)
);


ALTER TABLE public.clients OWNER TO doadmin;

--
-- TOC entry 5090 (class 0 OID 0)
-- Dependencies: 228
-- Name: TABLE clients; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.clients IS 'Stores information about clients (companies or organizations)';


--
-- TOC entry 5091 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.client_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.client_id IS 'Unique internal client ID';


--
-- TOC entry 5092 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.client_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.client_name IS 'Name of the client company or organization';


--
-- TOC entry 5093 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.company_registration_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.company_registration_number IS 'Company registration number of the client';


--
-- TOC entry 5094 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.seta; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.seta IS 'SETA the client belongs to';


--
-- TOC entry 5095 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.client_status; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.client_status IS 'Current status of the client (e.g., ''Active Client'', ''Lost Client'')';


--
-- TOC entry 5096 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.financial_year_end; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.financial_year_end IS 'Date of the client''s financial year-end';


--
-- TOC entry 5097 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.bbbee_verification_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.bbbee_verification_date IS 'Date of the client''s BBBEE verification';


--
-- TOC entry 5098 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.created_at IS 'Timestamp when the client record was created';


--
-- TOC entry 5099 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.updated_at IS 'Timestamp when the client record was last updated';


--
-- TOC entry 5100 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.main_client_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.main_client_id IS 'Reference to the 
main client for sub-client relationships (NULL for main clients)';


--
-- TOC entry 5101 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.contact_person; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.contact_person IS 'Primary contact person name (consolidated approach for new clients)';


--
-- TOC entry 5102 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.contact_person_email; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.contact_person_email IS 'Primary contact person email';


--
-- TOC entry 5103 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.contact_person_cellphone; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.contact_person_cellphone IS 'Primary contact person cellphone';


--
-- TOC entry 5104 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.contact_person_tel; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.contact_person_tel IS 'Primary contact person landline';


--
-- TOC entry 5105 (class 0 OID 0)
-- Dependencies: 228
-- Name: COLUMN clients.contact_person_position; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.clients.contact_person_position IS 'Primary contact person job position';


--
-- TOC entry 227 (class 1259 OID 17866)
-- Name: clients_client_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.clients_client_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.clients_client_id_seq OWNER TO doadmin;

--
-- TOC entry 5106 (class 0 OID 0)
-- Dependencies: 227
-- Name: clients_client_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.clients_client_id_seq OWNED BY public.clients.client_id;


--
-- TOC entry 259 (class 1259 OID 18031)
-- Name: collections; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.collections (
    collection_id integer NOT NULL,
    class_id integer,
    collection_date date,
    items text,
    status character varying(20),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.collections OWNER TO doadmin;

--
-- TOC entry 5107 (class 0 OID 0)
-- Dependencies: 259
-- Name: TABLE collections; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.collections IS 'Records collections made from classes';


--
-- TOC entry 5108 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN collections.collection_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.collections.collection_id IS 'Unique internal collection ID';


--
-- TOC entry 5109 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN collections.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.collections.class_id IS 'Reference to the class';


--
-- TOC entry 5110 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN collections.collection_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.collections.collection_date IS 'Date when the collection is scheduled or occurred';


--
-- TOC entry 5111 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN collections.items; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.collections.items IS 'Items collected from the class';


--
-- TOC entry 5112 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN collections.status; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.collections.status IS 'Collection status (e.g., ''Pending'', ''Collected'')';


--
-- TOC entry 5113 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN collections.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.collections.created_at IS 'Timestamp when the collection record was created';


--
-- TOC entry 5114 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN collections.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.collections.updated_at IS 'Timestamp when the collection record was last updated';


--
-- TOC entry 258 (class 1259 OID 18030)
-- Name: collections_collection_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.collections_collection_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.collections_collection_id_seq OWNER TO doadmin;

--
-- TOC entry 5115 (class 0 OID 0)
-- Dependencies: 258
-- Name: collections_collection_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.collections_collection_id_seq OWNED BY public.collections.collection_id;


--
-- TOC entry 257 (class 1259 OID 18020)
-- Name: deliveries; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.deliveries (
    delivery_id integer NOT NULL,
    class_id integer,
    delivery_date date,
    items text,
    status character varying(20),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.deliveries OWNER TO doadmin;

--
-- TOC entry 5116 (class 0 OID 0)
-- Dependencies: 257
-- Name: TABLE deliveries; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.deliveries IS 'Records deliveries made to classes';


--
-- TOC entry 5117 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN deliveries.delivery_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.deliveries.delivery_id IS 'Unique internal delivery ID';


--
-- TOC entry 5118 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN deliveries.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.deliveries.class_id IS 'Reference to the class';


--
-- TOC entry 5119 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN deliveries.delivery_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.deliveries.delivery_date IS 'Date when the delivery is scheduled or occurred';


--
-- TOC entry 5120 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN deliveries.items; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.deliveries.items IS 'Items included in the delivery';


--
-- TOC entry 5121 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN deliveries.status; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.deliveries.status IS 'Delivery status (e.g., ''Pending'', ''Delivered'')';


--
-- TOC entry 5122 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN deliveries.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.deliveries.created_at IS 'Timestamp when the delivery record was created';


--
-- TOC entry 5123 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN deliveries.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.deliveries.updated_at IS 'Timestamp when the delivery record was last updated';


--
-- TOC entry 256 (class 1259 OID 18019)
-- Name: deliveries_delivery_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.deliveries_delivery_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.deliveries_delivery_id_seq OWNER TO doadmin;

--
-- TOC entry 5124 (class 0 OID 0)
-- Dependencies: 256
-- Name: deliveries_delivery_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.deliveries_delivery_id_seq OWNED BY public.deliveries.delivery_id;


--
-- TOC entry 234 (class 1259 OID 17896)
-- Name: employers; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.employers (
    employer_id integer NOT NULL,
    employer_name character varying(100),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.employers OWNER TO doadmin;

--
-- TOC entry 5125 (class 0 OID 0)
-- Dependencies: 234
-- Name: TABLE employers; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.employers IS 'Stores information about employers or sponsors of learners';


--
-- TOC entry 5126 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN employers.employer_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.employers.employer_id IS 'Unique internal employer ID';


--
-- TOC entry 5127 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN employers.employer_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.employers.employer_name IS 'Name of the employer or sponsoring organization';


--
-- TOC entry 5128 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN employers.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.employers.created_at IS 'Timestamp when the employer record was created';


--
-- TOC entry 5129 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN employers.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.employers.updated_at IS 'Timestamp when the employer record was last updated';


--
-- TOC entry 233 (class 1259 OID 17895)
-- Name: employers_employer_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.employers_employer_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.employers_employer_id_seq OWNER TO doadmin;

--
-- TOC entry 5130 (class 0 OID 0)
-- Dependencies: 233
-- Name: employers_employer_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.employers_employer_id_seq OWNED BY public.employers.employer_id;


--
-- TOC entry 267 (class 1259 OID 18082)
-- Name: exam_results; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.exam_results (
    result_id integer NOT NULL,
    exam_id integer,
    learner_id integer,
    subject character varying(100),
    mock_exam_number integer,
    score numeric(5,2),
    result character varying(20),
    exam_date date,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.exam_results OWNER TO doadmin;

--
-- TOC entry 5131 (class 0 OID 0)
-- Dependencies: 267
-- Name: TABLE exam_results; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.exam_results IS 'Stores detailed exam results for learners';


--
-- TOC entry 5132 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.result_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.result_id IS 'Unique internal exam result ID';


--
-- TOC entry 5133 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.exam_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.exam_id IS 'Reference to the exam';


--
-- TOC entry 5134 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.learner_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.learner_id IS 'Reference to the learner';


--
-- TOC entry 5135 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.subject; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.subject IS 'Subject of the exam';


--
-- TOC entry 5136 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.mock_exam_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.mock_exam_number IS 'Number of the mock exam (e.g., 1, 2, 3)';


--
-- TOC entry 5137 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.score; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.score IS 'Learner''s score in the exam';


--
-- TOC entry 5138 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.result; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.result IS 'Exam result (e.g., ''Pass'', ''Fail'')';


--
-- TOC entry 5139 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.exam_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.exam_date IS 'Date when the exam was taken';


--
-- TOC entry 5140 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.created_at IS 'Timestamp when the exam result was created';


--
-- TOC entry 5141 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN exam_results.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exam_results.updated_at IS 'Timestamp when the exam result was last updated';


--
-- TOC entry 266 (class 1259 OID 18081)
-- Name: exam_results_result_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.exam_results_result_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.exam_results_result_id_seq OWNER TO doadmin;

--
-- TOC entry 5142 (class 0 OID 0)
-- Dependencies: 266
-- Name: exam_results_result_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.exam_results_result_id_seq OWNED BY public.exam_results.result_id;


--
-- TOC entry 249 (class 1259 OID 17977)
-- Name: exams; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.exams (
    exam_id integer NOT NULL,
    learner_id integer,
    product_id integer,
    exam_date date,
    exam_type character varying(50),
    score numeric(5,2),
    result character varying(20),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.exams OWNER TO doadmin;

--
-- TOC entry 5143 (class 0 OID 0)
-- Dependencies: 249
-- Name: TABLE exams; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.exams IS 'Stores exam results for learners';


--
-- TOC entry 5144 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.exam_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.exam_id IS 'Unique internal exam ID';


--
-- TOC entry 5145 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.learner_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.learner_id IS 'Reference to the learner';


--
-- TOC entry 5146 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.product_id IS 'Reference to the product or subject';


--
-- TOC entry 5147 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.exam_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.exam_date IS 'Date when the exam was taken';


--
-- TOC entry 5148 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.exam_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.exam_type IS 'Type of exam (e.g., ''Mock'', ''Final'')';


--
-- TOC entry 5149 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.score; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.score IS 'Learner''s score in the exam';


--
-- TOC entry 5150 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.result; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.result IS 'Exam result (e.g., ''Pass'', ''Fail'')';


--
-- TOC entry 5151 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.created_at IS 'Timestamp when the exam record was created';


--
-- TOC entry 5152 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exams.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.exams.updated_at IS 'Timestamp when the exam record was last updated';


--
-- TOC entry 248 (class 1259 OID 17976)
-- Name: exams_exam_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.exams_exam_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.exams_exam_id_seq OWNER TO doadmin;

--
-- TOC entry 5153 (class 0 OID 0)
-- Dependencies: 248
-- Name: exams_exam_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.exams_exam_id_seq OWNED BY public.exams.exam_id;


--
-- TOC entry 251 (class 1259 OID 17993)
-- Name: files; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.files (
    file_id integer NOT NULL,
    owner_type character varying(50),
    owner_id integer,
    file_path character varying(255),
    file_type character varying(50),
    uploaded_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.files OWNER TO doadmin;

--
-- TOC entry 5154 (class 0 OID 0)
-- Dependencies: 251
-- Name: TABLE files; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.files IS 'Stores references to files associated with various entities';


--
-- TOC entry 5155 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN files.file_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.files.file_id IS 'Unique internal file ID';


--
-- TOC entry 5156 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN files.owner_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.files.owner_type IS 'Type of entity that owns the file (e.g., ''Learner'', ''Class'', ''Agent'')';


--
-- TOC entry 5157 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN files.owner_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.files.owner_id IS 'ID of the owner entity';


--
-- TOC entry 5158 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN files.file_path; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.files.file_path IS 'File path or URL to the stored file';


--
-- TOC entry 5159 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN files.file_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.files.file_type IS 'Type of file (e.g., ''Scanned Portfolio'', ''QA Report'')';


--
-- TOC entry 5160 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN files.uploaded_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.files.uploaded_at IS 'Timestamp when the file was uploaded';


--
-- TOC entry 250 (class 1259 OID 17992)
-- Name: files_file_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.files_file_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.files_file_id_seq OWNER TO doadmin;

--
-- TOC entry 5161 (class 0 OID 0)
-- Dependencies: 250
-- Name: files_file_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.files_file_id_seq OWNED BY public.files.file_id;


--
-- TOC entry 253 (class 1259 OID 18001)
-- Name: history; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.history (
    history_id integer NOT NULL,
    entity_type character varying(50),
    entity_id integer,
    action character varying(50),
    changes jsonb,
    action_date timestamp without time zone DEFAULT now(),
    user_id integer
);


ALTER TABLE public.history OWNER TO doadmin;

--
-- TOC entry 5162 (class 0 OID 0)
-- Dependencies: 253
-- Name: TABLE history; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.history IS 'Records historical changes and actions performed on entities';


--
-- TOC entry 5163 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN history.history_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.history.history_id IS 'Unique internal history ID';


--
-- TOC entry 5164 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN history.entity_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.history.entity_type IS 'Type of entity the history record refers to (e.g., ''Learner'', ''Agent'', ''Class'')';


--
-- TOC entry 5165 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN history.entity_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.history.entity_id IS 'ID of the entity';


--
-- TOC entry 5166 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN history.action; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.history.action IS 'Type of action performed (e.g., ''Created'', ''Updated'', ''Deleted'')';


--
-- TOC entry 5167 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN history.changes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.history.changes IS 'Details of the changes made, stored in JSON format';


--
-- TOC entry 5168 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN history.action_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.history.action_date IS 'Timestamp when the action occurred';


--
-- TOC entry 5169 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN history.user_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.history.user_id IS 'Reference to the user who performed the action';


--
-- TOC entry 252 (class 1259 OID 18000)
-- Name: history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.history_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.history_history_id_seq OWNER TO doadmin;

--
-- TOC entry 5170 (class 0 OID 0)
-- Dependencies: 252
-- Name: history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.history_history_id_seq OWNED BY public.history.history_id;


--
-- TOC entry 282 (class 1259 OID 18752)
-- Name: latest_document; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.latest_document (
    id integer NOT NULL,
    class_id integer NOT NULL,
    visit_date date NOT NULL,
    visit_type character varying(255) NOT NULL,
    officer_name character varying(255) NOT NULL,
    report_metadata jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.latest_document OWNER TO doadmin;

--
-- TOC entry 276 (class 1259 OID 18424)
-- Name: learner_placement_level; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.learner_placement_level (
    placement_level_id integer NOT NULL,
    level character varying(255) NOT NULL,
    level_desc character varying(255)
);


ALTER TABLE public.learner_placement_level OWNER TO doadmin;

--
-- TOC entry 5171 (class 0 OID 0)
-- Dependencies: 276
-- Name: TABLE learner_placement_level; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.learner_placement_level IS 'Stores Learners Placement Levels';


--
-- TOC entry 278 (class 1259 OID 18454)
-- Name: learner_portfolios; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.learner_portfolios (
    portfolio_id integer NOT NULL,
    learner_id integer NOT NULL,
    file_path character varying(255) NOT NULL,
    upload_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.learner_portfolios OWNER TO doadmin;

--
-- TOC entry 277 (class 1259 OID 18453)
-- Name: learner_portfolios_portfolio_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.learner_portfolios_portfolio_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_portfolios_portfolio_id_seq OWNER TO doadmin;

--
-- TOC entry 5172 (class 0 OID 0)
-- Dependencies: 277
-- Name: learner_portfolios_portfolio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.learner_portfolios_portfolio_id_seq OWNED BY public.learner_portfolios.portfolio_id;


--
-- TOC entry 235 (class 1259 OID 17909)
-- Name: learner_products; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.learner_products (
    learner_id integer NOT NULL,
    product_id integer NOT NULL,
    start_date date,
    end_date date
);


ALTER TABLE public.learner_products OWNER TO doadmin;

--
-- TOC entry 5173 (class 0 OID 0)
-- Dependencies: 235
-- Name: TABLE learner_products; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.learner_products IS 'Associates learners with the products they are enrolled in';


--
-- TOC entry 5174 (class 0 OID 0)
-- Dependencies: 235
-- Name: COLUMN learner_products.learner_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_products.learner_id IS 'Reference to the learner';


--
-- TOC entry 5175 (class 0 OID 0)
-- Dependencies: 235
-- Name: COLUMN learner_products.product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_products.product_id IS 'Reference to the product the learner is enrolled in';


--
-- TOC entry 5176 (class 0 OID 0)
-- Dependencies: 235
-- Name: COLUMN learner_products.start_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_products.start_date IS 'Start date of the learner''s enrollment in the product';


--
-- TOC entry 5177 (class 0 OID 0)
-- Dependencies: 235
-- Name: COLUMN learner_products.end_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_products.end_date IS 'End date of the learner''s enrollment in the product';


--
-- TOC entry 269 (class 1259 OID 18098)
-- Name: learner_progressions; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.learner_progressions (
    progression_id integer NOT NULL,
    learner_id integer,
    from_product_id integer,
    to_product_id integer,
    progression_date date,
    notes text
);


ALTER TABLE public.learner_progressions OWNER TO doadmin;

--
-- TOC entry 5178 (class 0 OID 0)
-- Dependencies: 269
-- Name: TABLE learner_progressions; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.learner_progressions IS 'Tracks the progression of learners between products';


--
-- TOC entry 5179 (class 0 OID 0)
-- Dependencies: 269
-- Name: COLUMN learner_progressions.progression_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_progressions.progression_id IS 'Unique internal progression ID';


--
-- TOC entry 5180 (class 0 OID 0)
-- Dependencies: 269
-- Name: COLUMN learner_progressions.learner_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_progressions.learner_id IS 'Reference to the learner';


--
-- TOC entry 5181 (class 0 OID 0)
-- Dependencies: 269
-- Name: COLUMN learner_progressions.from_product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_progressions.from_product_id IS 'Reference to the initial product';


--
-- TOC entry 5182 (class 0 OID 0)
-- Dependencies: 269
-- Name: COLUMN learner_progressions.to_product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_progressions.to_product_id IS 'Reference to the new product after progression';


--
-- TOC entry 5183 (class 0 OID 0)
-- Dependencies: 269
-- Name: COLUMN learner_progressions.progression_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_progressions.progression_date IS 'Date when the learner progressed to the new product';


--
-- TOC entry 5184 (class 0 OID 0)
-- Dependencies: 269
-- Name: COLUMN learner_progressions.notes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_progressions.notes IS 'Additional notes regarding the progression';


--
-- TOC entry 268 (class 1259 OID 18097)
-- Name: learner_progressions_progression_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.learner_progressions_progression_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_progressions_progression_id_seq OWNER TO doadmin;

--
-- TOC entry 5185 (class 0 OID 0)
-- Dependencies: 268
-- Name: learner_progressions_progression_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.learner_progressions_progression_id_seq OWNED BY public.learner_progressions.progression_id;


--
-- TOC entry 275 (class 1259 OID 18410)
-- Name: learner_qualifications; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.learner_qualifications (
    id integer NOT NULL,
    qualification character varying(255)
);


ALTER TABLE public.learner_qualifications OWNER TO doadmin;

--
-- TOC entry 5186 (class 0 OID 0)
-- Dependencies: 275
-- Name: TABLE learner_qualifications; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.learner_qualifications IS 'Table containing a list of possible qualifications that learners can attain.';


--
-- TOC entry 5187 (class 0 OID 0)
-- Dependencies: 275
-- Name: COLUMN learner_qualifications.qualification; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learner_qualifications.qualification IS 'Name of the qualification.';


--
-- TOC entry 274 (class 1259 OID 18409)
-- Name: learner_qualifications_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.learner_qualifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_qualifications_id_seq OWNER TO doadmin;

--
-- TOC entry 5188 (class 0 OID 0)
-- Dependencies: 274
-- Name: learner_qualifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.learner_qualifications_id_seq OWNED BY public.learner_qualifications.id;


--
-- TOC entry 222 (class 1259 OID 17834)
-- Name: learners; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.learners (
    id integer NOT NULL,
    first_name character varying(50),
    initials character varying(10),
    surname character varying(50),
    gender character varying(10),
    race character varying(20),
    sa_id_no character varying(20),
    passport_number character varying(20),
    tel_number character varying(20),
    alternative_tel_number character varying(20),
    email_address character varying(100),
    address_line_1 character varying(100),
    address_line_2 character varying(100),
    city_town_id integer,
    province_region_id integer,
    postal_code character varying(10),
    assessment_status character varying(20),
    placement_assessment_date date,
    numeracy_level integer,
    employment_status boolean,
    employer_id integer,
    disability_status boolean,
    scanned_portfolio character varying(255),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    highest_qualification integer,
    communication_level integer,
    second_name character varying(255),
    title character varying(16)
);


ALTER TABLE public.learners OWNER TO doadmin;

--
-- TOC entry 5189 (class 0 OID 0)
-- Dependencies: 222
-- Name: TABLE learners; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.learners IS 'Stores personal, educational, and assessment information about learners';


--
-- TOC entry 5190 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.id IS 'Unique internal learner ID';


--
-- TOC entry 5191 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.first_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.first_name IS 'Learner''s first name';


--
-- TOC entry 5192 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.initials; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.initials IS 'Learner''s initials';


--
-- TOC entry 5193 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.surname; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.surname IS 'Learner''s surname';


--
-- TOC entry 5194 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.gender; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.gender IS 'Learner''s gender';


--
-- TOC entry 5195 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.race; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.race IS 'Learner''s race; options include ''African'', ''Coloured'', ''White'', ''Indian''';


--
-- TOC entry 5196 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.sa_id_no; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.sa_id_no IS 'Learner''s South African ID number';


--
-- TOC entry 5197 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.passport_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.passport_number IS 'Learner''s passport number if they are a foreigner';


--
-- TOC entry 5198 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.tel_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.tel_number IS 'Learner''s primary telephone number';


--
-- TOC entry 5199 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.alternative_tel_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.alternative_tel_number IS 'Learner''s alternative contact number';


--
-- TOC entry 5200 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.email_address; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.email_address IS 'Learner''s email address';


--
-- TOC entry 5201 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.address_line_1; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.address_line_1 IS 'First line of learner''s physical address';


--
-- TOC entry 5202 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.address_line_2; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.address_line_2 IS 'Second line of learner''s physical address';


--
-- TOC entry 5203 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.city_town_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.city_town_id IS 'Reference to the city or town where the learner lives';


--
-- TOC entry 5204 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.province_region_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.province_region_id IS 'Reference to the province/region where the learner lives';


--
-- TOC entry 5205 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.postal_code; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.postal_code IS 'Postal code of the learner''s area';


--
-- TOC entry 5206 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.assessment_status; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.assessment_status IS 'Assessment status; indicates if the learner was assessed (''Assessed'', ''Not Assessed'')';


--
-- TOC entry 5207 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.placement_assessment_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.placement_assessment_date IS 'Date when the learner took the placement assessment';


--
-- TOC entry 5208 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.numeracy_level; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.numeracy_level IS 'Learner''s initial placement level in Communications (e.g., ''CL1b'', ''CL1'', ''CL2'')';


--
-- TOC entry 5209 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.employment_status; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.employment_status IS 'Indicates if the learner is employed (true) or not (false)';


--
-- TOC entry 5210 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.employer_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.employer_id IS 'Reference to the learner''s employer or sponsor';


--
-- TOC entry 5211 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.disability_status; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.disability_status IS 'Indicates if the learner has a disability (true) or not (false)';


--
-- TOC entry 5212 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.scanned_portfolio; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.scanned_portfolio IS 'File path or URL to the learner''s scanned portfolio in PDF format';


--
-- TOC entry 5213 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.created_at IS 'Timestamp when the learner record was created';


--
-- TOC entry 5214 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.updated_at IS 'Timestamp when the learner record was last updated';


--
-- TOC entry 5215 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN learners.highest_qualification; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.learners.highest_qualification IS 'Foreign key referencing learner_qualifications.id; indicates the learner''s highest qualification.';


--
-- TOC entry 221 (class 1259 OID 17833)
-- Name: learners_learner_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.learners_learner_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learners_learner_id_seq OWNER TO doadmin;

--
-- TOC entry 5216 (class 0 OID 0)
-- Dependencies: 221
-- Name: learners_learner_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.learners_learner_id_seq OWNED BY public.learners.id;


--
-- TOC entry 232 (class 1259 OID 17887)
-- Name: locations; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.locations (
    location_id integer NOT NULL,
    suburb character varying(50),
    town character varying(50),
    province character varying(50),
    postal_code character varying(10),
    longitude numeric(9,6),
    latitude numeric(9,6),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    street_address text,
    CONSTRAINT locations_street_address_nonblank CHECK (((street_address IS NULL) OR (btrim(street_address) <> ''::text)))
);


ALTER TABLE public.locations OWNER TO doadmin;

--
-- TOC entry 5217 (class 0 OID 0)
-- Dependencies: 232
-- Name: TABLE locations; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.locations IS 'Stores geographical location data for addresses';


--
-- TOC entry 5218 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.location_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.location_id IS 'Unique internal location ID';


--
-- TOC entry 5219 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.suburb; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.suburb IS 'Suburb name';


--
-- TOC entry 5220 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.town; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.town IS 'Town name';


--
-- TOC entry 5221 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.province; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.province IS 'Province name';


--
-- TOC entry 5222 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.postal_code; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.postal_code IS 'Postal code';


--
-- TOC entry 5223 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.longitude; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.longitude IS 'Geographical longitude coordinate';


--
-- TOC entry 5224 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.latitude; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.latitude IS 'Geographical latitude coordinate';


--
-- TOC entry 5225 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.created_at IS 'Timestamp when the location record was created';


--
-- TOC entry 5226 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.updated_at IS 'Timestamp when the location record was last updated';


--
-- TOC entry 5227 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN locations.street_address; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.locations.street_address IS 'Street address line for the location';


--
-- TOC entry 231 (class 1259 OID 17886)
-- Name: locations_location_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.locations_location_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.locations_location_id_seq OWNER TO doadmin;

--
-- TOC entry 5228 (class 0 OID 0)
-- Dependencies: 231
-- Name: locations_location_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.locations_location_id_seq OWNED BY public.locations.location_id;


--
-- TOC entry 230 (class 1259 OID 17876)
-- Name: products; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.products (
    product_id integer NOT NULL,
    product_name character varying(100),
    product_duration integer,
    learning_area character varying(100),
    learning_area_duration integer,
    reporting_structure text,
    product_notes text,
    product_rules text,
    product_flags text,
    parent_product_id integer,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.products OWNER TO doadmin;

--
-- TOC entry 5229 (class 0 OID 0)
-- Dependencies: 230
-- Name: TABLE products; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.products IS 'Stores information about educational products or courses';


--
-- TOC entry 5230 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.product_id IS 'Unique internal product ID';


--
-- TOC entry 5231 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.product_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.product_name IS 'Name of the product or course';


--
-- TOC entry 5232 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.product_duration; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.product_duration IS 'Total duration of the product in hours';


--
-- TOC entry 5233 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.learning_area; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.learning_area IS 'Learning areas covered by the product (e.g., ''Communication'', ''Numeracy'')';


--
-- TOC entry 5234 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.learning_area_duration; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.learning_area_duration IS 'Duration of each learning area in hours';


--
-- TOC entry 5235 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.reporting_structure; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.reporting_structure IS 'Structure of progress reports for the product';


--
-- TOC entry 5236 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.product_notes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.product_notes IS 'Notes or additional information about the product';


--
-- TOC entry 5237 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.product_rules; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.product_rules IS 'Rules or guidelines associated with the product';


--
-- TOC entry 5238 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.product_flags; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.product_flags IS 'Flags or alerts for the product (e.g., attendance thresholds)';


--
-- TOC entry 5239 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.parent_product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.parent_product_id IS 'Reference to a parent product for hierarchical structuring';


--
-- TOC entry 5240 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.created_at IS 'Timestamp when the product record was created';


--
-- TOC entry 5241 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN products.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.products.updated_at IS 'Timestamp when the product record was last updated';


--
-- TOC entry 229 (class 1259 OID 17875)
-- Name: products_product_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.products_product_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_product_id_seq OWNER TO doadmin;

--
-- TOC entry 5242 (class 0 OID 0)
-- Dependencies: 229
-- Name: products_product_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.products_product_id_seq OWNED BY public.products.product_id;


--
-- TOC entry 247 (class 1259 OID 17966)
-- Name: progress_reports; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.progress_reports (
    report_id integer NOT NULL,
    class_id integer,
    learner_id integer,
    product_id integer,
    progress_percentage numeric(5,2),
    report_date date,
    remarks text,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.progress_reports OWNER TO doadmin;

--
-- TOC entry 5243 (class 0 OID 0)
-- Dependencies: 247
-- Name: TABLE progress_reports; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.progress_reports IS 'Stores progress reports for learners in specific classes and products';


--
-- TOC entry 5244 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.report_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.report_id IS 'Unique internal progress report ID';


--
-- TOC entry 5245 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.class_id IS 'Reference to the class';


--
-- TOC entry 5246 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.learner_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.learner_id IS 'Reference to the learner';


--
-- TOC entry 5247 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.product_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.product_id IS 'Reference to the product or subject';


--
-- TOC entry 5248 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.progress_percentage; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.progress_percentage IS 'Learner''s progress percentage in the product';


--
-- TOC entry 5249 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.report_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.report_date IS 'Date when the progress report was generated';


--
-- TOC entry 5250 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.remarks; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.remarks IS 'Additional remarks or comments';


--
-- TOC entry 5251 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.created_at IS 'Timestamp when the progress report was created';


--
-- TOC entry 5252 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN progress_reports.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.progress_reports.updated_at IS 'Timestamp when the progress report was last updated';


--
-- TOC entry 246 (class 1259 OID 17965)
-- Name: progress_reports_report_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.progress_reports_report_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.progress_reports_report_id_seq OWNER TO doadmin;

--
-- TOC entry 5253 (class 0 OID 0)
-- Dependencies: 246
-- Name: progress_reports_report_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.progress_reports_report_id_seq OWNED BY public.progress_reports.report_id;


--
-- TOC entry 284 (class 1259 OID 18796)
-- Name: qa_visits; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.qa_visits (
    id integer NOT NULL,
    class_id integer NOT NULL,
    visit_date date NOT NULL,
    visit_type character varying(255) NOT NULL,
    officer_name character varying(255) NOT NULL,
    latest_document jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.qa_visits OWNER TO doadmin;

--
-- TOC entry 281 (class 1259 OID 18751)
-- Name: qa_visits_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.qa_visits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.qa_visits_id_seq OWNER TO doadmin;

--
-- TOC entry 5254 (class 0 OID 0)
-- Dependencies: 281
-- Name: qa_visits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.qa_visits_id_seq OWNED BY public.latest_document.id;


--
-- TOC entry 283 (class 1259 OID 18795)
-- Name: qa_visits_id_seq1; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.qa_visits_id_seq1
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.qa_visits_id_seq1 OWNER TO doadmin;

--
-- TOC entry 5255 (class 0 OID 0)
-- Dependencies: 283
-- Name: qa_visits_id_seq1; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.qa_visits_id_seq1 OWNED BY public.qa_visits.id;


--
-- TOC entry 280 (class 1259 OID 18698)
-- Name: sites; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.sites (
    site_id integer NOT NULL,
    client_id integer NOT NULL,
    site_name character varying(100) NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    parent_site_id integer,
    place_id integer
);


ALTER TABLE public.sites OWNER TO doadmin;

--
-- TOC entry 5256 (class 0 OID 0)
-- Dependencies: 280
-- Name: TABLE sites; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.sites IS 'Stores information about client sites with hierarchical structure. Address data is stored in locations table and linked via place_id.';


--
-- TOC entry 5257 (class 0 OID 0)
-- Dependencies: 280
-- Name: COLUMN sites.site_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.sites.site_id IS 'Unique site ID';


--
-- TOC entry 5258 (class 0 OID 0)
-- Dependencies: 280
-- Name: COLUMN sites.client_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.sites.client_id IS 'Reference to the client this site belongs to';


--
-- TOC entry 5259 (class 0 OID 0)
-- Dependencies: 280
-- Name: COLUMN sites.site_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.sites.site_name IS 'Name of the site';


--
-- TOC entry 5260 (class 0 OID 0)
-- Dependencies: 280
-- Name: COLUMN sites.parent_site_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.sites.parent_site_id IS 'Reference to parent site for hierarchical structure. NULL indicates head site.';


--
-- TOC entry 5261 (class 0 OID 0)
-- Dependencies: 280
-- Name: COLUMN sites.place_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.sites.place_id IS 'Foreign key to locations table containing address data.';


--
-- TOC entry 298 (class 1259 OID 19473)
-- Name: sites_address_audit; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.sites_address_audit (
    site_id integer,
    client_id integer,
    site_name character varying(100),
    address_line_1 character varying(120),
    address_line_2 character varying(120),
    address text,
    place_id integer,
    parent_site_id integer,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


ALTER TABLE public.sites_address_audit OWNER TO doadmin;

--
-- TOC entry 297 (class 1259 OID 19468)
-- Name: sites_migration_backup; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.sites_migration_backup (
    site_id integer,
    client_id integer,
    site_name character varying(100),
    address text,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    parent_site_id integer,
    place_id integer,
    address_line_1 character varying(120),
    address_line_2 character varying(120)
);


ALTER TABLE public.sites_migration_backup OWNER TO doadmin;

--
-- TOC entry 279 (class 1259 OID 18697)
-- Name: sites_site_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.sites_site_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sites_site_id_seq OWNER TO doadmin;

--
-- TOC entry 5262 (class 0 OID 0)
-- Dependencies: 279
-- Name: sites_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.sites_site_id_seq OWNED BY public.sites.site_id;


--
-- TOC entry 273 (class 1259 OID 18116)
-- Name: user_permissions; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.user_permissions (
    permission_id integer NOT NULL,
    user_id integer,
    permission character varying(100)
);


ALTER TABLE public.user_permissions OWNER TO doadmin;

--
-- TOC entry 5263 (class 0 OID 0)
-- Dependencies: 273
-- Name: TABLE user_permissions; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.user_permissions IS 'Grants specific permissions to users';


--
-- TOC entry 5264 (class 0 OID 0)
-- Dependencies: 273
-- Name: COLUMN user_permissions.permission_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.user_permissions.permission_id IS 'Unique internal permission ID';


--
-- TOC entry 5265 (class 0 OID 0)
-- Dependencies: 273
-- Name: COLUMN user_permissions.user_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.user_permissions.user_id IS 'Reference to the user';


--
-- TOC entry 5266 (class 0 OID 0)
-- Dependencies: 273
-- Name: COLUMN user_permissions.permission; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.user_permissions.permission IS 'Specific permission granted to the user';


--
-- TOC entry 272 (class 1259 OID 18115)
-- Name: user_permissions_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.user_permissions_permission_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_permissions_permission_id_seq OWNER TO doadmin;

--
-- TOC entry 5267 (class 0 OID 0)
-- Dependencies: 272
-- Name: user_permissions_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.user_permissions_permission_id_seq OWNED BY public.user_permissions.permission_id;


--
-- TOC entry 271 (class 1259 OID 18107)
-- Name: user_roles; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.user_roles (
    role_id integer NOT NULL,
    role_name character varying(50),
    permissions jsonb
);


ALTER TABLE public.user_roles OWNER TO doadmin;

--
-- TOC entry 5268 (class 0 OID 0)
-- Dependencies: 271
-- Name: TABLE user_roles; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.user_roles IS 'Defines roles and associated permissions for users';


--
-- TOC entry 5269 (class 0 OID 0)
-- Dependencies: 271
-- Name: COLUMN user_roles.role_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.user_roles.role_id IS 'Unique internal role ID';


--
-- TOC entry 5270 (class 0 OID 0)
-- Dependencies: 271
-- Name: COLUMN user_roles.role_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.user_roles.role_name IS 'Name of the role (e.g., ''Admin'', ''Project Supervisor'')';


--
-- TOC entry 5271 (class 0 OID 0)
-- Dependencies: 271
-- Name: COLUMN user_roles.permissions; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.user_roles.permissions IS 'Permissions associated with the role, stored in JSON format';


--
-- TOC entry 270 (class 1259 OID 18106)
-- Name: user_roles_role_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.user_roles_role_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_roles_role_id_seq OWNER TO doadmin;

--
-- TOC entry 5272 (class 0 OID 0)
-- Dependencies: 270
-- Name: user_roles_role_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.user_roles_role_id_seq OWNED BY public.user_roles.role_id;


--
-- TOC entry 220 (class 1259 OID 17821)
-- Name: users; Type: TABLE; Schema: public; Owner: doadmin
--

CREATE TABLE public.users (
    user_id integer NOT NULL,
    first_name character varying(50),
    surname character varying(50),
    email character varying(100) NOT NULL,
    cellphone_number character varying(20),
    role character varying(50),
    password_hash character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.users OWNER TO doadmin;

--
-- TOC entry 5273 (class 0 OID 0)
-- Dependencies: 220
-- Name: TABLE users; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.users IS 'Stores system user information';


--
-- TOC entry 5274 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.user_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.user_id IS 'Unique internal user ID';


--
-- TOC entry 5275 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.first_name; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.first_name IS 'User''s first name';


--
-- TOC entry 5276 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.surname; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.surname IS 'User''s surname';


--
-- TOC entry 5277 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.email; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.email IS 'User''s email address';


--
-- TOC entry 5278 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.cellphone_number; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.cellphone_number IS 'User''s cellphone number';


--
-- TOC entry 5279 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.role; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.role IS 'User''s role in the system, e.g., ''Admin'', ''Project Supervisor''';


--
-- TOC entry 5280 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.password_hash; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.password_hash IS 'Hashed password for user authentication';


--
-- TOC entry 5281 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.created_at IS 'Timestamp when the user record was created';


--
-- TOC entry 5282 (class 0 OID 0)
-- Dependencies: 220
-- Name: COLUMN users.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.users.updated_at IS 'Timestamp when the user record was last updated';


--
-- TOC entry 219 (class 1259 OID 17820)
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: doadmin
--

CREATE SEQUENCE public.users_user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_user_id_seq OWNER TO doadmin;

--
-- TOC entry 5283 (class 0 OID 0)
-- Dependencies: 219
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.users_user_id_seq OWNED BY public.users.user_id;


--
-- TOC entry 299 (class 1259 OID 19480)
-- Name: v_client_head_sites; Type: VIEW; Schema: public; Owner: doadmin
--

CREATE VIEW public.v_client_head_sites AS
 SELECT s.site_id,
    s.client_id,
    c.client_name,
    s.site_name,
    s.place_id,
    l.street_address,
    l.suburb,
    l.town,
    l.province,
    l.postal_code,
    l.longitude,
    l.latitude,
    s.created_at,
    s.updated_at
   FROM ((public.sites s
     JOIN public.clients c ON ((c.client_id = s.client_id)))
     LEFT JOIN public.locations l ON ((l.location_id = s.place_id)))
  WHERE (s.parent_site_id IS NULL);


ALTER VIEW public.v_client_head_sites OWNER TO doadmin;

--
-- TOC entry 300 (class 1259 OID 19485)
-- Name: v_client_sub_sites; Type: VIEW; Schema: public; Owner: doadmin
--

CREATE VIEW public.v_client_sub_sites AS
 SELECT s.site_id,
    s.parent_site_id,
    s.client_id,
    s.site_name,
    s.place_id,
    l.street_address,
    l.suburb,
    l.town,
    l.province,
    l.postal_code,
    l.longitude,
    l.latitude,
    parent_s.site_name AS parent_site_name,
    s.created_at,
    s.updated_at
   FROM ((public.sites s
     JOIN public.sites parent_s ON ((parent_s.site_id = s.parent_site_id)))
     LEFT JOIN public.locations l ON ((l.location_id = s.place_id)))
  WHERE (s.parent_site_id IS NOT NULL);


ALTER VIEW public.v_client_sub_sites OWNER TO doadmin;

--
-- TOC entry 294 (class 1259 OID 19175)
-- Name: audit_log; Type: TABLE; Schema: wecoza_events; Owner: doadmin
--

CREATE TABLE wecoza_events.audit_log (
    id integer NOT NULL,
    level character varying(20) DEFAULT 'info'::character varying NOT NULL,
    action character varying(100) NOT NULL,
    message text NOT NULL,
    context jsonb DEFAULT '{}'::jsonb,
    user_id integer,
    ip_address inet,
    user_agent text,
    request_uri text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.audit_log OWNER TO doadmin;

--
-- TOC entry 5284 (class 0 OID 0)
-- Dependencies: 294
-- Name: TABLE audit_log; Type: COMMENT; Schema: wecoza_events; Owner: doadmin
--

COMMENT ON TABLE wecoza_events.audit_log IS 'Security and operation audit trail';


--
-- TOC entry 293 (class 1259 OID 19174)
-- Name: audit_log_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: doadmin
--

CREATE SEQUENCE wecoza_events.audit_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.audit_log_id_seq OWNER TO doadmin;

--
-- TOC entry 5285 (class 0 OID 0)
-- Dependencies: 293
-- Name: audit_log_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: doadmin
--

ALTER SEQUENCE wecoza_events.audit_log_id_seq OWNED BY wecoza_events.audit_log.id;


--
-- TOC entry 292 (class 1259 OID 19154)
-- Name: dashboard_status; Type: TABLE; Schema: wecoza_events; Owner: doadmin
--

CREATE TABLE wecoza_events.dashboard_status (
    id integer NOT NULL,
    class_id integer NOT NULL,
    task_type character varying(100) NOT NULL,
    task_status character varying(50) DEFAULT 'pending'::character varying,
    responsible_user_id integer,
    due_date timestamp with time zone,
    completed_at timestamp with time zone,
    completion_data jsonb DEFAULT '{}'::jsonb,
    last_reminder timestamp with time zone,
    overdue_notified boolean DEFAULT false,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.dashboard_status OWNER TO doadmin;

--
-- TOC entry 5286 (class 0 OID 0)
-- Dependencies: 292
-- Name: TABLE dashboard_status; Type: COMMENT; Schema: wecoza_events; Owner: doadmin
--

COMMENT ON TABLE wecoza_events.dashboard_status IS 'Status tracking for class-related tasks';


--
-- TOC entry 291 (class 1259 OID 19153)
-- Name: dashboard_status_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: doadmin
--

CREATE SEQUENCE wecoza_events.dashboard_status_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.dashboard_status_id_seq OWNER TO doadmin;

--
-- TOC entry 5287 (class 0 OID 0)
-- Dependencies: 291
-- Name: dashboard_status_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: doadmin
--

ALTER SEQUENCE wecoza_events.dashboard_status_id_seq OWNED BY wecoza_events.dashboard_status.id;


--
-- TOC entry 290 (class 1259 OID 19133)
-- Name: events_log; Type: TABLE; Schema: wecoza_events; Owner: doadmin
--

CREATE TABLE wecoza_events.events_log (
    id integer NOT NULL,
    event_name character varying(100) NOT NULL,
    event_payload jsonb DEFAULT '{}'::jsonb,
    class_id integer,
    actor_id integer,
    idempotency_key character varying(255) NOT NULL,
    processed boolean DEFAULT false,
    occurred_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    processed_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.events_log OWNER TO doadmin;

--
-- TOC entry 5288 (class 0 OID 0)
-- Dependencies: 290
-- Name: TABLE events_log; Type: COMMENT; Schema: wecoza_events; Owner: doadmin
--

COMMENT ON TABLE wecoza_events.events_log IS 'Log of all events processed by the system';


--
-- TOC entry 289 (class 1259 OID 19132)
-- Name: events_log_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: doadmin
--

CREATE SEQUENCE wecoza_events.events_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.events_log_id_seq OWNER TO doadmin;

--
-- TOC entry 5289 (class 0 OID 0)
-- Dependencies: 289
-- Name: events_log_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: doadmin
--

ALTER SEQUENCE wecoza_events.events_log_id_seq OWNED BY wecoza_events.events_log.id;


--
-- TOC entry 288 (class 1259 OID 19108)
-- Name: notification_queue; Type: TABLE; Schema: wecoza_events; Owner: doadmin
--

CREATE TABLE wecoza_events.notification_queue (
    id integer NOT NULL,
    event_name character varying(100) NOT NULL,
    idempotency_key character varying(255) NOT NULL,
    recipient_email character varying(255) NOT NULL,
    recipient_name character varying(255),
    channel character varying(50) DEFAULT 'email'::character varying,
    template_name character varying(100) NOT NULL,
    payload jsonb DEFAULT '{}'::jsonb,
    status character varying(50) DEFAULT 'pending'::character varying,
    attempts integer DEFAULT 0,
    max_attempts integer DEFAULT 3,
    last_error text,
    scheduled_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    sent_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.notification_queue OWNER TO doadmin;

--
-- TOC entry 5290 (class 0 OID 0)
-- Dependencies: 288
-- Name: TABLE notification_queue; Type: COMMENT; Schema: wecoza_events; Owner: doadmin
--

COMMENT ON TABLE wecoza_events.notification_queue IS 'Queue for outgoing notifications (email, dashboard, etc.)';


--
-- TOC entry 287 (class 1259 OID 19107)
-- Name: notification_queue_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: doadmin
--

CREATE SEQUENCE wecoza_events.notification_queue_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.notification_queue_id_seq OWNER TO doadmin;

--
-- TOC entry 5291 (class 0 OID 0)
-- Dependencies: 287
-- Name: notification_queue_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: doadmin
--

ALTER SEQUENCE wecoza_events.notification_queue_id_seq OWNED BY wecoza_events.notification_queue.id;


--
-- TOC entry 286 (class 1259 OID 19085)
-- Name: supervisors; Type: TABLE; Schema: wecoza_events; Owner: doadmin
--

CREATE TABLE wecoza_events.supervisors (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(50),
    role character varying(50) DEFAULT 'supervisor'::character varying,
    client_assignments jsonb DEFAULT '[]'::jsonb,
    site_assignments jsonb DEFAULT '[]'::jsonb,
    is_default boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.supervisors OWNER TO doadmin;

--
-- TOC entry 5292 (class 0 OID 0)
-- Dependencies: 286
-- Name: TABLE supervisors; Type: COMMENT; Schema: wecoza_events; Owner: doadmin
--

COMMENT ON TABLE wecoza_events.supervisors IS 'Supervisors assigned to manage classes and receive notifications';


--
-- TOC entry 285 (class 1259 OID 19084)
-- Name: supervisors_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: doadmin
--

CREATE SEQUENCE wecoza_events.supervisors_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.supervisors_id_seq OWNER TO doadmin;

--
-- TOC entry 5293 (class 0 OID 0)
-- Dependencies: 285
-- Name: supervisors_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: doadmin
--

ALTER SEQUENCE wecoza_events.supervisors_id_seq OWNED BY wecoza_events.supervisors.id;


--
-- TOC entry 4494 (class 2604 OID 18056)
-- Name: agent_absences absence_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_absences ALTER COLUMN absence_id SET DEFAULT nextval('public.agent_absences_absence_id_seq'::regclass);


--
-- TOC entry 4468 (class 2604 OID 17935)
-- Name: agent_notes note_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_notes ALTER COLUMN note_id SET DEFAULT nextval('public.agent_notes_note_id_seq'::regclass);


--
-- TOC entry 4485 (class 2604 OID 18014)
-- Name: agent_orders order_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_orders ALTER COLUMN order_id SET DEFAULT nextval('public.agent_orders_order_id_seq'::regclass);


--
-- TOC entry 4496 (class 2604 OID 18066)
-- Name: agent_replacements replacement_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_replacements ALTER COLUMN replacement_id SET DEFAULT nextval('public.agent_replacements_replacement_id_seq'::regclass);


--
-- TOC entry 4439 (class 2604 OID 17848)
-- Name: agents agent_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agents ALTER COLUMN agent_id SET DEFAULT nextval('public.agents_agent_id_seq'::regclass);


--
-- TOC entry 4472 (class 2604 OID 17955)
-- Name: attendance_registers register_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.attendance_registers ALTER COLUMN register_id SET DEFAULT nextval('public.attendance_registers_register_id_seq'::regclass);


--
-- TOC entry 4549 (class 2604 OID 19454)
-- Name: class_change_logs log_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_change_logs ALTER COLUMN log_id SET DEFAULT nextval('public.class_change_logs_log_id_seq'::regclass);


--
-- TOC entry 4470 (class 2604 OID 17945)
-- Name: class_notes note_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_notes ALTER COLUMN note_id SET DEFAULT nextval('public.class_notes_note_id_seq'::regclass);


--
-- TOC entry 4467 (class 2604 OID 17918)
-- Name: class_schedules schedule_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_schedules ALTER COLUMN schedule_id SET DEFAULT nextval('public.class_schedules_schedule_id_seq'::regclass);


--
-- TOC entry 4446 (class 2604 OID 17859)
-- Name: classes class_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes ALTER COLUMN class_id SET DEFAULT nextval('public.classes_class_id_seq'::regclass);


--
-- TOC entry 4497 (class 2604 OID 18075)
-- Name: client_communications communication_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.client_communications ALTER COLUMN communication_id SET DEFAULT nextval('public.client_communications_communication_id_seq'::regclass);


--
-- TOC entry 4455 (class 2604 OID 17870)
-- Name: clients client_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.clients ALTER COLUMN client_id SET DEFAULT nextval('public.clients_client_id_seq'::regclass);


--
-- TOC entry 4491 (class 2604 OID 18034)
-- Name: collections collection_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.collections ALTER COLUMN collection_id SET DEFAULT nextval('public.collections_collection_id_seq'::regclass);


--
-- TOC entry 4488 (class 2604 OID 18023)
-- Name: deliveries delivery_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.deliveries ALTER COLUMN delivery_id SET DEFAULT nextval('public.deliveries_delivery_id_seq'::regclass);


--
-- TOC entry 4464 (class 2604 OID 17899)
-- Name: employers employer_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.employers ALTER COLUMN employer_id SET DEFAULT nextval('public.employers_employer_id_seq'::regclass);


--
-- TOC entry 4499 (class 2604 OID 18085)
-- Name: exam_results result_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exam_results ALTER COLUMN result_id SET DEFAULT nextval('public.exam_results_result_id_seq'::regclass);


--
-- TOC entry 4478 (class 2604 OID 17980)
-- Name: exams exam_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exams ALTER COLUMN exam_id SET DEFAULT nextval('public.exams_exam_id_seq'::regclass);


--
-- TOC entry 4481 (class 2604 OID 17996)
-- Name: files file_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.files ALTER COLUMN file_id SET DEFAULT nextval('public.files_file_id_seq'::regclass);


--
-- TOC entry 4483 (class 2604 OID 18004)
-- Name: history history_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.history ALTER COLUMN history_id SET DEFAULT nextval('public.history_history_id_seq'::regclass);


--
-- TOC entry 4511 (class 2604 OID 18755)
-- Name: latest_document id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.latest_document ALTER COLUMN id SET DEFAULT nextval('public.qa_visits_id_seq'::regclass);


--
-- TOC entry 4506 (class 2604 OID 18457)
-- Name: learner_portfolios portfolio_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_portfolios ALTER COLUMN portfolio_id SET DEFAULT nextval('public.learner_portfolios_portfolio_id_seq'::regclass);


--
-- TOC entry 4502 (class 2604 OID 18101)
-- Name: learner_progressions progression_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_progressions ALTER COLUMN progression_id SET DEFAULT nextval('public.learner_progressions_progression_id_seq'::regclass);


--
-- TOC entry 4505 (class 2604 OID 18413)
-- Name: learner_qualifications id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_qualifications ALTER COLUMN id SET DEFAULT nextval('public.learner_qualifications_id_seq'::regclass);


--
-- TOC entry 4436 (class 2604 OID 17837)
-- Name: learners id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learners ALTER COLUMN id SET DEFAULT nextval('public.learners_learner_id_seq'::regclass);


--
-- TOC entry 4461 (class 2604 OID 17890)
-- Name: locations location_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.locations ALTER COLUMN location_id SET DEFAULT nextval('public.locations_location_id_seq'::regclass);


--
-- TOC entry 4458 (class 2604 OID 17879)
-- Name: products product_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.products ALTER COLUMN product_id SET DEFAULT nextval('public.products_product_id_seq'::regclass);


--
-- TOC entry 4475 (class 2604 OID 17969)
-- Name: progress_reports report_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.progress_reports ALTER COLUMN report_id SET DEFAULT nextval('public.progress_reports_report_id_seq'::regclass);


--
-- TOC entry 4514 (class 2604 OID 18799)
-- Name: qa_visits id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.qa_visits ALTER COLUMN id SET DEFAULT nextval('public.qa_visits_id_seq1'::regclass);


--
-- TOC entry 4508 (class 2604 OID 18701)
-- Name: sites site_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.sites ALTER COLUMN site_id SET DEFAULT nextval('public.sites_site_id_seq'::regclass);


--
-- TOC entry 4504 (class 2604 OID 18119)
-- Name: user_permissions permission_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.user_permissions ALTER COLUMN permission_id SET DEFAULT nextval('public.user_permissions_permission_id_seq'::regclass);


--
-- TOC entry 4503 (class 2604 OID 18110)
-- Name: user_roles role_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.user_roles ALTER COLUMN role_id SET DEFAULT nextval('public.user_roles_role_id_seq'::regclass);


--
-- TOC entry 4433 (class 2604 OID 17824)
-- Name: users user_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.users ALTER COLUMN user_id SET DEFAULT nextval('public.users_user_id_seq'::regclass);


--
-- TOC entry 4545 (class 2604 OID 19178)
-- Name: audit_log id; Type: DEFAULT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.audit_log ALTER COLUMN id SET DEFAULT nextval('wecoza_events.audit_log_id_seq'::regclass);


--
-- TOC entry 4539 (class 2604 OID 19157)
-- Name: dashboard_status id; Type: DEFAULT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.dashboard_status ALTER COLUMN id SET DEFAULT nextval('wecoza_events.dashboard_status_id_seq'::regclass);


--
-- TOC entry 4534 (class 2604 OID 19136)
-- Name: events_log id; Type: DEFAULT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.events_log ALTER COLUMN id SET DEFAULT nextval('wecoza_events.events_log_id_seq'::regclass);


--
-- TOC entry 4525 (class 2604 OID 19111)
-- Name: notification_queue id; Type: DEFAULT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.notification_queue ALTER COLUMN id SET DEFAULT nextval('wecoza_events.notification_queue_id_seq'::regclass);


--
-- TOC entry 4517 (class 2604 OID 19088)
-- Name: supervisors id; Type: DEFAULT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.supervisors ALTER COLUMN id SET DEFAULT nextval('wecoza_events.supervisors_id_seq'::regclass);


--
-- TOC entry 4645 (class 2606 OID 18061)
-- Name: agent_absences agent_absences_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_absences
    ADD CONSTRAINT agent_absences_pkey PRIMARY KEY (absence_id);


--
-- TOC entry 4624 (class 2606 OID 17940)
-- Name: agent_notes agent_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_notes
    ADD CONSTRAINT agent_notes_pkey PRIMARY KEY (note_id);


--
-- TOC entry 4639 (class 2606 OID 18018)
-- Name: agent_orders agent_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_orders
    ADD CONSTRAINT agent_orders_pkey PRIMARY KEY (order_id);


--
-- TOC entry 4649 (class 2606 OID 18070)
-- Name: agent_replacements agent_replacements_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_pkey PRIMARY KEY (replacement_id);


--
-- TOC entry 4569 (class 2606 OID 18853)
-- Name: agents agents_email_unique; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_email_unique UNIQUE (email_address);


--
-- TOC entry 4571 (class 2606 OID 17854)
-- Name: agents agents_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_pkey PRIMARY KEY (agent_id);


--
-- TOC entry 4573 (class 2606 OID 18855)
-- Name: agents agents_sa_id_unique; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_sa_id_unique UNIQUE (sa_id_no);


--
-- TOC entry 4629 (class 2606 OID 17959)
-- Name: attendance_registers attendance_registers_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.attendance_registers
    ADD CONSTRAINT attendance_registers_pkey PRIMARY KEY (register_id);


--
-- TOC entry 4622 (class 2606 OID 17930)
-- Name: class_agents class_agents_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_agents
    ADD CONSTRAINT class_agents_pkey PRIMARY KEY (class_id, agent_id, start_date);


--
-- TOC entry 4735 (class 2606 OID 19459)
-- Name: class_change_logs class_change_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_change_logs
    ADD CONSTRAINT class_change_logs_pkey PRIMARY KEY (log_id);


--
-- TOC entry 4627 (class 2606 OID 17950)
-- Name: class_notes class_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_notes
    ADD CONSTRAINT class_notes_pkey PRIMARY KEY (note_id);


--
-- TOC entry 4618 (class 2606 OID 17920)
-- Name: class_schedules class_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_pkey PRIMARY KEY (schedule_id);


--
-- TOC entry 4620 (class 2606 OID 17925)
-- Name: class_subjects class_subjects_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_subjects
    ADD CONSTRAINT class_subjects_pkey PRIMARY KEY (class_id, product_id);


--
-- TOC entry 4594 (class 2606 OID 17865)
-- Name: classes classes_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_pkey PRIMARY KEY (class_id);


--
-- TOC entry 4651 (class 2606 OID 18080)
-- Name: client_communications client_communications_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_pkey PRIMARY KEY (communication_id);


--
-- TOC entry 4604 (class 2606 OID 17874)
-- Name: clients clients_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_pkey PRIMARY KEY (client_id);


--
-- TOC entry 4643 (class 2606 OID 18040)
-- Name: collections collections_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.collections
    ADD CONSTRAINT collections_pkey PRIMARY KEY (collection_id);


--
-- TOC entry 4641 (class 2606 OID 18029)
-- Name: deliveries deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.deliveries
    ADD CONSTRAINT deliveries_pkey PRIMARY KEY (delivery_id);


--
-- TOC entry 4614 (class 2606 OID 17903)
-- Name: employers employers_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.employers
    ADD CONSTRAINT employers_pkey PRIMARY KEY (employer_id);


--
-- TOC entry 4654 (class 2606 OID 18089)
-- Name: exam_results exam_results_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exam_results
    ADD CONSTRAINT exam_results_pkey PRIMARY KEY (result_id);


--
-- TOC entry 4633 (class 2606 OID 17984)
-- Name: exams exams_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exams
    ADD CONSTRAINT exams_pkey PRIMARY KEY (exam_id);


--
-- TOC entry 4635 (class 2606 OID 17999)
-- Name: files files_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.files
    ADD CONSTRAINT files_pkey PRIMARY KEY (file_id);


--
-- TOC entry 4637 (class 2606 OID 18009)
-- Name: history history_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.history
    ADD CONSTRAINT history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 4664 (class 2606 OID 18428)
-- Name: learner_placement_level learner_placement_level_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_placement_level
    ADD CONSTRAINT learner_placement_level_pkey PRIMARY KEY (placement_level_id);


--
-- TOC entry 4666 (class 2606 OID 18460)
-- Name: learner_portfolios learner_portfolios_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_portfolios
    ADD CONSTRAINT learner_portfolios_pkey PRIMARY KEY (portfolio_id);


--
-- TOC entry 4616 (class 2606 OID 17913)
-- Name: learner_products learner_products_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_products
    ADD CONSTRAINT learner_products_pkey PRIMARY KEY (learner_id, product_id);


--
-- TOC entry 4656 (class 2606 OID 18105)
-- Name: learner_progressions learner_progressions_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_pkey PRIMARY KEY (progression_id);


--
-- TOC entry 4662 (class 2606 OID 18415)
-- Name: learner_qualifications learner_qualifications_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_qualifications
    ADD CONSTRAINT learner_qualifications_pkey PRIMARY KEY (id);


--
-- TOC entry 4567 (class 2606 OID 17843)
-- Name: learners learners_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_pkey PRIMARY KEY (id);


--
-- TOC entry 4612 (class 2606 OID 17894)
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (location_id);


--
-- TOC entry 4610 (class 2606 OID 17885)
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (product_id);


--
-- TOC entry 4631 (class 2606 OID 17975)
-- Name: progress_reports progress_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_pkey PRIMARY KEY (report_id);


--
-- TOC entry 4686 (class 2606 OID 18761)
-- Name: latest_document qa_visits_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.latest_document
    ADD CONSTRAINT qa_visits_pkey PRIMARY KEY (id);


--
-- TOC entry 4688 (class 2606 OID 18805)
-- Name: qa_visits qa_visits_pkey1; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.qa_visits
    ADD CONSTRAINT qa_visits_pkey1 PRIMARY KEY (id);


--
-- TOC entry 4679 (class 2606 OID 18707)
-- Name: sites sites_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_pkey PRIMARY KEY (site_id);


--
-- TOC entry 4660 (class 2606 OID 18121)
-- Name: user_permissions user_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.user_permissions
    ADD CONSTRAINT user_permissions_pkey PRIMARY KEY (permission_id);


--
-- TOC entry 4658 (class 2606 OID 18114)
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (role_id);


--
-- TOC entry 4563 (class 2606 OID 17832)
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- TOC entry 4565 (class 2606 OID 17830)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 4728 (class 2606 OID 19185)
-- Name: audit_log audit_log_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.audit_log
    ADD CONSTRAINT audit_log_pkey PRIMARY KEY (id);


--
-- TOC entry 4719 (class 2606 OID 19168)
-- Name: dashboard_status dashboard_status_class_id_task_type_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.dashboard_status
    ADD CONSTRAINT dashboard_status_class_id_task_type_key UNIQUE (class_id, task_type);


--
-- TOC entry 4721 (class 2606 OID 19166)
-- Name: dashboard_status dashboard_status_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.dashboard_status
    ADD CONSTRAINT dashboard_status_pkey PRIMARY KEY (id);


--
-- TOC entry 4709 (class 2606 OID 19146)
-- Name: events_log events_log_idempotency_key_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.events_log
    ADD CONSTRAINT events_log_idempotency_key_key UNIQUE (idempotency_key);


--
-- TOC entry 4711 (class 2606 OID 19144)
-- Name: events_log events_log_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.events_log
    ADD CONSTRAINT events_log_pkey PRIMARY KEY (id);


--
-- TOC entry 4705 (class 2606 OID 19125)
-- Name: notification_queue notification_queue_idempotency_key_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.notification_queue
    ADD CONSTRAINT notification_queue_idempotency_key_key UNIQUE (idempotency_key);


--
-- TOC entry 4707 (class 2606 OID 19123)
-- Name: notification_queue notification_queue_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.notification_queue
    ADD CONSTRAINT notification_queue_pkey PRIMARY KEY (id);


--
-- TOC entry 4695 (class 2606 OID 19101)
-- Name: supervisors supervisors_email_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.supervisors
    ADD CONSTRAINT supervisors_email_key UNIQUE (email);


--
-- TOC entry 4697 (class 2606 OID 19099)
-- Name: supervisors supervisors_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: doadmin
--

ALTER TABLE ONLY wecoza_events.supervisors
    ADD CONSTRAINT supervisors_pkey PRIMARY KEY (id);


--
-- TOC entry 4646 (class 1259 OID 19068)
-- Name: idx_agent_absences_agent_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agent_absences_agent_id ON public.agent_absences USING btree (agent_id);


--
-- TOC entry 4647 (class 1259 OID 19069)
-- Name: idx_agent_absences_date; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agent_absences_date ON public.agent_absences USING btree (absence_date);


--
-- TOC entry 4625 (class 1259 OID 19067)
-- Name: idx_agent_notes_agent_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agent_notes_agent_id ON public.agent_notes USING btree (agent_id);


--
-- TOC entry 4574 (class 1259 OID 18869)
-- Name: idx_agents_city; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_city ON public.agents USING btree (city);


--
-- TOC entry 4575 (class 1259 OID 18875)
-- Name: idx_agents_city_province; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_city_province ON public.agents USING btree (city, province);


--
-- TOC entry 4576 (class 1259 OID 18871)
-- Name: idx_agents_created_at; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_created_at ON public.agents USING btree (created_at);


--
-- TOC entry 4577 (class 1259 OID 19065)
-- Name: idx_agents_email; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_email ON public.agents USING btree (email_address);


--
-- TOC entry 4578 (class 1259 OID 18864)
-- Name: idx_agents_email_address; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_email_address ON public.agents USING btree (email_address);


--
-- TOC entry 4579 (class 1259 OID 18876)
-- Name: idx_agents_email_unique; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE UNIQUE INDEX idx_agents_email_unique ON public.agents USING btree (email_address) WHERE ((status)::text <> 'deleted'::text);


--
-- TOC entry 4580 (class 1259 OID 18866)
-- Name: idx_agents_first_name; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_first_name ON public.agents USING btree (first_name);


--
-- TOC entry 4581 (class 1259 OID 18878)
-- Name: idx_agents_phone; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_phone ON public.agents USING btree (tel_number);


--
-- TOC entry 4582 (class 1259 OID 18870)
-- Name: idx_agents_province; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_province ON public.agents USING btree (province);


--
-- TOC entry 4583 (class 1259 OID 18865)
-- Name: idx_agents_sa_id_no; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_sa_id_no ON public.agents USING btree (sa_id_no);


--
-- TOC entry 4584 (class 1259 OID 18877)
-- Name: idx_agents_sa_id_unique; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE UNIQUE INDEX idx_agents_sa_id_unique ON public.agents USING btree (sa_id_no) WHERE ((sa_id_no IS NOT NULL) AND ((sa_id_no)::text <> ''::text) AND ((status)::text <> 'deleted'::text));


--
-- TOC entry 4585 (class 1259 OID 18879)
-- Name: idx_agents_sace; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_sace ON public.agents USING btree (sace_number) WHERE ((sace_number IS NOT NULL) AND ((sace_number)::text <> ''::text));


--
-- TOC entry 4586 (class 1259 OID 18873)
-- Name: idx_agents_search; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_search ON public.agents USING btree (surname, first_name, email_address);


--
-- TOC entry 4587 (class 1259 OID 18863)
-- Name: idx_agents_status; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_status ON public.agents USING btree (status);


--
-- TOC entry 4588 (class 1259 OID 18874)
-- Name: idx_agents_status_created; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_status_created ON public.agents USING btree (status, created_at DESC);


--
-- TOC entry 4589 (class 1259 OID 18867)
-- Name: idx_agents_surname; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_surname ON public.agents USING btree (surname);


--
-- TOC entry 4590 (class 1259 OID 18868)
-- Name: idx_agents_tel_number; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_tel_number ON public.agents USING btree (tel_number);


--
-- TOC entry 4591 (class 1259 OID 18872)
-- Name: idx_agents_updated_at; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_updated_at ON public.agents USING btree (updated_at);


--
-- TOC entry 4592 (class 1259 OID 18880)
-- Name: idx_agents_working_areas; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_agents_working_areas ON public.agents USING btree (preferred_working_area_1, preferred_working_area_2, preferred_working_area_3);


--
-- TOC entry 4736 (class 1259 OID 19466)
-- Name: idx_class_change_logs_changed_at; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_class_change_logs_changed_at ON public.class_change_logs USING btree (changed_at DESC);


--
-- TOC entry 4737 (class 1259 OID 19465)
-- Name: idx_class_change_logs_class_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_class_change_logs_class_id ON public.class_change_logs USING btree (class_id);


--
-- TOC entry 4738 (class 1259 OID 19467)
-- Name: idx_class_change_logs_diff_gin; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_class_change_logs_diff_gin ON public.class_change_logs USING gin (diff);


--
-- TOC entry 4595 (class 1259 OID 18642)
-- Name: idx_classes_backup_agent_ids; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_backup_agent_ids ON public.classes USING gin (backup_agent_ids);


--
-- TOC entry 4596 (class 1259 OID 18644)
-- Name: idx_classes_class_agent; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_class_agent ON public.classes USING btree (class_agent);


--
-- TOC entry 4597 (class 1259 OID 18658)
-- Name: idx_classes_class_code; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_class_code ON public.classes USING btree (class_code);


--
-- TOC entry 4598 (class 1259 OID 18657)
-- Name: idx_classes_class_subject; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_class_subject ON public.classes USING btree (class_subject);


--
-- TOC entry 4599 (class 1259 OID 18731)
-- Name: idx_classes_exam_learners; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_exam_learners ON public.classes USING gin (exam_learners);


--
-- TOC entry 4600 (class 1259 OID 18641)
-- Name: idx_classes_learner_ids; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_learner_ids ON public.classes USING gin (learner_ids);


--
-- TOC entry 4601 (class 1259 OID 18643)
-- Name: idx_classes_schedule_data; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_schedule_data ON public.classes USING gin (schedule_data);


--
-- TOC entry 4602 (class 1259 OID 18714)
-- Name: idx_classes_site_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_site_id ON public.classes USING btree (site_id);


--
-- TOC entry 4605 (class 1259 OID 18729)
-- Name: idx_clients_client_name; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_clients_client_name ON public.clients USING btree (client_name);


--
-- TOC entry 4606 (class 1259 OID 19496)
-- Name: idx_clients_contact_email; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_clients_contact_email ON public.clients USING btree (contact_person_email) WHERE (contact_person_email IS NOT NULL);


--
-- TOC entry 4607 (class 1259 OID 19495)
-- Name: idx_clients_contact_email_lower; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_clients_contact_email_lower ON public.clients USING btree (lower((contact_person_email)::text)) WHERE (contact_person_email IS NOT NULL);


--
-- TOC entry 4681 (class 1259 OID 18767)
-- Name: idx_qa_visits_class_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_qa_visits_class_id ON public.latest_document USING btree (class_id);


--
-- TOC entry 4682 (class 1259 OID 18769)
-- Name: idx_qa_visits_officer_name; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_qa_visits_officer_name ON public.latest_document USING btree (officer_name);


--
-- TOC entry 4683 (class 1259 OID 18768)
-- Name: idx_qa_visits_visit_date; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_qa_visits_visit_date ON public.latest_document USING btree (visit_date);


--
-- TOC entry 4684 (class 1259 OID 18770)
-- Name: idx_qa_visits_visit_type; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_qa_visits_visit_type ON public.latest_document USING btree (visit_type);


--
-- TOC entry 4667 (class 1259 OID 19491)
-- Name: idx_sites_client_hierarchy; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_sites_client_hierarchy ON public.sites USING btree (client_id, parent_site_id);


--
-- TOC entry 4668 (class 1259 OID 18713)
-- Name: idx_sites_client_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_sites_client_id ON public.sites USING btree (client_id);


--
-- TOC entry 4669 (class 1259 OID 19493)
-- Name: idx_sites_client_place; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_sites_client_place ON public.sites USING btree (client_id, place_id);


--
-- TOC entry 4670 (class 1259 OID 18728)
-- Name: idx_sites_created_at; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_sites_created_at ON public.sites USING btree (created_at);


--
-- TOC entry 4671 (class 1259 OID 19492)
-- Name: idx_sites_place_lookup; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_sites_place_lookup ON public.sites USING btree (place_id);


--
-- TOC entry 4672 (class 1259 OID 18725)
-- Name: idx_sites_site_name; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_sites_site_name ON public.sites USING btree (site_name);


--
-- TOC entry 4673 (class 1259 OID 19490)
-- Name: idx_sites_site_name_lower; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_sites_site_name_lower ON public.sites USING btree (lower((site_name)::text));


--
-- TOC entry 4674 (class 1259 OID 19478)
-- Name: idxu_sites_client_head_site_name_ci; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE UNIQUE INDEX idxu_sites_client_head_site_name_ci ON public.sites USING btree (client_id, lower((site_name)::text)) WHERE (parent_site_id IS NULL);


--
-- TOC entry 5294 (class 0 OID 0)
-- Dependencies: 4674
-- Name: INDEX idxu_sites_client_head_site_name_ci; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON INDEX public.idxu_sites_client_head_site_name_ci IS 'Ensures unique (case-insensitive) head site names within each client (parent_site_id IS NULL).';


--
-- TOC entry 4675 (class 1259 OID 19479)
-- Name: idxu_sites_parent_site_name_ci; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE UNIQUE INDEX idxu_sites_parent_site_name_ci ON public.sites USING btree (parent_site_id, lower((site_name)::text)) WHERE (parent_site_id IS NOT NULL);


--
-- TOC entry 5295 (class 0 OID 0)
-- Dependencies: 4675
-- Name: INDEX idxu_sites_parent_site_name_ci; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON INDEX public.idxu_sites_parent_site_name_ci IS 'Ensures unique (case-insensitive) sub-site names within each parent site (parent_site_id IS NOT NULL).';


--
-- TOC entry 4608 (class 1259 OID 19444)
-- Name: ix_clients_main_client_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX ix_clients_main_client_id ON public.clients USING btree (main_client_id);


--
-- TOC entry 4652 (class 1259 OID 19419)
-- Name: ix_comm_site; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX ix_comm_site ON public.client_communications USING btree (site_id);


--
-- TOC entry 4676 (class 1259 OID 19404)
-- Name: ix_sites_parent; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX ix_sites_parent ON public.sites USING btree (parent_site_id);


--
-- TOC entry 4677 (class 1259 OID 19405)
-- Name: ix_sites_place; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX ix_sites_place ON public.sites USING btree (place_id);


--
-- TOC entry 4680 (class 1259 OID 19393)
-- Name: uq_sites_client_lowername; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE UNIQUE INDEX uq_sites_client_lowername ON public.sites USING btree (client_id, lower((site_name)::text));


--
-- TOC entry 4729 (class 1259 OID 19187)
-- Name: idx_audit_log_action; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_audit_log_action ON wecoza_events.audit_log USING btree (action);


--
-- TOC entry 4730 (class 1259 OID 19190)
-- Name: idx_audit_log_context; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_audit_log_context ON wecoza_events.audit_log USING gin (context);


--
-- TOC entry 4731 (class 1259 OID 19189)
-- Name: idx_audit_log_created_at; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_audit_log_created_at ON wecoza_events.audit_log USING btree (created_at);


--
-- TOC entry 4732 (class 1259 OID 19186)
-- Name: idx_audit_log_level; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_audit_log_level ON wecoza_events.audit_log USING btree (level);


--
-- TOC entry 4733 (class 1259 OID 19188)
-- Name: idx_audit_log_user_id; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_audit_log_user_id ON wecoza_events.audit_log USING btree (user_id);


--
-- TOC entry 4722 (class 1259 OID 19169)
-- Name: idx_dashboard_status_class_id; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_dashboard_status_class_id ON wecoza_events.dashboard_status USING btree (class_id);


--
-- TOC entry 4723 (class 1259 OID 19172)
-- Name: idx_dashboard_status_due_date; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_dashboard_status_due_date ON wecoza_events.dashboard_status USING btree (due_date);


--
-- TOC entry 4724 (class 1259 OID 19171)
-- Name: idx_dashboard_status_responsible_user_id; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_dashboard_status_responsible_user_id ON wecoza_events.dashboard_status USING btree (responsible_user_id);


--
-- TOC entry 4725 (class 1259 OID 19170)
-- Name: idx_dashboard_status_task_status; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_dashboard_status_task_status ON wecoza_events.dashboard_status USING btree (task_status);


--
-- TOC entry 4726 (class 1259 OID 19173)
-- Name: idx_dashboard_status_task_type; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_dashboard_status_task_type ON wecoza_events.dashboard_status USING btree (task_type);


--
-- TOC entry 4712 (class 1259 OID 19149)
-- Name: idx_events_log_actor_id; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_events_log_actor_id ON wecoza_events.events_log USING btree (actor_id);


--
-- TOC entry 4713 (class 1259 OID 19148)
-- Name: idx_events_log_class_id; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_events_log_class_id ON wecoza_events.events_log USING btree (class_id);


--
-- TOC entry 4714 (class 1259 OID 19147)
-- Name: idx_events_log_event_name; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_events_log_event_name ON wecoza_events.events_log USING btree (event_name);


--
-- TOC entry 4715 (class 1259 OID 19151)
-- Name: idx_events_log_occurred_at; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_events_log_occurred_at ON wecoza_events.events_log USING btree (occurred_at);


--
-- TOC entry 4716 (class 1259 OID 19152)
-- Name: idx_events_log_payload; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_events_log_payload ON wecoza_events.events_log USING gin (event_payload);


--
-- TOC entry 4717 (class 1259 OID 19150)
-- Name: idx_events_log_processed; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_events_log_processed ON wecoza_events.events_log USING btree (processed);


--
-- TOC entry 4698 (class 1259 OID 19130)
-- Name: idx_notification_queue_channel; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_notification_queue_channel ON wecoza_events.notification_queue USING btree (channel);


--
-- TOC entry 4699 (class 1259 OID 19126)
-- Name: idx_notification_queue_event_name; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_notification_queue_event_name ON wecoza_events.notification_queue USING btree (event_name);


--
-- TOC entry 4700 (class 1259 OID 19127)
-- Name: idx_notification_queue_recipient_email; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_notification_queue_recipient_email ON wecoza_events.notification_queue USING btree (recipient_email);


--
-- TOC entry 4701 (class 1259 OID 19129)
-- Name: idx_notification_queue_scheduled_at; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_notification_queue_scheduled_at ON wecoza_events.notification_queue USING btree (scheduled_at);


--
-- TOC entry 4702 (class 1259 OID 19128)
-- Name: idx_notification_queue_status; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_notification_queue_status ON wecoza_events.notification_queue USING btree (status);


--
-- TOC entry 4703 (class 1259 OID 19131)
-- Name: idx_notification_queue_template_name; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_notification_queue_template_name ON wecoza_events.notification_queue USING btree (template_name);


--
-- TOC entry 4689 (class 1259 OID 19105)
-- Name: idx_supervisors_client_assignments; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_supervisors_client_assignments ON wecoza_events.supervisors USING gin (client_assignments);


--
-- TOC entry 4690 (class 1259 OID 19102)
-- Name: idx_supervisors_email; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_supervisors_email ON wecoza_events.supervisors USING btree (email);


--
-- TOC entry 4691 (class 1259 OID 19104)
-- Name: idx_supervisors_is_active; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_supervisors_is_active ON wecoza_events.supervisors USING btree (is_active);


--
-- TOC entry 4692 (class 1259 OID 19103)
-- Name: idx_supervisors_is_default; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_supervisors_is_default ON wecoza_events.supervisors USING btree (is_default);


--
-- TOC entry 4693 (class 1259 OID 19106)
-- Name: idx_supervisors_site_assignments; Type: INDEX; Schema: wecoza_events; Owner: doadmin
--

CREATE INDEX idx_supervisors_site_assignments ON wecoza_events.supervisors USING gin (site_assignments);


--
-- TOC entry 4796 (class 2620 OID 19350)
-- Name: classes classes_log_insert_update; Type: TRIGGER; Schema: public; Owner: doadmin
--

CREATE TRIGGER classes_log_insert_update AFTER INSERT OR UPDATE ON public.classes FOR EACH ROW EXECUTE FUNCTION public.log_class_change();


--
-- TOC entry 4797 (class 2620 OID 19421)
-- Name: sites trg_sites_same_client; Type: TRIGGER; Schema: public; Owner: doadmin
--

CREATE TRIGGER trg_sites_same_client BEFORE INSERT OR UPDATE OF client_id, parent_site_id ON public.sites FOR EACH ROW EXECUTE FUNCTION public.fn_sites_same_client();


--
-- TOC entry 4795 (class 2620 OID 18927)
-- Name: agents update_agents_updated_at; Type: TRIGGER; Schema: public; Owner: doadmin
--

CREATE TRIGGER update_agents_updated_at BEFORE UPDATE ON public.agents FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 4800 (class 2620 OID 19230)
-- Name: dashboard_status update_dashboard_status_updated_at; Type: TRIGGER; Schema: wecoza_events; Owner: doadmin
--

CREATE TRIGGER update_dashboard_status_updated_at BEFORE UPDATE ON wecoza_events.dashboard_status FOR EACH ROW EXECUTE FUNCTION wecoza_events.update_updated_at_column();


--
-- TOC entry 4799 (class 2620 OID 19229)
-- Name: notification_queue update_notification_queue_updated_at; Type: TRIGGER; Schema: wecoza_events; Owner: doadmin
--

CREATE TRIGGER update_notification_queue_updated_at BEFORE UPDATE ON wecoza_events.notification_queue FOR EACH ROW EXECUTE FUNCTION wecoza_events.update_updated_at_column();


--
-- TOC entry 4798 (class 2620 OID 19228)
-- Name: supervisors update_supervisors_updated_at; Type: TRIGGER; Schema: wecoza_events; Owner: doadmin
--

CREATE TRIGGER update_supervisors_updated_at BEFORE UPDATE ON wecoza_events.supervisors FOR EACH ROW EXECUTE FUNCTION wecoza_events.update_updated_at_column();


--
-- TOC entry 4774 (class 2606 OID 18329)
-- Name: agent_absences agent_absences_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_absences
    ADD CONSTRAINT agent_absences_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 4775 (class 2606 OID 18334)
-- Name: agent_absences agent_absences_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_absences
    ADD CONSTRAINT agent_absences_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4760 (class 2606 OID 18234)
-- Name: agent_notes agent_notes_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_notes
    ADD CONSTRAINT agent_notes_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 4770 (class 2606 OID 18299)
-- Name: agent_orders agent_orders_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_orders
    ADD CONSTRAINT agent_orders_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 4771 (class 2606 OID 18304)
-- Name: agent_orders agent_orders_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_orders
    ADD CONSTRAINT agent_orders_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4776 (class 2606 OID 18339)
-- Name: agent_replacements agent_replacements_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4777 (class 2606 OID 18344)
-- Name: agent_replacements agent_replacements_original_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_original_agent_id_fkey FOREIGN KEY (original_agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 4778 (class 2606 OID 18349)
-- Name: agent_replacements agent_replacements_replacement_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_replacement_agent_id_fkey FOREIGN KEY (replacement_agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 4744 (class 2606 OID 18144)
-- Name: agents agents_preferred_working_area_1_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_preferred_working_area_1_fkey FOREIGN KEY (preferred_working_area_1) REFERENCES public.locations(location_id);


--
-- TOC entry 4745 (class 2606 OID 18149)
-- Name: agents agents_preferred_working_area_2_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_preferred_working_area_2_fkey FOREIGN KEY (preferred_working_area_2) REFERENCES public.locations(location_id);


--
-- TOC entry 4746 (class 2606 OID 18154)
-- Name: agents agents_preferred_working_area_3_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_preferred_working_area_3_fkey FOREIGN KEY (preferred_working_area_3) REFERENCES public.locations(location_id);


--
-- TOC entry 4762 (class 2606 OID 18249)
-- Name: attendance_registers attendance_registers_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.attendance_registers
    ADD CONSTRAINT attendance_registers_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 4763 (class 2606 OID 18244)
-- Name: attendance_registers attendance_registers_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.attendance_registers
    ADD CONSTRAINT attendance_registers_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4758 (class 2606 OID 18229)
-- Name: class_agents class_agents_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_agents
    ADD CONSTRAINT class_agents_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 4759 (class 2606 OID 18224)
-- Name: class_agents class_agents_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_agents
    ADD CONSTRAINT class_agents_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4794 (class 2606 OID 19460)
-- Name: class_change_logs class_change_logs_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_change_logs
    ADD CONSTRAINT class_change_logs_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 4761 (class 2606 OID 18239)
-- Name: class_notes class_notes_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_notes
    ADD CONSTRAINT class_notes_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4755 (class 2606 OID 18209)
-- Name: class_schedules class_schedules_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4756 (class 2606 OID 18214)
-- Name: class_subjects class_subjects_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_subjects
    ADD CONSTRAINT class_subjects_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4757 (class 2606 OID 18219)
-- Name: class_subjects class_subjects_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.class_subjects
    ADD CONSTRAINT class_subjects_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 4747 (class 2606 OID 18159)
-- Name: classes classes_client_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_client_id_fkey FOREIGN KEY (client_id) REFERENCES public.clients(client_id);


--
-- TOC entry 4748 (class 2606 OID 18169)
-- Name: classes classes_project_supervisor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_project_supervisor_id_fkey FOREIGN KEY (project_supervisor_id) REFERENCES public.users(user_id);


--
-- TOC entry 4779 (class 2606 OID 18354)
-- Name: client_communications client_communications_client_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_client_id_fkey FOREIGN KEY (client_id) REFERENCES public.clients(client_id);


--
-- TOC entry 4780 (class 2606 OID 19414)
-- Name: client_communications client_communications_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(site_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 4781 (class 2606 OID 18359)
-- Name: client_communications client_communications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- TOC entry 4751 (class 2606 OID 19445)
-- Name: clients clients_main_client_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_main_client_id_fkey FOREIGN KEY (main_client_id) REFERENCES public.clients(client_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 4773 (class 2606 OID 18314)
-- Name: collections collections_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.collections
    ADD CONSTRAINT collections_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4772 (class 2606 OID 18309)
-- Name: deliveries deliveries_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.deliveries
    ADD CONSTRAINT deliveries_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4782 (class 2606 OID 18364)
-- Name: exam_results exam_results_exam_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exam_results
    ADD CONSTRAINT exam_results_exam_id_fkey FOREIGN KEY (exam_id) REFERENCES public.exams(exam_id);


--
-- TOC entry 4783 (class 2606 OID 18369)
-- Name: exam_results exam_results_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exam_results
    ADD CONSTRAINT exam_results_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 4767 (class 2606 OID 18279)
-- Name: exams exams_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exams
    ADD CONSTRAINT exams_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 4768 (class 2606 OID 18284)
-- Name: exams exams_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.exams
    ADD CONSTRAINT exams_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 4749 (class 2606 OID 18646)
-- Name: classes fk_classes_agent; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT fk_classes_agent FOREIGN KEY (class_agent) REFERENCES public.agents(agent_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 4750 (class 2606 OID 18720)
-- Name: classes fk_classes_site; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT fk_classes_site FOREIGN KEY (site_id) REFERENCES public.sites(site_id) ON DELETE SET NULL;


--
-- TOC entry 4739 (class 2606 OID 18416)
-- Name: learners fk_highest_qualification; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT fk_highest_qualification FOREIGN KEY (highest_qualification) REFERENCES public.learner_qualifications(id);


--
-- TOC entry 5296 (class 0 OID 0)
-- Dependencies: 4739
-- Name: CONSTRAINT fk_highest_qualification ON learners; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON CONSTRAINT fk_highest_qualification ON public.learners IS 'Ensures that highest_qualification in learners references a valid id in learner_qualifications.';


--
-- TOC entry 4740 (class 2606 OID 18440)
-- Name: learners fk_placement_level; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT fk_placement_level FOREIGN KEY (numeracy_level) REFERENCES public.learner_placement_level(placement_level_id) ON UPDATE CASCADE;


--
-- TOC entry 4792 (class 2606 OID 18762)
-- Name: latest_document fk_qa_visits_class; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.latest_document
    ADD CONSTRAINT fk_qa_visits_class FOREIGN KEY (class_id) REFERENCES public.classes(class_id) ON DELETE CASCADE;


--
-- TOC entry 4793 (class 2606 OID 18806)
-- Name: qa_visits fk_qa_visits_class; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.qa_visits
    ADD CONSTRAINT fk_qa_visits_class FOREIGN KEY (class_id) REFERENCES public.classes(class_id) ON DELETE CASCADE;


--
-- TOC entry 4789 (class 2606 OID 18708)
-- Name: sites fk_sites_client; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT fk_sites_client FOREIGN KEY (client_id) REFERENCES public.clients(client_id) ON DELETE CASCADE;


--
-- TOC entry 4769 (class 2606 OID 18294)
-- Name: history history_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.history
    ADD CONSTRAINT history_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- TOC entry 4788 (class 2606 OID 18461)
-- Name: learner_portfolios learner_portfolios_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_portfolios
    ADD CONSTRAINT learner_portfolios_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 4753 (class 2606 OID 18199)
-- Name: learner_products learner_products_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_products
    ADD CONSTRAINT learner_products_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 4754 (class 2606 OID 18204)
-- Name: learner_products learner_products_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_products
    ADD CONSTRAINT learner_products_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 4784 (class 2606 OID 18394)
-- Name: learner_progressions learner_progressions_from_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_from_product_id_fkey FOREIGN KEY (from_product_id) REFERENCES public.products(product_id);


--
-- TOC entry 4785 (class 2606 OID 18389)
-- Name: learner_progressions learner_progressions_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 4786 (class 2606 OID 18399)
-- Name: learner_progressions learner_progressions_to_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_to_product_id_fkey FOREIGN KEY (to_product_id) REFERENCES public.products(product_id);


--
-- TOC entry 4741 (class 2606 OID 18124)
-- Name: learners learners_city_town_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_city_town_id_fkey FOREIGN KEY (city_town_id) REFERENCES public.locations(location_id);


--
-- TOC entry 4742 (class 2606 OID 18134)
-- Name: learners learners_employer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_employer_id_fkey FOREIGN KEY (employer_id) REFERENCES public.employers(employer_id);


--
-- TOC entry 4743 (class 2606 OID 18129)
-- Name: learners learners_province_region_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_province_region_id_fkey FOREIGN KEY (province_region_id) REFERENCES public.locations(location_id);


--
-- TOC entry 4752 (class 2606 OID 18184)
-- Name: products products_parent_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_parent_product_id_fkey FOREIGN KEY (parent_product_id) REFERENCES public.products(product_id);


--
-- TOC entry 4764 (class 2606 OID 18264)
-- Name: progress_reports progress_reports_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 4765 (class 2606 OID 18269)
-- Name: progress_reports progress_reports_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 4766 (class 2606 OID 18274)
-- Name: progress_reports progress_reports_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 4790 (class 2606 OID 19394)
-- Name: sites sites_parent_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_parent_site_id_fkey FOREIGN KEY (parent_site_id) REFERENCES public.sites(site_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- TOC entry 4791 (class 2606 OID 19399)
-- Name: sites sites_place_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_place_id_fkey FOREIGN KEY (place_id) REFERENCES public.locations(location_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- TOC entry 4787 (class 2606 OID 18404)
-- Name: user_permissions user_permissions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.user_permissions
    ADD CONSTRAINT user_permissions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- TOC entry 4952 (class 0 OID 0)
-- Dependencies: 5
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: doadmin
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;


-- Completed on 2025-10-20 06:21:29 SAST

--
-- PostgreSQL database dump complete
--

\unrestrict BnhJZleNWpPd8wrXYf6qJY5ukCzweobMkTQdImaOSVPNCJdpdG22wgb2C8QSTbt

