<?php
/**
 * Synchronizes dashboard_status with class lifecycle timestamps.
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Services/SecurityService.php';

class DashboardStatusSyncService
{
    /**
     * Maximum rows to process per sync run.
     */
    const SYNC_LIMIT = 50;

    /**
     * Default supervisor fallback when none is assigned.
     */
    const DEFAULT_SUPERVISOR_ID = 1;

    /**
     * @var PostgreSQLDatabaseService
     */
    private $db;

    public function __construct()
    {
        $this->db = PostgreSQLDatabaseService::get_instance();
    }

    /**
     * Public entry point used by shortcodes to ensure data is in sync.
     */
    public function sync()
    {
        $this->sync_missing_class_created_rows();
        $this->sync_outdated_class_created_rows();
    }

    /**
     * Insert dashboard rows for classes that do not yet have a class_created status.
     */
    private function sync_missing_class_created_rows()
    {
        $classes = $this->db->get_results(
            'SELECT c.class_id, c.class_code, c.client_id, c.project_supervisor_id, c.site_id, c.class_type, c.created_at, c.updated_at
             FROM public.classes c
             WHERE NOT EXISTS (
                 SELECT 1 FROM wecoza_events.dashboard_status ds
                 WHERE ds.class_id = c.class_id
                 AND ds.task_type = \'class_created\'
             )
             ORDER BY c.created_at DESC
             LIMIT ' . self::SYNC_LIMIT
        );

        if (empty($classes)) {
            return;
        }

        foreach ($classes as $class) {
            $this->trigger_class_created_event($class);
        }
    }

    /**
     * Refresh dashboard rows where the class record has newer data.
     */
    private function sync_outdated_class_created_rows()
    {
        $rows = $this->db->get_results(
            'SELECT ds.id AS dashboard_id, ds.updated_at AS dashboard_updated_at, ds.completed_at, c.class_id,
                    c.class_code, c.client_id, c.project_supervisor_id, c.site_id, c.class_type,
                    c.created_at, c.updated_at
             FROM wecoza_events.dashboard_status ds
             INNER JOIN public.classes c ON c.class_id = ds.class_id
             WHERE ds.task_type = \'class_created\'
               AND (
                    (c.updated_at IS NOT NULL AND (ds.updated_at IS NULL OR c.updated_at > ds.updated_at))
                    OR (ds.completed_at IS NULL AND c.created_at IS NOT NULL)
               )
             ORDER BY COALESCE(c.updated_at, c.created_at) DESC
             LIMIT ' . self::SYNC_LIMIT
        );

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $this->update_class_created_row($row);
        }
    }

    /**
     * Emit class.created event so the notification pipeline seeds dashboard + email.
     *
     * @param object $class
     */
    private function trigger_class_created_event($class)
    {
        $class_id = SecurityService::sanitize_int($class->class_id);
        if ($class_id <= 0) {
            return;
        }

        $created_at = $this->normalize_timestamp($class->created_at);
        $responsible_id = $this->resolve_supervisor_id($class->project_supervisor_id);

        $metadata = $this->build_completion_metadata($class);

        $occurred_at = $created_at ?: current_time('mysql');

        if (function_exists('do_action')) {
            do_action('wecoza_event', array(
                'event' => 'class.created',
                'class_id' => $class_id,
                'actor_id' => $responsible_id ?: self::DEFAULT_SUPERVISOR_ID,
                'occurred_at' => $occurred_at,
                'idempotency_key' => sprintf('class.created:sync:%d', $class_id),
                'metadata' => $metadata
            ));
        }
    }

    /**
     * Update existing class_created row with fresh class information.
     *
     * @param object $row
     */
    private function update_class_created_row($row)
    {
        $dashboard_id = SecurityService::sanitize_int($row->dashboard_id);
        $class_id = SecurityService::sanitize_int($row->class_id);

        if ($dashboard_id <= 0 || $class_id <= 0) {
            return;
        }

        $created_at = $this->normalize_timestamp($row->created_at);
        $updated_at = $this->normalize_timestamp($row->updated_at);
        $metadata = $this->build_completion_metadata($row);

        $fields = array(
            'responsible_user_id' => $this->resolve_supervisor_id($row->project_supervisor_id),
            'completion_data' => wp_json_encode($metadata),
            'updated_at' => $updated_at ?: current_time('mysql')
        );

        $result = $this->db->update('dashboard_status', $fields, array('id' => $dashboard_id));

        if ($result === false) {
            $this->log_debug('Failed to update dashboard_status for class', array('class_id' => $class_id));
        }
    }

    /**
     * Build metadata block stored in completion_data JSON column.
     *
     * @param object $class
     * @return array
     */
    private function build_completion_metadata($class)
    {
        return array(
            'class_code' => SecurityService::sanitize_text($class->class_code ?? ''),
            'client_id' => SecurityService::sanitize_int($class->client_id ?? 0),
            'site_id' => SecurityService::sanitize_int($class->site_id ?? 0),
            'class_type' => SecurityService::sanitize_text($class->class_type ?? ''),
            'class_created_at' => $this->normalize_timestamp($class->created_at),
            'class_updated_at' => $this->normalize_timestamp($class->updated_at),
            'responsible_user_id' => $this->resolve_supervisor_id($class->project_supervisor_id ?? null),
            'synced_at' => current_time('mysql'),
            'synced_source' => 'dashboard_status_sync'
        );
    }

    /**
     * Convert timestamps to a consistent format recognised by PostgreSQL.
     *
     * @param string|null $value
     * @return string|null
     */
    private function normalize_timestamp($value)
    {
        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Resolve supervisor responsible for the dashboard row.
     *
     * @param mixed $supervisor_id
     * @return int
     */
    private function resolve_supervisor_id($supervisor_id)
    {
        $supervisor_id = SecurityService::sanitize_int($supervisor_id);
        return $supervisor_id > 0 ? $supervisor_id : self::DEFAULT_SUPERVISOR_ID;
    }

    /**
     * Log debug information when WP_DEBUG is enabled.
     */
    private function log_debug($message, $context = array())
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $context_json = empty($context) ? '' : ' ' . wp_json_encode($context);
        error_log('[DashboardStatusSync] ' . $message . $context_json);
    }
}
