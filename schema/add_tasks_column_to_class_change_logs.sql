-- Add tasks column to class_change_logs table if it doesn't exist
-- This migration adds the missing tasks column for the plugin functionality

DO $$
BEGIN
    -- Check if the column exists before adding it
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'class_change_logs' 
        AND column_name = 'tasks'
        AND table_schema = 'public'
    ) THEN
        ALTER TABLE public.class_change_logs 
        ADD COLUMN tasks JSONB DEFAULT '[]'::jsonb;
        
        RAISE NOTICE 'Added tasks column to class_change_logs table';
    ELSE
        RAISE NOTICE 'tasks column already exists in class_change_logs table';
    END IF;
END $$;
