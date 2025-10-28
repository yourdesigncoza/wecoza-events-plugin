<?php
declare(strict_types=1);

namespace WeCozaEvents\Support;

use function array_key_exists;
use function str_replace;
use function strtolower;
use function ucwords;

final class FieldMapper
{
    private const FIELD_MAPPINGS = [
        'client_id' => 'Client Name (ID)',
        'site_id' => 'Class/Site Name',
        'site_address' => 'Address',
        'class_type' => 'Class Type',
        'class_subject' => 'Class Subject',
        'class_duration' => 'Duration (Hours)',
        'class_code' => 'Class Code',
        'class_id' => 'Class ID',
        'class_start_date' => 'Class Start Date',
        'schedule_start_date' => 'Schedule Start Date',
        'schedule_end_date' => 'Estimated End Date',
        'initial_agent_start_date' => 'Start Date',
        'delivery_date' => 'Delivery Date',
        'schedule_pattern' => 'Schedule Pattern',
        'schedule_days' => 'Days of Week',
        'schedule_day_monday' => 'Monday',
        'schedule_day_tuesday' => 'Tuesday',
        'schedule_day_wednesday' => 'Wednesday',
        'schedule_day_thursday' => 'Thursday',
        'schedule_day_friday' => 'Friday',
        'schedule_day_saturday' => 'Saturday',
        'schedule_day_sunday' => 'Sunday',
        'schedule_day_of_month' => 'Day of Month',
        'schedule_total_hours' => 'Total Hours',
        'day-start-time' => 'Start Time',
        'day-end-time' => 'End Time',
        'exception_dates' => 'Date',
        'exception_reasons' => 'Reason',
        'holiday_overrides' => 'Holiday Overrides',
        'stop_dates' => 'Stop Date',
        'restart_dates' => 'Restart Date',
        'seta_funded' => 'SETA Funded?',
        'seta_id' => 'SETA',
        'exam_class' => 'Exam Class',
        'exam_type' => 'Exam Type',
        'exam_learner_select' => 'Select Learners',
        'exam_learners' => 'Exam Learners',
        'class_learners_data' => 'Learners Data',
        'note_class_id' => 'Note Class ID',
        'note_id' => 'Note ID',
        'note_content' => 'Note Content',
        'class_notes' => 'Class Notes',
        'note_priority' => 'Priority',
        'qa_visits_data' => 'Visit Data',
        'qa_visit_dates' => 'Visit Date',
        'qa_visit_types' => 'Visit Type',
        'qa_officers' => 'Officer Name',
        'qa_reports' => 'QA Report',
        'qa_class_id' => 'QA Class ID',
        'qa_question' => 'QA Question',
        'qa_context' => 'Context/Notes',
        'qa_attachment' => 'Attachment',
        'initial_class_agent' => 'Initial Class Agent',
        'project_supervisor' => 'Project Supervisor',
        'backup_agent_ids' => 'Backup Agent',
        'backup_agent_dates' => 'Backup Date',
        'stat-total-days' => 'Total Calendar Days',
        'stat-total-weeks' => 'Total Weeks',
        'stat-total-months' => 'Total Months',
        'stat-total-classes' => 'Total Scheduled Classes',
        'stat-total-hours' => 'Total Training Hours',
        'stat-avg-hours-month' => 'Average Hours per Month',
        'stat-holidays-affecting' => 'Holidays Affecting Classes',
        'stat-exception-dates' => 'Exception Dates',
        'stat-actual-days' => 'Actual Training Days',
    ];

    /**
     * Get human-readable label for a field ID
     */
    public static function getLabel(string $fieldId): string
    {
        $normalized = strtolower(str_replace('[]', '', $fieldId));

        if (array_key_exists($normalized, self::FIELD_MAPPINGS)) {
            return self::FIELD_MAPPINGS[$normalized];
        }

        return self::titleCase($fieldId);
    }

    /**
     * Get all field mappings
     *
     * @return array<string,string>
     */
    public static function getAllMappings(): array
    {
        return self::FIELD_MAPPINGS;
    }

    /**
     * Convert field ID to title case as fallback
     */
    private static function titleCase(string $fieldId): string
    {
        $fieldId = str_replace(['_', '-', '[]'], ' ', $fieldId);
        return ucwords(strtolower($fieldId));
    }
}
