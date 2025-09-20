--
-- PostgreSQL database dump
--

-- Dumped from database version 16.9
-- Dumped by pg_dump version 17.5 (Ubuntu 17.5-1.pgdg22.04+1)

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

SET default_tablespace = '';

SET default_table_access_method = heap;

--
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
    qa_visit_dates text,
    class_agent integer,
    learner_ids jsonb DEFAULT '[]'::jsonb,
    backup_agent_ids jsonb DEFAULT '[]'::jsonb,
    schedule_data jsonb DEFAULT '[]'::jsonb,
    stop_restart_dates jsonb DEFAULT '[]'::jsonb,
    class_notes_data jsonb DEFAULT '[]'::jsonb,
    initial_class_agent integer,
    initial_agent_start_date date,
    qa_reports jsonb DEFAULT '[]'::jsonb,
    exam_learners jsonb DEFAULT '[]'::jsonb
);


ALTER TABLE public.classes OWNER TO doadmin;

--
-- Name: TABLE classes; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON TABLE public.classes IS 'Stores information about classes, including scheduling and associations';


--
-- Name: COLUMN classes.class_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.class_id IS 'Unique internal class ID';


--
-- Name: COLUMN classes.client_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.client_id IS 'Reference to the client associated with the class';


--
-- Name: COLUMN classes.class_address_line; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.class_address_line IS 'Street address where the class takes place';


--
-- Name: COLUMN classes.class_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.class_type IS 'Type of class; determines the ''rules'' (e.g., ''Employed'', ''Community'')';


--
-- Name: COLUMN classes.original_start_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.original_start_date IS 'Original start date of the class';


--
-- Name: COLUMN classes.seta_funded; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.seta_funded IS 'Indicates if the project is SETA funded (true) or not (false)';


--
-- Name: COLUMN classes.seta; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.seta IS 'Name of the SETA (Sector Education and Training Authority) the client belongs to';


--
-- Name: COLUMN classes.exam_class; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.exam_class IS 'Indicates if this is an exam project (true) or not (false)';


--
-- Name: COLUMN classes.exam_type; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.exam_type IS 'Type of exam associated with the class';


--
-- Name: COLUMN classes.project_supervisor_id; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.project_supervisor_id IS 'Reference to the project supervisor managing the class';


--
-- Name: COLUMN classes.delivery_date; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.delivery_date IS 'Date when materials or resources must be delivered to the class';


--
-- Name: COLUMN classes.created_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.created_at IS 'Timestamp when the class record was created';


--
-- Name: COLUMN classes.updated_at; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.updated_at IS 'Timestamp when the class record was last updated';


--
-- Name: COLUMN classes.qa_reports; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.qa_reports IS 'JSON array storing QA report file paths and metadata';


--
-- Name: COLUMN classes.exam_learners; Type: COMMENT; Schema: public; Owner: doadmin
--

COMMENT ON COLUMN public.classes.exam_learners IS 'JSON array storing exam learner IDs and 
  metadata for learners taking exams';


--
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
-- Name: classes_class_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: doadmin
--

ALTER SEQUENCE public.classes_class_id_seq OWNED BY public.classes.class_id;


--
-- Name: classes class_id; Type: DEFAULT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes ALTER COLUMN class_id SET DEFAULT nextval('public.classes_class_id_seq'::regclass);


--
-- Name: classes classes_pkey; Type: CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_pkey PRIMARY KEY (class_id);


--
-- Name: idx_classes_backup_agent_ids; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_backup_agent_ids ON public.classes USING gin (backup_agent_ids);


--
-- Name: idx_classes_class_agent; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_class_agent ON public.classes USING btree (class_agent);


--
-- Name: idx_classes_class_code; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_class_code ON public.classes USING btree (class_code);


--
-- Name: idx_classes_class_subject; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_class_subject ON public.classes USING btree (class_subject);


--
-- Name: idx_classes_exam_learners; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_exam_learners ON public.classes USING gin (exam_learners);


--
-- Name: idx_classes_learner_ids; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_learner_ids ON public.classes USING gin (learner_ids);


--
-- Name: idx_classes_qa_reports; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_qa_reports ON public.classes USING gin (qa_reports);


--
-- Name: idx_classes_schedule_data; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_schedule_data ON public.classes USING gin (schedule_data);


--
-- Name: idx_classes_site_id; Type: INDEX; Schema: public; Owner: doadmin
--

CREATE INDEX idx_classes_site_id ON public.classes USING btree (site_id);


--
-- Name: classes classes_client_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_client_id_fkey FOREIGN KEY (client_id) REFERENCES public.clients(client_id);


--
-- Name: classes classes_project_supervisor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_project_supervisor_id_fkey FOREIGN KEY (project_supervisor_id) REFERENCES public.users(user_id);


--
-- Name: classes fk_classes_agent; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT fk_classes_agent FOREIGN KEY (class_agent) REFERENCES public.agents(agent_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: classes fk_classes_site; Type: FK CONSTRAINT; Schema: public; Owner: doadmin
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT fk_classes_site FOREIGN KEY (site_id) REFERENCES public.sites(site_id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--
