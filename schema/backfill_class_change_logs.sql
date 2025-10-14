-- Backfill class_change_logs table with existing classes
-- This creates log entries for all existing classes so they appear in the plugin

DO $$
DECLARE
    class_record RECORD;
    log_exists INTEGER;
BEGIN
    -- Create a cursor to iterate through all classes
    FOR class_record IN 
        SELECT class_id, client_id, class_address_line, class_type, original_start_date, 
               seta_funded, seta, exam_class, exam_type, project_supervisor_id, delivery_date, 
               site_id, class_subject, class_code, class_duration, class_agent, learner_ids,
               backup_agent_ids, schedule_data, stop_restart_dates, class_notes_data,
               initial_class_agent, initial_agent_start_date, exam_learners, created_at, updated_at
        FROM public.classes 
        ORDER BY class_id
    LOOP
        -- Check if a log entry already exists for this class
        SELECT COUNT(*) INTO log_exists
        FROM public.class_change_logs 
        WHERE class_id = class_record.class_id 
        LIMIT 1;
        
        -- Only insert if no log entry exists
        IF log_exists = 0 THEN
            INSERT INTO public.class_change_logs (
                class_id, 
                operation, 
                changed_at, 
                new_row, 
                old_row, 
                diff,
                tasks
            ) VALUES (
                class_record.class_id,
                'insert',
                COALESCE(class_record.created_at, NOW()),
                jsonb_build_object(
                    'class_id', class_record.class_id,
                    'client_id', class_record.client_id,
                    'class_address_line', class_record.class_address_line,
                    'class_type', class_record.class_type,
                    'original_start_date', class_record.original_start_date,
                    'seta_funded', class_record.seta_funded,
                    'seta', class_record.seta,
                    'exam_class', class_record.exam_class,
                    'exam_type', class_record.exam_type,
                    'project_supervisor_id', class_record.project_supervisor_id,
                    'delivery_date', class_record.delivery_date,
                    'site_id', class_record.site_id,
                    'class_subject', class_record.class_subject,
                    'class_code', class_record.class_code,
                    'class_duration', class_record.class_duration,
                    'class_agent', class_record.class_agent,
                    'learner_ids', class_record.learner_ids,
                    'backup_agent_ids', class_record.backup_agent_ids,
                    'schedule_data', class_record.schedule_data,
                    'stop_restart_dates', class_record.stop_restart_dates,
                    'class_notes_data', class_record.class_notes_data,
                    'initial_class_agent', class_record.initial_class_agent,
                    'initial_agent_start_date', class_record.initial_agent_start_date,
                    'exam_learners', class_record.exam_learners,
                    'created_at', class_record.created_at,
                    'updated_at', class_record.updated_at
                ),
                NULL,
                jsonb_build_object(
                    'class_id', class_record.class_id,
                    'class_code', class_record.class_code,
                    'class_subject', class_record.class_subject
                ),
                '[]'::jsonb
            );
            
            RAISE NOTICE 'Created log entry for class_id: %, class_code: %', class_record.class_id, class_record.class_code;
        END IF;
    END LOOP;
    
    RAISE NOTICE 'Backfill completed. Processed % classes.', (SELECT COUNT(*) FROM public.classes);
END $$;

-- Update the sequence to the correct next value
SELECT setval('public.class_change_logs_log_id_seq', 
    COALESCE((SELECT MAX(log_id) FROM public.class_change_logs), 1), 
    true
);

RAISE NOTICE 'Updated class_change_logs_log_id_seq sequence';
