<?php
/**
 * Shortcode controller for WECOZA Notifications
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode controller class
 */
class ShortcodeController
{
    /**
     * Database service instance
     */
    private $db;

    /**
     * Dashboard sync service
     */
    private $dashboard_sync_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = PostgreSQLDatabaseService::get_instance();
        $this->dashboard_sync_service = new DashboardStatusSyncService();
        $this->init_hooks();
        $this->register_shortcodes();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // AJAX hooks for shortcode updates
        add_action('wp_ajax_wecoza_update_shortcode', array($this, 'ajax_update_shortcode'));
        add_action('wp_ajax_nopriv_wecoza_update_shortcode', array($this, 'ajax_update_shortcode'));
        add_action('wp_ajax_wecoza_complete_task', array($this, 'ajax_complete_task'));
        add_action('wp_ajax_wecoza_get_class_status', array($this, 'ajax_get_class_status'));
        add_action('wp_ajax_wecoza_run_dashboard_sync', array($this, 'ajax_run_dashboard_sync'));
    }

    /**
     * Register all shortcodes
     */
    public function register_shortcodes()
    {
        // Status Display Shortcodes
        add_shortcode('wecoza_class_status', array($this, 'render_class_status'));
        add_shortcode('wecoza_pending_tasks', array($this, 'render_pending_tasks'));
        add_shortcode('wecoza_status_tile', array($this, 'render_status_tile'));

        // Notification Management Shortcodes
        add_shortcode('wecoza_notification_center', array($this, 'render_notification_center'));
        add_shortcode('wecoza_notification_badges', array($this, 'render_notification_badges'));

        // Progress & Activity Shortcodes
        add_shortcode('wecoza_progress_bar', array($this, 'render_progress_bar'));
        add_shortcode('wecoza_recent_activity', array($this, 'render_recent_activity'));
        add_shortcode('wecoza_deadline_tracker', array($this, 'render_deadline_tracker'));

        // Advanced Display Shortcodes
        add_shortcode('wecoza_supervisor_dashboard', array($this, 'render_supervisor_dashboard'));
        add_shortcode('wecoza_quick_actions', array($this, 'render_quick_actions'));
        add_shortcode('wecoza_class_timeline', array($this, 'render_class_timeline'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets()
    {
        // Only enqueue on pages with WECOZA shortcodes
        global $post;
        if (!$post || !$this->has_wecoza_shortcodes($post->post_content)) {
            return;
        }

        $script_path = WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'assets/js/shortcodes.js';
        $script_version = file_exists($script_path) ? filemtime($script_path) : time();

        wp_enqueue_script(
            'wecoza-shortcodes',
            WECOZA_NOTIFICATIONS_PLUGIN_URL . 'assets/js/shortcodes.js',
            array('jquery'),
            $script_version,
            true
        );

        wp_localize_script('wecoza-shortcodes', 'wecoza_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wecoza_shortcode_nonce'),
            'refresh_interval' => 120000 // 120 seconds
        ));
    }

    /**
     * Render class status shortcode
     */
    public function render_class_status($atts)
    {
        $atts = shortcode_atts(array(
            'class_id' => '',
            'client_id' => '',
            'user_id' => 'current',
            'status' => 'all',
            'show_completed' => 'false',
            'limit' => '10',
            'sort' => 'due_date',
            'refresh_interval' => '120'
        ), $atts, 'wecoza_class_status');

        $container_id = 'wecoza-class-status-' . uniqid();

        $content = $this->get_class_status_inner($atts, $container_id);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-class-status-container"
             data-wecoza-shortcode="class_status"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>"
             data-refresh-interval="<?php echo esc_attr($atts['refresh_interval']); ?>">

            <?php echo $content; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Build inner markup for class status shortcode (buttons + tiles)
     */
    private function get_class_status_inner($atts, $container_id)
    {
        $tasks = $this->get_dashboard_tasks($atts);

        if (empty($tasks) && $atts['show_completed'] === 'false') {
            ob_start();
            echo $this->render_manual_sync_button($container_id, 'top');
            ?>
            <div class="alert alert-subtle-info border border-subtle">
                <div class="d-flex align-items-center gap-2">
                    <span class="fs-5">😊</span>
                    <div>
                        <h6 class="mb-1 text-body-emphasis">No pending tasks</h6>
                        <p class="mb-0 text-body-secondary">All class setup tasks are up to date. Use the sync button if you expect new activity.</p>
                    </div>
                </div>
            </div>
            <?php
            echo $this->render_manual_sync_button($container_id, 'bottom');
            return ob_get_clean();
        }

        $filters = $this->build_task_filters($tasks);

        ob_start();

        $view_path = WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'app/Views/class-status-table-view.php';

        if (!file_exists($view_path)) {
            return '<div class="wecoza-error">' . esc_html__('Class status view not found.', 'wecoza-notifications') . '</div>';
        }

        $filters = apply_filters('wecoza_class_status_filters', $filters, $tasks, $atts);
        $tasks = apply_filters('wecoza_class_status_tasks', $tasks, $atts);
        $summary_stats = $this->build_class_status_summary($tasks);

        $controller = $this; // provide controller context inside the view

        ob_start();
        include $view_path;
        return ob_get_clean();
    }

    /**
     * Render pending tasks shortcode
     */
    public function render_pending_tasks($atts)
    {
        $atts = shortcode_atts(array(
            'user_id' => 'current',
            'priority' => 'all',
            'limit' => '5',
            'show_overdue_first' => 'true',
            'group_by' => 'class',
            'compact' => 'false'
        ), $atts, 'wecoza_pending_tasks');

        $container_id = 'wecoza-pending-tasks-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-pending-tasks-container"
             data-wecoza-shortcode="pending_tasks"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>">

            <h3>📋 Pending Tasks</h3>
            <?php echo $this->get_pending_tasks_content($atts); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get pending tasks content
     */
    private function get_pending_tasks_content($atts)
    {
        $user_id = $atts['user_id'] === 'current' ? get_current_user_id() : intval($atts['user_id']);
        $tasks = $this->get_user_pending_tasks($user_id, $atts);

        if (empty($tasks)) {
            return '<div class="wecoza-no-tasks"><p>No pending tasks found. Great job! 🎉</p></div>';
        }

        ob_start();
        ?>
        <div class="wecoza-pending-tasks-list">
            <?php foreach ($tasks as $task): ?>
                <div class="wecoza-pending-task <?php echo $this->is_task_overdue($task) ? 'overdue' : ''; ?>">
                    <div class="task-info">
                        <strong><?php echo esc_html($this->get_task_title($task->task_type)); ?></strong>
                        <span class="class-name"><?php echo esc_html($this->get_class_name($task->class_id)); ?></span>
                        <?php if ($task->due_date): ?>
                            <span class="due-date">Due: <?php echo date('M j', strtotime($task->due_date)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="task-actions">
                        <button class="btn btn-sm btn-primary wecoza-complete-task"
                                data-class-id="<?php echo esc_attr($task->class_id); ?>"
                                data-task="<?php echo esc_attr($task->task_type); ?>">
                            Complete
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render notification badges shortcode
     */
    public function render_notification_badges($atts)
    {
        $atts = shortcode_atts(array(
            'types' => 'reminder,confirmation',
            'user_id' => 'current',
            'style' => 'bubble',
            'position' => 'top-right'
        ), $atts, 'wecoza_notification_badges');

        $user_id = $atts['user_id'] === 'current' ? get_current_user_id() : intval($atts['user_id']);
        $count = $this->get_notification_count($user_id, explode(',', $atts['types']));

        ob_start();
        ?>
        <div class="wecoza-notification-badge" data-style="<?php echo esc_attr($atts['style']); ?>">
            <?php if ($count > 0): ?>
                <span class="wecoza-notification-count wecoza-count-badge"><?php echo $count; ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render progress bar shortcode
     */
    public function render_progress_bar($atts)
    {
        $atts = shortcode_atts(array(
            'class_id' => '',
            'style' => 'horizontal',
            'show_percentage' => 'true',
            'show_tasks' => 'true',
            'color_scheme' => 'green'
        ), $atts, 'wecoza_progress_bar');

        if (empty($atts['class_id'])) {
            return '<p>Error: class_id is required for progress bar.</p>';
        }

        $progress = $this->get_class_progress($atts['class_id']);

        ob_start();
        ?>
        <div class="wecoza-progress-container" data-color-scheme="<?php echo esc_attr($atts['color_scheme']); ?>">
            <div class="wecoza-progress-bar" style="width: <?php echo $progress['percentage']; ?>%;">
                <?php if ($atts['show_percentage'] === 'true'): ?>
                    <span class="wecoza-progress-text"><?php echo $progress['percentage']; ?>%</span>
                <?php endif; ?>
            </div>

            <?php if ($atts['show_tasks'] === 'true'): ?>
                <div class="wecoza-progress-details">
                    <small><?php echo $progress['completed']; ?> of <?php echo $progress['total']; ?> tasks completed</small>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper methods for data retrieval
     */

    private function get_dashboard_tasks($atts)
    {
        $table = $this->db->get_table('dashboard_status');
        $sql = "SELECT ds.*, 
                       c.class_code, 
                       c.class_type, 
                       c.project_supervisor_id, 
                       c.client_id, 
                       c.site_id, 
                       c.delivery_date,
                       c.created_at AS class_created_at,
                       c.updated_at AS class_updated_at
                FROM {$table} ds
                LEFT JOIN public.classes c ON ds.class_id = c.class_id
                WHERE 1=1";
        $params = array();

        $param_count = 1;

        if (!empty($atts['class_id'])) {
            $sql .= " AND class_id = $" . $param_count++;
            $params[] = $atts['class_id'];
        }

        if ($atts['status'] !== 'all') {
            $sql .= " AND task_status = $" . $param_count++;
            $params[] = $atts['status'];
        }

        if ($atts['show_completed'] === 'false') {
            $sql .= " AND task_status = $" . $param_count++;
            $params[] = 'open';
        }

        $sql .= " ORDER BY " . ($atts['sort'] === 'due_date' ? 'ds.due_date ASC' : 'ds.created_at DESC');
        $sql .= " LIMIT $" . $param_count++;
        $params[] = intval($atts['limit']);

        return $this->db->get_results($sql, $params);
    }

    /**
     * Build filter list for task types present in result set
     */
    private function build_task_filters($tasks)
    {
        $filters = array('all' => __('All Tasks', 'wecoza-notifications'));

        foreach ($tasks as $task) {
            if (empty($task->task_type)) {
                continue;
            }

            if (!isset($filters[$task->task_type])) {
                $filters[$task->task_type] = $this->get_task_title($task->task_type);
            }
        }

        return $filters;
    }

    /**
     * Build summary statistics for the class status dashboard strip.
     *
     * @param array $tasks
     * @return array<int, array<string, mixed>>
     */
    private function build_class_status_summary($tasks)
    {
        $parse_timestamp = static function($value) {
            if ($value instanceof \DateTimeInterface) {
                return $value->getTimestamp();
            }

            if (is_numeric($value)) {
                return (int) $value;
            }

            if (is_string($value) && $value !== '') {
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }

            return 0;
        };

        $normalize_bool = static function($value) {
            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value === 1;
            }

            if (is_string($value)) {
                $value = strtolower($value);
                return in_array($value, array('1', 'true', 't', 'yes', 'y'), true);
            }

            return false;
        };

        $classes = array();
        $client_summary = array();

        $now = current_time('timestamp');
        $week_ago = strtotime('-7 days', $now);
        $two_weeks_ago = strtotime('-14 days', $now);

        foreach ($tasks as $task) {
            $class_id = isset($task->class_id) ? (int) $task->class_id : 0;
            if ($class_id <= 0) {
                continue;
            }

            if (!isset($classes[$class_id])) {
                $created_at = $parse_timestamp(!empty($task->class_created_at) ? $task->class_created_at : (isset($task->created_at) ? $task->created_at : null));
                $updated_at = $parse_timestamp(!empty($task->class_updated_at) ? $task->class_updated_at : (isset($task->updated_at) ? $task->updated_at : null));
                $class_type = isset($task->class_type) ? (string) $task->class_type : '';
                $client_id = isset($task->client_id) ? (int) $task->client_id : 0;

                $classes[$class_id] = array(
                    'created_at' => $created_at,
                    'updated_at' => $updated_at,
                    'seta_funded' => isset($task->seta_funded) ? $normalize_bool($task->seta_funded) : false,
                    'class_type' => $class_type,
                    'client_id' => $client_id,
                    'has_open_task' => false,
                );

                if ($client_id > 0) {
                    if (!isset($client_summary[$client_id])) {
                        $client_summary[$client_id] = array(
                            'first_created' => $created_at,
                        );
                    } else {
                        $client_summary[$client_id]['first_created'] = min(
                            $client_summary[$client_id]['first_created'],
                            $created_at
                        );
                    }
                }
            }

            if (!empty($task->task_status) && $task->task_status === 'open') {
                $classes[$class_id]['has_open_task'] = true;
            }
        }

        if (empty($classes)) {
            $metrics = array('total_classes', 'active_classes', 'seta_funded', 'exam_classes', 'unique_clients');
            $summary = array();
            foreach ($metrics as $metric) {
                $summary[] = array(
                    'key' => $metric,
                    'label' => $this->get_class_summary_label($metric),
                    'value' => 0,
                    'value_formatted' => number_format_i18n(0),
                    'delta' => 0,
                    'delta_formatted' => '0',
                    'delta_type' => 'neutral',
                    'description' => __('Sync dashboard data to populate this stat.', 'wecoza-notifications'),
                );
            }

            return $summary;
        }

        $total_classes = count($classes);
        $recent_classes = 0;
        $previous_classes = 0;

        $active_classes = 0;
        $recent_active = 0;
        $previous_active = 0;

        $seta_classes = 0;
        $recent_seta = 0;
        $previous_seta = 0;

        $exam_classes = 0;
        $recent_exam = 0;
        $previous_exam = 0;

        foreach ($classes as $class) {
            $created_at = $class['created_at'];
            $updated_at = $class['updated_at'];
            $is_exam = $class['class_type'] !== '' && stripos($class['class_type'], 'exam') !== false;

            if ($created_at >= $week_ago) {
                $recent_classes++;
            } elseif ($created_at >= $two_weeks_ago && $created_at < $week_ago) {
                $previous_classes++;
            }

            if ($class['has_open_task']) {
                $active_classes++;

                if ($updated_at >= $week_ago) {
                    $recent_active++;
                } elseif ($updated_at >= $two_weeks_ago && $updated_at < $week_ago) {
                    $previous_active++;
                }
            }

            if ($class['seta_funded']) {
                $seta_classes++;

                if ($created_at >= $week_ago) {
                    $recent_seta++;
                } elseif ($created_at >= $two_weeks_ago && $created_at < $week_ago) {
                    $previous_seta++;
                }
            }

            if ($is_exam) {
                $exam_classes++;

                if ($created_at >= $week_ago) {
                    $recent_exam++;
                } elseif ($created_at >= $two_weeks_ago && $created_at < $week_ago) {
                    $previous_exam++;
                }
            }
        }

        $unique_clients = count($client_summary);
        $recent_clients = 0;
        $previous_clients = 0;

        foreach ($client_summary as $client) {
            $first_created = isset($client['first_created']) ? (int) $client['first_created'] : 0;

            if ($first_created >= $week_ago) {
                $recent_clients++;
            } elseif ($first_created >= $two_weeks_ago && $first_created < $week_ago) {
                $previous_clients++;
            }
        }

        $summary = array(
            array(
                'key' => 'total_classes',
                'label' => $this->get_class_summary_label('total_classes'),
                'value' => $total_classes,
                'value_formatted' => number_format_i18n($total_classes),
                'delta' => $recent_classes - $previous_classes,
                'description' => sprintf(
                    /* translators: %d: number of new classes this week */
                    __('%d new classes in the past week.', 'wecoza-notifications'),
                    max($recent_classes, 0)
                ),
            ),
            array(
                'key' => 'active_classes',
                'label' => $this->get_class_summary_label('active_classes'),
                'value' => $active_classes,
                'value_formatted' => number_format_i18n($active_classes),
                'delta' => $recent_active - $previous_active,
                'description' => sprintf(
                    /* translators: %d: number of recently updated classes */
                    __('%d classes updated in the past week.', 'wecoza-notifications'),
                    max($recent_active, 0)
                ),
            ),
            array(
                'key' => 'seta_funded',
                'label' => $this->get_class_summary_label('seta_funded'),
                'value' => $seta_classes,
                'value_formatted' => number_format_i18n($seta_classes),
                'delta' => $recent_seta - $previous_seta,
                'description' => sprintf(
                    /* translators: %d: number of SETA funded classes */
                    __('%d funded classes started this week.', 'wecoza-notifications'),
                    max($recent_seta, 0)
                ),
            ),
            array(
                'key' => 'exam_classes',
                'label' => $this->get_class_summary_label('exam_classes'),
                'value' => $exam_classes,
                'value_formatted' => number_format_i18n($exam_classes),
                'delta' => $recent_exam - $previous_exam,
                'description' => sprintf(
                    /* translators: %d: number of exam classes */
                    __('%d new exam cohorts scheduled this week.', 'wecoza-notifications'),
                    max($recent_exam, 0)
                ),
            ),
            array(
                'key' => 'unique_clients',
                'label' => $this->get_class_summary_label('unique_clients'),
                'value' => $unique_clients,
                'value_formatted' => number_format_i18n($unique_clients),
                'delta' => $recent_clients - $previous_clients,
                'description' => sprintf(
                    /* translators: %d: number of new clients */
                    __('%d new client engagements in the past week.', 'wecoza-notifications'),
                    max($recent_clients, 0)
                ),
            ),
        );

        foreach ($summary as &$stat) {
            $delta = isset($stat['delta']) ? (int) $stat['delta'] : 0;
            if ($delta > 0) {
                $stat['delta_type'] = 'positive';
                $stat['delta_formatted'] = '+ ' . number_format_i18n($delta);
            } elseif ($delta < 0) {
                $stat['delta_type'] = 'negative';
                $stat['delta_formatted'] = '- ' . number_format_i18n(abs($delta));
            } else {
                $stat['delta_type'] = 'neutral';
                $stat['delta_formatted'] = '0';
            }
        }
        unset($stat);

        return $summary;
    }

    /**
     * Resolve localized label for dashboard summary metric key.
     *
     * @param string $key
     * @return string
     */
    private function get_class_summary_label($key)
    {
        $labels = array(
            'total_classes' => __('Total Classes', 'wecoza-notifications'),
            'active_classes' => __('Active Classes', 'wecoza-notifications'),
            'seta_funded' => __('SETA Funded', 'wecoza-notifications'),
            'exam_classes' => __('Exam Classes', 'wecoza-notifications'),
            'unique_clients' => __('Unique Clients', 'wecoza-notifications'),
        );

        return isset($labels[$key]) ? $labels[$key] : $key;
    }

    /**
     * Render a table row for a dashboard task entry
     */
    private function render_task_table_row($task)
    {
        $metadata = $this->extract_task_metadata($task);
        $task_type = isset($task->task_type) ? $task->task_type : '';
        $task_label = $this->get_task_title($task_type);
        $task_icon = $this->get_task_icon($task_type);
        $task_description = $this->get_task_description($task_type);

        $class_label = !empty($metadata['class_code'])
            ? $metadata['class_code']
            : (!empty($task->class_code) ? $task->class_code : $this->get_class_name($task->class_id));

        if (empty($class_label)) {
            $class_label = sprintf(__('Class #%d', 'wecoza-notifications'), intval($task->class_id));
        }

        $class_type = !empty($metadata['class_type']) ? $metadata['class_type'] : (!empty($task->class_type) ? $task->class_type : '');
        $client_id = !empty($metadata['client_id']) ? intval($metadata['client_id']) : (!empty($task->client_id) ? intval($task->client_id) : 0);

        $supervisor_name = $this->resolve_supervisor_name($task, $metadata);
        $due_markup = $this->format_due_date_display($task);
        $status_badge = $this->render_status_badge($task);

        $row_classes = array('wecoza-task-row');
        $row_attributes = sprintf(
            'class="%s" data-task-type="%s" data-overdue="%s"',
            esc_attr(implode(' ', $row_classes)),
            esc_attr($task_type),
            $this->is_task_overdue($task) ? '1' : '0'
        );

        ob_start();
        ?>
        <tr <?php echo $row_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        >
            <td class="py-2 ps-3 align-middle white-space-nowrap">
                <div class="d-flex align-items-center gap-2" title="<?php echo esc_attr($task_description); ?>">
                    <span class="fs-7 lh-1 wecoza-task-icon-holder"><?php echo $task_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="fw-semibold text-body"> <?php echo esc_html($task_label); ?></span>
                </div>
            </td>
            <td class="py-2 align-middle white-space-nowrap">
                <a href="<?php echo esc_url($this->get_task_url($task)); ?>" class="fw-semibold text-body text-decoration-none">
                    <?php echo esc_html($class_label); ?>
                </a>
                <?php if (!empty($class_type)): ?>
                    <div class="text-body-tertiary fs-10 text-uppercase mt-1"><?php echo esc_html($class_type); ?></div>
                <?php endif; ?>
            </td>
            <td class="py-2 align-middle white-space-nowrap">
                <span class="text-body fw-medium"><?php echo esc_html($supervisor_name); ?></span>
                <?php if ($client_id > 0): ?>
                    <div class="text-body-tertiary fs-10"><?php printf(esc_html__('Client #%d', 'wecoza-notifications'), $client_id); ?></div>
                <?php endif; ?>
            </td>
            <td class="py-2 align-middle white-space-nowrap">
                <?php echo $due_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>
            <td class="py-2 align-middle text-center fs-8 white-space-nowrap">
                <?php echo $status_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>
            <td class="py-2 align-middle text-end white-space-nowrap pe-3">
                <div class="btn-group btn-group-sm" role="group" aria-label="Task actions">
                    <?php if (isset($task->task_status) && $task->task_status === 'open'): ?>
                        <button type="button"
                                class="btn btn-subtle-success wecoza-complete-task"
                                data-class-id="<?php echo esc_attr($task->class_id); ?>"
                                data-task="<?php echo esc_attr($task_type); ?>">
                            <?php esc_html_e('Complete', 'wecoza-notifications'); ?>
                        </button>
                    <?php endif; ?>
                    <a class="btn btn-subtle-secondary"
                       href="<?php echo esc_url($this->get_task_url($task)); ?>">
                        <?php esc_html_e('Open', 'wecoza-notifications'); ?>
                    </a>
                </div>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Decode task metadata and merge with joined fields
     */
    private function extract_task_metadata($task)
    {
        $metadata = array();

        if (!empty($task->completion_data)) {
            $decoded = json_decode($task->completion_data, true);
            if (is_array($decoded)) {
                $metadata = array_merge($metadata, $decoded);
            }
        }

        if (!empty($task->class_code) && empty($metadata['class_code'])) {
            $metadata['class_code'] = $task->class_code;
        }

        if (!empty($task->class_type) && empty($metadata['class_type'])) {
            $metadata['class_type'] = $task->class_type;
        }

        if (!empty($task->client_id) && empty($metadata['client_id'])) {
            $metadata['client_id'] = $task->client_id;
        }

        if (!empty($task->project_supervisor_id) && empty($metadata['responsible_user_id'])) {
            $metadata['responsible_user_id'] = $task->project_supervisor_id;
        }

        if (!empty($task->class_created_at) && empty($metadata['class_created_at'])) {
            $metadata['class_created_at'] = $task->class_created_at;
        }

        if (!empty($task->class_updated_at) && empty($metadata['class_updated_at'])) {
            $metadata['class_updated_at'] = $task->class_updated_at;
        }

        return $metadata;
    }

    /**
     * Resolve supervisor display name
     */
    private function resolve_supervisor_name($task, $metadata)
    {
        $candidate_ids = array();

        if (!empty($task->responsible_user_id)) {
            $candidate_ids[] = intval($task->responsible_user_id);
        }

        if (!empty($metadata['responsible_user_id'])) {
            $candidate_ids[] = intval($metadata['responsible_user_id']);
        }

        if (!empty($task->project_supervisor_id)) {
            $candidate_ids[] = intval($task->project_supervisor_id);
        }

        foreach ($candidate_ids as $user_id) {
            if ($user_id <= 0) {
                continue;
            }
            $user = get_userdata($user_id);
            if ($user && $user->display_name) {
                return $user->display_name;
            }
        }

        return __('Unassigned', 'wecoza-notifications');
    }

    /**
     * Format due date markup with relative feedback
     */
    private function format_due_date_display($task)
    {
        if (empty($task->due_date)) {
            return '<span class="text-body-tertiary">' . esc_html__('Not set', 'wecoza-notifications') . '</span>';
        }

        $timestamp = strtotime($task->due_date);
        if (!$timestamp) {
            return '<span class="text-body-tertiary">' . esc_html__('Not set', 'wecoza-notifications') . '</span>';
        }

        $formatted = esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp));
        $now = current_time('timestamp');
        $is_overdue = $this->is_task_overdue($task);

        $relative = human_time_diff($is_overdue ? $timestamp : $now, $is_overdue ? $now : $timestamp);
        $relative_text = $is_overdue
            ? sprintf(esc_html__('Overdue by %s', 'wecoza-notifications'), $relative)
            : sprintf(esc_html__('Due in %s', 'wecoza-notifications'), $relative);

        $badge = '';
        if ($is_overdue) {
            $badge = '<span class="badge bg-subtle-danger text-danger-emphasis ms-2">' . esc_html__('Overdue', 'wecoza-notifications') . '</span>';
        }

        $html  = '<div class="d-flex align-items-center text-body-emphasis fw-semibold">' . $formatted . $badge . '</div>';
        $html .= '<div class="text-body-tertiary fs-10">' . esc_html($relative_text) . '</div>';

        return $html;
    }

    /**
     * Render status badge markup
     */
    private function render_status_badge($task)
    {
        $status = isset($task->task_status) ? strtolower($task->task_status) : '';
        if (empty($status)) {
            $status = 'unknown';
        }

        $map = array(
            'open' => array('label' => __('Open', 'wecoza-notifications'), 'class' => 'bg-subtle-warning text-warning-emphasis'),
            'pending' => array('label' => __('Pending', 'wecoza-notifications'), 'class' => 'bg-subtle-warning text-warning-emphasis'),
            'informed' => array('label' => __('Informed', 'wecoza-notifications'), 'class' => 'bg-subtle-success text-success-emphasis'),
            'completed' => array('label' => __('Completed', 'wecoza-notifications'), 'class' => 'bg-subtle-success text-success-emphasis'),
            'unknown' => array('label' => __('Unknown', 'wecoza-notifications'), 'class' => 'bg-subtle-secondary text-body-secondary'),
        );

        if (!isset($map[$status])) {
            $map[$status] = array(
                'label' => ucfirst($status),
                'class' => 'bg-subtle-secondary text-body-secondary'
            );
        }

        $config = $map[$status];

        return '<span class="badge ' . esc_attr($config['class']) . '">' . esc_html($config['label']) . '</span>';
    }

    private function get_user_pending_tasks($user_id, $atts)
    {
        $sql = "SELECT * FROM {$this->db->get_table('dashboard_status')}
                WHERE responsible_user_id = $1 AND task_status = 'open'";
        $params = array($user_id);

        if ($atts['show_overdue_first'] === 'true') {
            $sql .= " ORDER BY (due_date < NOW()) DESC, due_date ASC";
        } else {
            $sql .= " ORDER BY due_date ASC";
        }

        $sql .= " LIMIT $2";
        $params[] = intval($atts['limit']);

        return $this->db->get_results($sql, $params);
    }

    private function get_class_name($class_id)
    {
        // This would integrate with the classes plugin
        // For now, return a placeholder
        return "Training Class #" . $class_id;
    }

    private function get_task_title($task_type)
    {
        $titles = array(
            'load_learners' => 'Load Learners',
            'agent_order' => 'Submit Agent Order',
            'training_schedule' => 'Set Training Schedule',
            'material_delivery' => 'Arrange Material Delivery',
            'agent_paperwork' => 'Complete Agent Paperwork'
        );

        return $titles[$task_type] ?? ucwords(str_replace('_', ' ', $task_type));
    }

    private function get_task_description($task_type)
    {
        $descriptions = array(
            'load_learners' => 'Upload or enter the learner roster for this class',
            'agent_order' => 'Submit training agent order for instructor assignment',
            'training_schedule' => 'Set the dates and times for training sessions',
            'material_delivery' => 'Arrange delivery of training materials to venue',
            'agent_paperwork' => 'Complete and submit agent documentation'
        );

        return $descriptions[$task_type] ?? 'Complete this task to proceed with class setup';
    }

    private function get_task_icon($task_type)
    {
        $icons = array(
            'load_learners' => '<i class="bi bi-people"></i>',
            'agent_order' => '<i class="bi bi-person-check"></i>',
            'training_schedule' => '<i class="bi bi-calendar-week"></i>',
            'material_delivery' => '<i class="bi bi-box-seam"></i>',
            'agent_paperwork' => '<i class="bi bi-file-earmark-check"></i>'
        );

        return $icons[$task_type] ?? '<i class="bi bi-clipboard-check"></i>';
    }

    private function get_task_action_text($task_type)
    {
        $actions = array(
            'load_learners' => 'Load Learners',
            'agent_order' => 'Submit Order',
            'training_schedule' => 'Set Schedule',
            'material_delivery' => 'Arrange Delivery',
            'agent_paperwork' => 'Complete Paperwork'
        );

        return $actions[$task_type] ?? 'Complete Task';
    }

    private function get_task_url($task)
    {
        // This would return the appropriate URL for each task type
        // For now, return a placeholder
        if (is_array($task)) {
            $class_id = isset($task['class_id']) ? $task['class_id'] : 0;
        } else {
            $class_id = isset($task->class_id) ? $task->class_id : 0;
        }
        return admin_url('admin.php?page=wecoza-classes&action=edit&class_id=' . $class_id);
    }

    private function is_task_overdue($task)
    {
        if (is_array($task)) {
            $due_date = isset($task['due_date']) ? $task['due_date'] : null;
        } else {
            $due_date = isset($task->due_date) ? $task->due_date : null;
        }
        return $due_date && strtotime($due_date) < time();
    }

    private function get_overdue_days($task)
    {
        if (is_array($task)) {
            $due_date = isset($task['due_date']) ? $task['due_date'] : null;
        } else {
            $due_date = isset($task->due_date) ? $task->due_date : null;
        }
        if (!$due_date) return 0;
        return max(0, floor((time() - strtotime($due_date)) / DAY_IN_SECONDS));
    }

    private function get_notification_count($user_id, $types)
    {
        // This would count unread notifications for the user
        // For now, return a placeholder
        return 3;
    }

    private function get_class_progress($class_id)
    {
        $total_tasks = 5; // load_learners, agent_order, training_schedule, material_delivery, agent_paperwork

        $sql = "SELECT COUNT(*) FROM {$this->db->get_table('dashboard_status')}
                WHERE class_id = $1 AND task_status = 'informed'";

        $completed = $this->db->get_var($sql, array($class_id)) ?: 0;
        $percentage = $total_tasks > 0 ? round(($completed / $total_tasks) * 100) : 0;

        return array(
            'total' => $total_tasks,
            'completed' => $completed,
            'percentage' => $percentage
        );
    }

    /**
     * AJAX handlers
     */

    public function ajax_complete_task()
    {
        check_ajax_referer('wecoza_shortcode_nonce', 'nonce');

        $class_id = intval($_POST['class_id']);
        $task_type = sanitize_text_field($_POST['task_type']);

        // Update task status
        $sql = "UPDATE {$this->db->get_table('dashboard_status')}
                SET task_status = 'informed', completed_at = $1
                WHERE class_id = $2 AND task_type = $3";

        $result = $this->db->query($sql, array(
            current_time('mysql'),
            $class_id,
            $task_type
        ));

        if ($result) {
            wp_send_json_success(array('message' => 'Task marked as complete'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update task'));
        }
    }

    public function ajax_get_class_status()
    {
        check_ajax_referer('wecoza_shortcode_nonce', 'nonce');

        $class_id = intval($_POST['class_id']);
        $tasks = $this->get_dashboard_tasks(array('class_id' => $class_id, 'limit' => 50));

        wp_send_json_success($tasks);
    }

    public function ajax_run_dashboard_sync()
    {
        check_ajax_referer('wecoza_shortcode_nonce', 'nonce');

        if (!is_user_logged_in() || !SecurityService::current_user_can(SecurityService::CAP_MANAGE_NOTIFICATIONS)) {
            wp_send_json_error(array('message' => __('You do not have permission to run the sync.', 'wecoza-notifications')), 403);
            return;
        }

        try {
            $this->dashboard_sync_service->sync();
            wp_send_json_success(array('message' => __('Dashboard data synced successfully.', 'wecoza-notifications')));
        } catch (\Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[ShortcodeController] Manual dashboard sync failed: ' . $exception->getMessage());
            }

            wp_send_json_error(array('message' => __('Failed to sync dashboard data. Please check logs.', 'wecoza-notifications')));
        }
    }

    public function ajax_update_shortcode()
    {
        check_ajax_referer('wecoza_shortcode_nonce', 'nonce');

        $shortcode_type = sanitize_text_field($_POST['shortcode_type']);
        $params = $_POST['params'] ? json_decode(stripslashes($_POST['params']), true) : array();
        $container_id = isset($_POST['container_id']) ? sanitize_text_field($_POST['container_id']) : 'wecoza-shortcode-' . uniqid();

        $content = '';

        switch ($shortcode_type) {
            case 'class_status':
                $content = $this->get_class_status_inner($params, $container_id);
                break;
            case 'pending_tasks':
                $content = $this->get_pending_tasks_content($params);
                break;
            case 'status_tile':
                $task = $this->get_single_task_status($params['class_id'], $params['task_type']);
                if ($task) {
                    $content = $this->get_status_tile_content($task, $params);
                } else {
                    $content = '<div class="wecoza-error">Task not found.</div>';
                }
                break;
            case 'notification_center':
                $user_id = $params['user_id'] === 'current' ? get_current_user_id() : intval($params['user_id']);
                $content = $this->get_notification_center_content($user_id, $params);
                break;
            case 'recent_activity':
                $content = $this->get_recent_activity_content($params);
                break;
            case 'deadline_tracker':
                $content = $this->get_deadline_tracker_content($params);
                break;
            case 'supervisor_dashboard':
                $supervisor_id = $params['supervisor_id'] === 'current' ? get_current_user_id() : intval($params['supervisor_id']);
                $content = $this->get_supervisor_dashboard_content($supervisor_id, $params);
                break;
            case 'quick_actions':
                $content = $this->get_quick_actions_content($params);
                break;
            case 'class_timeline':
                $content = $this->get_class_timeline_content($params);
                break;
        }

        wp_send_json_success(array('html' => $content));
    }

    /**
     * Check if content has WECOZA shortcodes
     */
    private function has_wecoza_shortcodes($content)
    {
        $wecoza_shortcodes = array(
            'wecoza_class_status',
            'wecoza_pending_tasks',
            'wecoza_status_tile',
            'wecoza_notification_center',
            'wecoza_notification_badges',
            'wecoza_progress_bar',
            'wecoza_recent_activity',
            'wecoza_deadline_tracker',
            'wecoza_supervisor_dashboard',
            'wecoza_quick_actions',
            'wecoza_class_timeline'
        );

        foreach ($wecoza_shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render status tile shortcode - Single task status display
     */
    public function render_status_tile($atts)
    {
        $atts = shortcode_atts(array(
            'class_id' => '',
            'task_type' => '',
            'style' => 'card',
            'size' => 'medium',
            'show_actions' => 'true',
            'show_due_date' => 'true',
            'auto_refresh' => 'true'
        ), $atts, 'wecoza_status_tile');

        if (empty($atts['class_id']) || empty($atts['task_type'])) {
            return '<div class="wecoza-error">Error: class_id and task_type are required for status tile.</div>';
        }

        $container_id = 'wecoza-status-tile-' . uniqid();
        $task = $this->get_single_task_status($atts['class_id'], $atts['task_type']);

        if (!$task) {
            return '<div class="wecoza-error">Task not found.</div>';
        }

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-status-tile-single <?php echo esc_attr($atts['style'] . ' ' . $atts['size']); ?>"
             data-wecoza-shortcode="status_tile"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>"
             data-refresh-interval="<?php echo $atts['auto_refresh'] === 'true' ? '30' : '0'; ?>">

            <?php echo $this->get_status_tile_content($task, $atts); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get status tile content
     */
    private function get_status_tile_content($task, $atts)
    {
        $status_class = $task->task_status === 'open' ? 'open-task' : 'informed';
        $overdue_class = $this->is_task_overdue($task) ? 'overdue' : '';
        $urgency_class = $this->get_task_urgency_class($task);

        ob_start();
        ?>
        <div class="wecoza-status-tile-wrapper <?php echo esc_attr($status_class . ' ' . $overdue_class . ' ' . $urgency_class); ?>"
             data-class-id="<?php echo esc_attr($task->class_id); ?>"
             data-task="<?php echo esc_attr($task->task_type); ?>">

            <div class="wecoza-tile-status-indicator">
                <span class="wecoza-task-icon"><?php echo $this->get_task_icon($task->task_type); ?></span>
                <span class="wecoza-status-badge <?php echo esc_attr($task->task_status); ?>">
                    <?php echo $task->task_status === 'open' ? '⬜' : '✅'; ?>
                </span>
            </div>

            <div class="wecoza-tile-main-content">
                <h4 class="wecoza-tile-title"><?php echo esc_html($this->get_task_title($task->task_type)); ?></h4>

                <div class="wecoza-tile-meta">
                    <span class="wecoza-class-name"><?php echo esc_html($this->get_class_name($task->class_id)); ?></span>

                    <?php if ($atts['show_due_date'] === 'true' && $task->due_date): ?>
                        <span class="wecoza-due-date <?php echo $this->is_task_overdue($task) ? 'overdue' : ''; ?>">
                            <?php if ($this->is_task_overdue($task)): ?>
                                🚨 Overdue: <?php echo $this->get_overdue_days($task); ?> days
                            <?php else: ?>
                                📅 Due: <?php echo date('M j, Y', strtotime($task->due_date)); ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <p class="wecoza-tile-description"><?php echo esc_html($this->get_task_description($task->task_type)); ?></p>

                <?php if ($task->task_status === 'informed'): ?>
                    <div class="wecoza-completion-info">
                        <span class="wecoza-completed-label">✅ Completed</span>
                        <?php if ($task->completed_at): ?>
                            <span class="wecoza-completed-date">
                                on <?php echo date('M j, Y \a\t g:i A', strtotime($task->completed_at)); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($atts['show_actions'] === 'true' && $task->task_status === 'open'): ?>
                <div class="wecoza-tile-actions">
                    <button class="btn btn-primary btn-sm wecoza-complete-task"
                            data-class-id="<?php echo esc_attr($task->class_id); ?>"
                            data-task="<?php echo esc_attr($task->task_type); ?>">
                        ✓ Complete
                    </button>
                    <a href="<?php echo esc_url($this->get_task_url($task)); ?>"
                       class="btn btn-secondary btn-sm">
                        <?php echo esc_html($this->get_task_action_text($task->task_type)); ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($this->is_task_overdue($task) && $task->task_status === 'open'): ?>
                <div class="wecoza-urgency-banner">
                    <span class="wecoza-urgency-text">⚠️ Urgent: This task is overdue!</span>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get single task status from database
     */
    private function get_single_task_status($class_id, $task_type)
    {
        $sql = "SELECT * FROM {$this->db->get_table('dashboard_status')}
                WHERE class_id = $1 AND task_type = $2 LIMIT 1";

        return $this->db->get_row($sql, array($class_id, $task_type));
    }

    /**
     * Render manual sync control button
     */
    private function render_manual_sync_button($container_id, $position = 'top')
    {
        if (!is_user_logged_in()) {
            return '';
        }

        if (!SecurityService::current_user_can(SecurityService::CAP_MANAGE_NOTIFICATIONS)) {
            return '';
        }

        $position_class = $position === 'bottom' ? 'wecoza-sync-controls-bottom' : 'wecoza-sync-controls-top';

        $label = esc_html__('Sync Class Data', 'wecoza-notifications');
        $loading_label = esc_html__('Syncing...', 'wecoza-notifications');

        ob_start();
        ?>
        <div class="wecoza-sync-controls <?php echo esc_attr($position_class); ?>">
            <button type="button"
                    class="button button-primary wecoza-run-dashboard-sync"
                    data-container="<?php echo esc_attr($container_id); ?>"
                    data-label="<?php echo esc_attr($label); ?>"
                    data-loading-label="<?php echo esc_attr($loading_label); ?>">
                <?php echo $label; ?>
            </button>
            <span class="wecoza-sync-status" aria-live="polite"></span>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get task urgency class based on due date
     */
    private function get_task_urgency_class($task)
    {
        if (!$task->due_date || $task->task_status !== 'open') {
            return '';
        }

        $days_until_due = ceil((strtotime($task->due_date) - time()) / DAY_IN_SECONDS);

        if ($days_until_due < 0) {
            return 'overdue';
        } elseif ($days_until_due <= 1) {
            return 'urgent';
        } elseif ($days_until_due <= 3) {
            return 'warning';
        }

        return 'normal';
    }
    /**
     * Render notification center shortcode - Comprehensive notification management
     */
    public function render_notification_center($atts)
    {
        $atts = shortcode_atts(array(
            'user_id' => 'current',
            'types' => 'reminder,confirmation',
            'status' => 'all',
            'limit' => '20',
            'show_search' => 'true',
            'show_filters' => 'true',
            'group_by' => 'date',
            'compact' => 'false',
            'auto_refresh' => 'true'
        ), $atts, 'wecoza_notification_center');

        $container_id = 'wecoza-notification-center-' . uniqid();
        $user_id = $atts['user_id'] === 'current' ? get_current_user_id() : intval($atts['user_id']);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-notification-center-container <?php echo $atts['compact'] === 'true' ? 'compact' : ''; ?>"
             data-wecoza-shortcode="notification_center"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>"
             data-refresh-interval="<?php echo $atts['auto_refresh'] === 'true' ? '30' : '0'; ?>">

            <div class="wecoza-notification-center-header">
                <h3>📢 Notification Center</h3>

                <?php if ($atts['show_search'] === 'true' || $atts['show_filters'] === 'true'): ?>
                    <div class="wecoza-notification-controls">
                        <?php if ($atts['show_search'] === 'true'): ?>
                            <div class="wecoza-search-box">
                                <input type="text" class="form-control" placeholder="Search notifications..."
                                       id="<?php echo esc_attr($container_id); ?>-search">
                            </div>
                        <?php endif; ?>

                        <?php if ($atts['show_filters'] === 'true'): ?>
                            <div class="wecoza-filter-controls">
                                <select class="form-select" id="<?php echo esc_attr($container_id); ?>-type-filter">
                                    <option value="">All Types</option>
                                    <option value="reminder">Reminders</option>
                                    <option value="confirmation">Confirmations</option>
                                </select>

                                <select class="form-select" id="<?php echo esc_attr($container_id); ?>-status-filter">
                                    <option value="">All Status</option>
                                    <option value="unread">Unread</option>
                                    <option value="read">Read</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="wecoza-notification-center-content">
                <?php echo $this->get_notification_center_content($user_id, $atts); ?>
            </div>

            <div class="wecoza-notification-center-footer">
                <div class="wecoza-notification-actions">
                    <button class="btn btn-outline-primary btn-sm wecoza-mark-all-read">
                        ✓ Mark All Read
                    </button>
                    <button class="btn btn-outline-secondary btn-sm wecoza-archive-read">
                        📁 Archive Read
                    </button>
                    <button class="btn btn-outline-info btn-sm wecoza-refresh-notifications">
                        🔄 Refresh
                    </button>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get notification center content
     */
    private function get_notification_center_content($user_id, $atts)
    {
        $notifications = $this->get_user_notifications_detailed($user_id, $atts);

        if (empty($notifications)) {
            return '<div class="wecoza-no-notifications">
                        <div class="wecoza-empty-state">
                            <span class="wecoza-empty-icon">📭</span>
                            <h4>No notifications found</h4>
                            <p>You\'re all caught up! Check back later for new updates.</p>
                        </div>
                    </div>';
        }

        ob_start();

        if ($atts['group_by'] === 'date') {
            $grouped = $this->group_notifications_by_date($notifications);
            foreach ($grouped as $date_group => $group_notifications) {
                ?>
                <div class="wecoza-notification-group">
                    <h5 class="wecoza-group-header"><?php echo esc_html($date_group); ?></h5>
                    <?php foreach ($group_notifications as $notification): ?>
                        <?php echo $this->render_single_notification($notification, $atts); ?>
                    <?php endforeach; ?>
                </div>
                <?php
            }
        } else {
            foreach ($notifications as $notification) {
                echo $this->render_single_notification($notification, $atts);
            }
        }

        return ob_get_clean();
    }

    /**
     * Render a single notification item
     */
    private function render_single_notification($notification, $atts)
    {
        $status_class = $notification->status ?? 'unread';
        $type_class = $notification->type ?? 'general';
        $priority_class = $this->get_notification_priority_class($notification);

        ob_start();
        ?>
        <div class="wecoza-notification-item <?php echo esc_attr($status_class . ' ' . $type_class . ' ' . $priority_class); ?>"
             data-notification-id="<?php echo esc_attr($notification->id ?? ''); ?>"
             data-type="<?php echo esc_attr($type_class); ?>">

            <div class="wecoza-notification-icon">
                <?php echo $this->get_notification_icon($notification); ?>
            </div>

            <div class="wecoza-notification-content">
                <div class="wecoza-notification-header">
                    <h6 class="wecoza-notification-title">
                        <?php echo esc_html($notification->title ?? $this->get_default_notification_title($notification)); ?>
                    </h6>
                    <span class="wecoza-notification-time">
                        <?php echo $this->format_notification_time($notification->created_at ?? current_time('mysql')); ?>
                    </span>
                </div>

                <div class="wecoza-notification-body">
                    <p><?php echo esc_html($notification->message ?? 'No message content'); ?></p>

                    <?php if (!empty($notification->class_id)): ?>
                        <div class="wecoza-notification-meta">
                            <span class="wecoza-class-link">
                                Class: <?php echo esc_html($this->get_class_name($notification->class_id)); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($notification->status === 'unread' || !empty($notification->action_url)): ?>
                    <div class="wecoza-notification-actions">
                        <?php if ($notification->status === 'unread'): ?>
                            <button class="btn btn-sm btn-outline-primary wecoza-mark-read"
                                    data-notification-id="<?php echo esc_attr($notification->id ?? ''); ?>">
                                Mark Read
                            </button>
                        <?php endif; ?>

                        <?php if (!empty($notification->action_url)): ?>
                            <a href="<?php echo esc_url($notification->action_url); ?>"
                               class="btn btn-sm btn-primary">
                                <?php echo esc_html($notification->action_text ?? 'View Details'); ?>
                            </a>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-outline-secondary wecoza-archive-notification"
                                data-notification-id="<?php echo esc_attr($notification->id ?? ''); ?>">
                            Archive
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($status_class === 'unread'): ?>
                <div class="wecoza-unread-indicator"></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper methods for notification center
     */

    private function get_user_notifications_detailed($user_id, $atts)
    {
        // Mock data for now - this would integrate with actual notification system
        $mock_notifications = array(
            (object) array(
                'id' => 1,
                'title' => 'Class Created - Load Learners Required',
                'message' => 'A new training class has been created and requires learner information to be loaded.',
                'type' => 'reminder',
                'status' => 'unread',
                'class_id' => 123,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'action_url' => admin_url('admin.php?page=wecoza-classes&action=edit&class_id=123'),
                'action_text' => 'Load Learners'
            ),
            (object) array(
                'id' => 2,
                'title' => 'Agent Order Submitted',
                'message' => 'Training agent order has been successfully submitted and is pending approval.',
                'type' => 'confirmation',
                'status' => 'read',
                'class_id' => 122,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'action_url' => null,
                'action_text' => null
            ),
            (object) array(
                'id' => 3,
                'title' => 'Material Delivery Overdue',
                'message' => 'Training materials for Class #121 are overdue for delivery. Please arrange immediately.',
                'type' => 'reminder',
                'status' => 'unread',
                'class_id' => 121,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'action_url' => admin_url('admin.php?page=wecoza-classes&action=edit&class_id=121'),
                'action_text' => 'Arrange Delivery'
            )
        );

        return $mock_notifications;
    }

    private function group_notifications_by_date($notifications)
    {
        $grouped = array();

        foreach ($notifications as $notification) {
            $date = date('Y-m-d', strtotime($notification->created_at));
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            if ($date === $today) {
                $group_key = 'Today';
            } elseif ($date === $yesterday) {
                $group_key = 'Yesterday';
            } else {
                $group_key = date('F j, Y', strtotime($date));
            }

            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = array();
            }
            $grouped[$group_key][] = $notification;
        }

        return $grouped;
    }

    private function get_notification_icon($notification)
    {
        $icons = array(
            'reminder' => '⏰',
            'confirmation' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            'info' => 'ℹ️'
        );

        return $icons[$notification->type ?? 'info'] ?? '📧';
    }

    private function get_default_notification_title($notification)
    {
        if ($notification->type === 'reminder') {
            return 'Task Reminder';
        } elseif ($notification->type === 'confirmation') {
            return 'Action Confirmed';
        }
        return 'Notification';
    }

    private function format_notification_time($timestamp)
    {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;

        if ($diff < 3600) { // Less than 1 hour
            $minutes = floor($diff / 60);
            return $minutes . ' minutes ago';
        } elseif ($diff < 86400) { // Less than 1 day
            $hours = floor($diff / 3600);
            return $hours . ' hours ago';
        } elseif ($diff < 604800) { // Less than 1 week
            $days = floor($diff / 86400);
            return $days . ' days ago';
        } else {
            return date('M j, Y', $time);
        }
    }

    private function get_notification_priority_class($notification)
    {
        // Determine priority based on type and content
        if ($notification->type === 'reminder' && strpos($notification->message, 'overdue') !== false) {
            return 'high-priority';
        } elseif ($notification->type === 'reminder') {
            return 'medium-priority';
        }
        return 'normal-priority';
    }
    /**
     * Render recent activity shortcode - Activity feed and event logs
     */
    public function render_recent_activity($atts)
    {
        $atts = shortcode_atts(array(
            'limit' => '10',
            'class_id' => '',
            'user_id' => 'current',
            'types' => 'all',
            'time_range' => '7d',
            'group_by' => 'date',
            'show_avatars' => 'true',
            'show_timestamps' => 'true',
            'compact' => 'false',
            'auto_refresh' => 'true'
        ), $atts, 'wecoza_recent_activity');

        $container_id = 'wecoza-recent-activity-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-recent-activity-container <?php echo $atts['compact'] === 'true' ? 'compact' : ''; ?>"
             data-wecoza-shortcode="recent_activity"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>"
             data-refresh-interval="<?php echo $atts['auto_refresh'] === 'true' ? '30' : '0'; ?>">

            <div class="wecoza-activity-header">
                <h3>📊 Recent Activity</h3>

                <div class="wecoza-activity-filters">
                    <select class="form-select form-select-sm" id="<?php echo esc_attr($container_id); ?>-time-filter">
                        <option value="1d" <?php selected($atts['time_range'], '1d'); ?>>Last 24 hours</option>
                        <option value="7d" <?php selected($atts['time_range'], '7d'); ?>>Last 7 days</option>
                        <option value="30d" <?php selected($atts['time_range'], '30d'); ?>>Last 30 days</option>
                        <option value="all" <?php selected($atts['time_range'], 'all'); ?>>All time</option>
                    </select>

                    <select class="form-select form-select-sm" id="<?php echo esc_attr($container_id); ?>-type-filter">
                        <option value="all" <?php selected($atts['types'], 'all'); ?>>All Activities</option>
                        <option value="class_created">Class Created</option>
                        <option value="learners_loaded">Learners Loaded</option>
                        <option value="agent_ordered">Agent Ordered</option>
                        <option value="schedule_set">Schedule Set</option>
                        <option value="materials_delivered">Materials Delivered</option>
                        <option value="paperwork_completed">Paperwork Completed</option>
                    </select>
                </div>
            </div>

            <div class="wecoza-activity-feed">
                <?php echo $this->get_recent_activity_content($atts); ?>
            </div>

            <?php if ($atts['auto_refresh'] === 'true'): ?>
                <div class="wecoza-activity-footer">
                    <small class="text-muted">
                        <span class="wecoza-last-updated">Last updated: <?php echo date('g:i A'); ?></span>
                        • Auto-refreshes every 30 seconds
                    </small>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get recent activity content
     */
    private function get_recent_activity_content($atts)
    {
        $activities = $this->get_recent_activities($atts);

        if (empty($activities)) {
            return '<div class="wecoza-no-activity">
                        <div class="wecoza-empty-state">
                            <span class="wecoza-empty-icon">📭</span>
                            <h5>No recent activity</h5>
                            <p>No activities found for the selected time period.</p>
                        </div>
                    </div>';
        }

        ob_start();

        if ($atts['group_by'] === 'date') {
            $grouped = $this->group_activities_by_date($activities);
            foreach ($grouped as $date_group => $group_activities) {
                ?>
                <div class="wecoza-activity-group">
                    <h6 class="wecoza-activity-date-header"><?php echo esc_html($date_group); ?></h6>
                    <div class="wecoza-activity-timeline">
                        <?php foreach ($group_activities as $activity): ?>
                            <?php echo $this->render_single_activity($activity, $atts); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="wecoza-activity-timeline">
                <?php foreach ($activities as $activity): ?>
                    <?php echo $this->render_single_activity($activity, $atts); ?>
                <?php endforeach; ?>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Render a single activity item
     */
    private function render_single_activity($activity, $atts)
    {
        $activity_type = $activity->activity_type ?? 'general';
        $priority_class = $this->get_activity_priority_class($activity);

        ob_start();
        ?>
        <div class="wecoza-activity-item <?php echo esc_attr($activity_type . ' ' . $priority_class); ?>"
             data-activity-id="<?php echo esc_attr($activity->id ?? ''); ?>"
             data-class-id="<?php echo esc_attr($activity->class_id ?? ''); ?>">

            <div class="wecoza-activity-timeline-marker">
                <span class="wecoza-activity-icon">
                    <?php echo $this->get_activity_icon($activity); ?>
                </span>
            </div>

            <div class="wecoza-activity-content">
                <div class="wecoza-activity-header">
                    <?php if ($atts['show_avatars'] === 'true' && !empty($activity->user_id)): ?>
                        <div class="wecoza-activity-avatar">
                            <?php echo get_avatar($activity->user_id, 32); ?>
                        </div>
                    <?php endif; ?>

                    <div class="wecoza-activity-meta">
                        <h6 class="wecoza-activity-title">
                            <?php echo esc_html($this->get_activity_title($activity)); ?>
                        </h6>

                        <div class="wecoza-activity-details">
                            <span class="wecoza-activity-user">
                                <?php echo esc_html($this->get_activity_user_name($activity)); ?>
                            </span>

                            <?php if ($atts['show_timestamps'] === 'true'): ?>
                                <span class="wecoza-activity-time">
                                    <?php echo $this->format_activity_time($activity->created_at ?? current_time('mysql')); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="wecoza-activity-description">
                    <p><?php echo esc_html($this->get_activity_description($activity)); ?></p>

                    <?php if (!empty($activity->class_id)): ?>
                        <div class="wecoza-activity-context">
                            <span class="wecoza-class-context">
                                📚 <?php echo esc_html($this->get_class_name($activity->class_id)); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($activity->metadata)): ?>
                    <div class="wecoza-activity-metadata">
                        <?php echo $this->render_activity_metadata($activity->metadata); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper methods for recent activity
     */

    private function get_recent_activities($atts)
    {
        // Mock data for now - this would integrate with the events log table
        $mock_activities = array(
            (object) array(
                'id' => 1,
                'activity_type' => 'class_created',
                'user_id' => 1,
                'class_id' => 123,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'metadata' => json_encode(array('client_name' => 'ABC Corporation', 'course_type' => 'Safety Training'))
            ),
            (object) array(
                'id' => 2,
                'activity_type' => 'learners_loaded',
                'user_id' => 2,
                'class_id' => 123,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'metadata' => json_encode(array('learner_count' => 25))
            ),
            (object) array(
                'id' => 3,
                'activity_type' => 'agent_ordered',
                'user_id' => 1,
                'class_id' => 122,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'metadata' => json_encode(array('agent_name' => 'John Instructor'))
            ),
            (object) array(
                'id' => 4,
                'activity_type' => 'schedule_set',
                'user_id' => 3,
                'class_id' => 121,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'metadata' => json_encode(array('start_date' => '2025-01-15', 'duration' => '3 days'))
            ),
            (object) array(
                'id' => 5,
                'activity_type' => 'materials_delivered',
                'user_id' => 4,
                'class_id' => 120,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'metadata' => json_encode(array('delivery_method' => 'Courier', 'tracking_number' => 'TN123456'))
            )
        );

        return array_slice($mock_activities, 0, intval($atts['limit']));
    }

    private function group_activities_by_date($activities)
    {
        $grouped = array();

        foreach ($activities as $activity) {
            $date = date('Y-m-d', strtotime($activity->created_at));
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            if ($date === $today) {
                $group_key = 'Today';
            } elseif ($date === $yesterday) {
                $group_key = 'Yesterday';
            } else {
                $group_key = date('F j, Y', strtotime($date));
            }

            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = array();
            }
            $grouped[$group_key][] = $activity;
        }

        return $grouped;
    }

    private function get_activity_icon($activity)
    {
        $icons = array(
            'class_created' => '🆕',
            'learners_loaded' => '👥',
            'agent_ordered' => '👨‍🏫',
            'schedule_set' => '📅',
            'materials_delivered' => '📦',
            'paperwork_completed' => '📄',
            'general' => '📋'
        );

        return $icons[$activity->activity_type ?? 'general'] ?? '📋';
    }

    private function get_activity_title($activity)
    {
        $titles = array(
            'class_created' => 'Class Created',
            'learners_loaded' => 'Learners Loaded',
            'agent_ordered' => 'Training Agent Ordered',
            'schedule_set' => 'Training Schedule Set',
            'materials_delivered' => 'Materials Delivered',
            'paperwork_completed' => 'Agent Paperwork Completed'
        );

        return $titles[$activity->activity_type ?? 'general'] ?? 'Activity Logged';
    }

    private function get_activity_description($activity)
    {
        $descriptions = array(
            'class_created' => 'A new training class has been created and is ready for setup.',
            'learners_loaded' => 'Learner roster has been uploaded and validated.',
            'agent_ordered' => 'Training agent has been requested and order submitted.',
            'schedule_set' => 'Training dates and schedule have been confirmed.',
            'materials_delivered' => 'Training materials have been delivered to the venue.',
            'paperwork_completed' => 'All required agent documentation has been completed.'
        );

        return $descriptions[$activity->activity_type ?? 'general'] ?? 'Activity completed successfully.';
    }

    private function get_activity_user_name($activity)
    {
        if (empty($activity->user_id)) {
            return 'System';
        }

        $user = get_user_by('id', $activity->user_id);
        return $user ? $user->display_name : 'Unknown User';
    }

    private function format_activity_time($timestamp)
    {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;

        if ($diff < 3600) { // Less than 1 hour
            $minutes = floor($diff / 60);
            return $minutes . 'm ago';
        } elseif ($diff < 86400) { // Less than 1 day
            $hours = floor($diff / 3600);
            return $hours . 'h ago';
        } else {
            return date('M j, g:i A', $time);
        }
    }

    private function get_activity_priority_class($activity)
    {
        // Determine priority based on activity type and recency
        $created_time = strtotime($activity->created_at ?? current_time('mysql'));
        $age_hours = (time() - $created_time) / 3600;

        if ($age_hours < 1) {
            return 'recent';
        } elseif ($age_hours < 24) {
            return 'today';
        }

        return 'older';
    }

    private function render_activity_metadata($metadata_json)
    {
        $metadata = json_decode($metadata_json, true);
        if (empty($metadata)) {
            return '';
        }

        ob_start();
        ?>
        <div class="wecoza-metadata-tags">
            <?php foreach ($metadata as $key => $value): ?>
                <span class="wecoza-metadata-tag">
                    <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</strong>
                    <?php echo esc_html($value); ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * Render deadline tracker shortcode - Track upcoming deadlines and overdue tasks
     */
    public function render_deadline_tracker($atts)
    {
        $atts = shortcode_atts(array(
            'user_id' => 'current',
            'days_ahead' => '30',
            'show_overdue' => 'true',
            'group_by' => 'urgency',
            'limit' => '20',
            'compact' => 'false',
            'show_charts' => 'true',
            'auto_refresh' => 'true'
        ), $atts, 'wecoza_deadline_tracker');

        $container_id = 'wecoza-deadline-tracker-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-deadline-tracker-container <?php echo $atts['compact'] === 'true' ? 'compact' : ''; ?>"
             data-wecoza-shortcode="deadline_tracker"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>"
             data-refresh-interval="<?php echo $atts['auto_refresh'] === 'true' ? '30' : '0'; ?>">

            <div class="wecoza-deadline-tracker-header">
                <h3>⏰ Deadline Tracker</h3>

                <div class="wecoza-deadline-controls">
                    <select class="form-select form-select-sm" id="<?php echo esc_attr($container_id); ?>-range-filter">
                        <option value="7" <?php selected($atts['days_ahead'], '7'); ?>>Next 7 days</option>
                        <option value="14" <?php selected($atts['days_ahead'], '14'); ?>>Next 14 days</option>
                        <option value="30" <?php selected($atts['days_ahead'], '30'); ?>>Next 30 days</option>
                        <option value="60" <?php selected($atts['days_ahead'], '60'); ?>>Next 60 days</option>
                    </select>

                    <select class="form-select form-select-sm" id="<?php echo esc_attr($container_id); ?>-group-filter">
                        <option value="urgency" <?php selected($atts['group_by'], 'urgency'); ?>>Group by Urgency</option>
                        <option value="date" <?php selected($atts['group_by'], 'date'); ?>>Group by Date</option>
                        <option value="class" <?php selected($atts['group_by'], 'class'); ?>>Group by Class</option>
                    </select>
                </div>
            </div>

            <?php if ($atts['show_charts'] === 'true'): ?>
                <div class="wecoza-deadline-summary">
                    <?php echo $this->get_deadline_summary($atts); ?>
                </div>
            <?php endif; ?>

            <div class="wecoza-deadline-content">
                <?php echo $this->get_deadline_tracker_content($atts); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get deadline tracker content
     */
    private function get_deadline_tracker_content($atts)
    {
        $deadlines = $this->get_upcoming_deadlines($atts);

        if (empty($deadlines)) {
            return '<div class="wecoza-no-deadlines">
                        <div class="wecoza-empty-state">
                            <span class="wecoza-empty-icon">✅</span>
                            <h5>No upcoming deadlines</h5>
                            <p>All tasks are on track! No deadlines in the selected time range.</p>
                        </div>
                    </div>';
        }

        ob_start();

        if ($atts['group_by'] === 'urgency') {
            $grouped = $this->group_deadlines_by_urgency($deadlines);
            foreach ($grouped as $urgency_group => $group_deadlines) {
                $urgency_class = strtolower(str_replace(' ', '-', $urgency_group));
                ?>
                <div class="wecoza-deadline-group <?php echo esc_attr($urgency_class); ?>">
                    <h5 class="wecoza-deadline-group-header">
                        <?php echo $this->get_urgency_icon($urgency_group); ?>
                        <?php echo esc_html($urgency_group); ?>
                        <span class="wecoza-group-count">(<?php echo count($group_deadlines); ?>)</span>
                    </h5>
                    <div class="wecoza-deadline-list">
                        <?php foreach ($group_deadlines as $deadline): ?>
                            <?php echo $this->render_single_deadline($deadline, $atts); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            }
        } elseif ($atts['group_by'] === 'date') {
            $grouped = $this->group_deadlines_by_date($deadlines);
            foreach ($grouped as $date_group => $group_deadlines) {
                ?>
                <div class="wecoza-deadline-group">
                    <h5 class="wecoza-deadline-group-header">
                        📅 <?php echo esc_html($date_group); ?>
                        <span class="wecoza-group-count">(<?php echo count($group_deadlines); ?>)</span>
                    </h5>
                    <div class="wecoza-deadline-list">
                        <?php foreach ($group_deadlines as $deadline): ?>
                            <?php echo $this->render_single_deadline($deadline, $atts); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="wecoza-deadline-list">
                <?php foreach ($deadlines as $deadline): ?>
                    <?php echo $this->render_single_deadline($deadline, $atts); ?>
                <?php endforeach; ?>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Render a single deadline item
     */
    private function render_single_deadline($deadline, $atts)
    {
        $urgency_class = $this->get_deadline_urgency_class($deadline);
        $status_class = $deadline->status ?? 'open';

        ob_start();
        ?>
        <div class="wecoza-deadline-item <?php echo esc_attr($urgency_class . ' ' . $status_class); ?>"
             data-class-id="<?php echo esc_attr($deadline->class_id); ?>"
             data-task="<?php echo esc_attr($deadline->task_type); ?>">

            <div class="wecoza-deadline-indicator">
                <span class="wecoza-deadline-icon">
                    <?php echo $this->get_task_icon($deadline->task_type); ?>
                </span>
                <div class="wecoza-deadline-urgency">
                    <?php echo $this->get_deadline_urgency_badge($deadline); ?>
                </div>
            </div>

            <div class="wecoza-deadline-details">
                <div class="wecoza-deadline-header">
                    <h6 class="wecoza-deadline-title">
                        <?php echo esc_html($this->get_task_title($deadline->task_type)); ?>
                    </h6>
                    <span class="wecoza-deadline-class">
                        <?php echo esc_html($this->get_class_name($deadline->class_id)); ?>
                    </span>
                </div>

                <div class="wecoza-deadline-meta">
                    <div class="wecoza-deadline-date">
                        <strong>Due:</strong> <?php echo date('M j, Y', strtotime($deadline->due_date)); ?>
                        <span class="wecoza-days-remaining">
                            (<?php echo $this->get_days_remaining_text($deadline); ?>)
                        </span>
                    </div>

                    <?php if (!empty($deadline->description)): ?>
                        <p class="wecoza-deadline-description">
                            <?php echo esc_html($deadline->description); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($deadline->status === 'open'): ?>
                    <div class="wecoza-deadline-actions">
                        <button class="btn btn-sm btn-primary wecoza-complete-task"
                                data-class-id="<?php echo esc_attr($deadline->class_id); ?>"
                                data-task="<?php echo esc_attr($deadline->task_type); ?>">
                            Complete Now
                        </button>
                        <a href="<?php echo esc_url($this->get_task_url($deadline)); ?>"
                           class="btn btn-sm btn-outline-secondary">
                            View Details
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($urgency_class === 'overdue'): ?>
                <div class="wecoza-overdue-flag">
                    <span>⚠️ OVERDUE</span>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get deadline summary for charts
     */
    private function get_deadline_summary($atts)
    {
        $deadlines = $this->get_upcoming_deadlines($atts);
        $summary = $this->analyze_deadline_summary($deadlines);

        ob_start();
        ?>
        <div class="wecoza-deadline-summary-grid">
            <div class="wecoza-summary-card overdue">
                <div class="wecoza-summary-number"><?php echo $summary['overdue']; ?></div>
                <div class="wecoza-summary-label">Overdue</div>
            </div>
            <div class="wecoza-summary-card urgent">
                <div class="wecoza-summary-number"><?php echo $summary['urgent']; ?></div>
                <div class="wecoza-summary-label">Due Soon</div>
            </div>
            <div class="wecoza-summary-card warning">
                <div class="wecoza-summary-number"><?php echo $summary['warning']; ?></div>
                <div class="wecoza-summary-label">This Week</div>
            </div>
            <div class="wecoza-summary-card normal">
                <div class="wecoza-summary-number"><?php echo $summary['normal']; ?></div>
                <div class="wecoza-summary-label">Later</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper methods for deadline tracker
     */

    private function get_upcoming_deadlines($atts)
    {
        $user_id = $atts['user_id'] === 'current' ? get_current_user_id() : intval($atts['user_id']);
        $days_ahead = intval($atts['days_ahead']);

        // Mock data for now - this would integrate with the dashboard_status table
        $mock_deadlines = array(
            (object) array(
                'id' => 1,
                'class_id' => 123,
                'task_type' => 'load_learners',
                'due_date' => date('Y-m-d', strtotime('-2 days')), // Overdue
                'status' => 'open',
                'description' => 'Upload learner roster for safety training class'
            ),
            (object) array(
                'id' => 2,
                'class_id' => 124,
                'task_type' => 'agent_order',
                'due_date' => date('Y-m-d', strtotime('+1 day')), // Urgent
                'status' => 'open',
                'description' => 'Submit training agent order for instructor assignment'
            ),
            (object) array(
                'id' => 3,
                'class_id' => 125,
                'task_type' => 'training_schedule',
                'due_date' => date('Y-m-d', strtotime('+5 days')), // Warning
                'status' => 'open',
                'description' => 'Confirm training dates and schedule with client'
            ),
            (object) array(
                'id' => 4,
                'class_id' => 126,
                'task_type' => 'material_delivery',
                'due_date' => date('Y-m-d', strtotime('+14 days')), // Normal
                'status' => 'open',
                'description' => 'Arrange delivery of training materials to venue'
            ),
            (object) array(
                'id' => 5,
                'class_id' => 127,
                'task_type' => 'agent_paperwork',
                'due_date' => date('Y-m-d', strtotime('+20 days')), // Normal
                'status' => 'open',
                'description' => 'Complete and submit agent documentation'
            )
        );

        return array_slice($mock_deadlines, 0, intval($atts['limit']));
    }

    private function group_deadlines_by_urgency($deadlines)
    {
        $grouped = array(
            'Overdue' => array(),
            'Due Today' => array(),
            'Due This Week' => array(),
            'Due Later' => array()
        );

        foreach ($deadlines as $deadline) {
            $urgency = $this->get_deadline_urgency_group($deadline);
            $grouped[$urgency][] = $deadline;
        }

        // Remove empty groups
        return array_filter($grouped);
    }

    private function group_deadlines_by_date($deadlines)
    {
        $grouped = array();

        foreach ($deadlines as $deadline) {
            $date = date('Y-m-d', strtotime($deadline->due_date));
            $today = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));

            if ($date < $today) {
                $group_key = 'Overdue';
            } elseif ($date === $today) {
                $group_key = 'Today';
            } elseif ($date === $tomorrow) {
                $group_key = 'Tomorrow';
            } else {
                $group_key = date('F j, Y', strtotime($date));
            }

            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = array();
            }
            $grouped[$group_key][] = $deadline;
        }

        return $grouped;
    }

    private function get_deadline_urgency_class($deadline)
    {
        $days_remaining = $this->get_days_remaining($deadline);

        if ($days_remaining < 0) {
            return 'overdue';
        } elseif ($days_remaining === 0) {
            return 'due-today';
        } elseif ($days_remaining <= 2) {
            return 'urgent';
        } elseif ($days_remaining <= 7) {
            return 'warning';
        }

        return 'normal';
    }

    private function get_deadline_urgency_group($deadline)
    {
        $days_remaining = $this->get_days_remaining($deadline);

        if ($days_remaining < 0) {
            return 'Overdue';
        } elseif ($days_remaining === 0) {
            return 'Due Today';
        } elseif ($days_remaining <= 7) {
            return 'Due This Week';
        }

        return 'Due Later';
    }

    private function get_days_remaining($deadline)
    {
        $due_date = strtotime($deadline->due_date);
        $today = strtotime(date('Y-m-d'));

        return floor(($due_date - $today) / DAY_IN_SECONDS);
    }

    private function get_days_remaining_text($deadline)
    {
        $days = $this->get_days_remaining($deadline);

        if ($days < 0) {
            return abs($days) . ' days overdue';
        } elseif ($days === 0) {
            return 'due today';
        } elseif ($days === 1) {
            return 'due tomorrow';
        } else {
            return $days . ' days remaining';
        }
    }

    private function get_deadline_urgency_badge($deadline)
    {
        $urgency_class = $this->get_deadline_urgency_class($deadline);
        $days = $this->get_days_remaining($deadline);

        $badges = array(
            'overdue' => '<span class="badge bg-danger">Overdue</span>',
            'due-today' => '<span class="badge bg-warning">Today</span>',
            'urgent' => '<span class="badge bg-warning">Urgent</span>',
            'warning' => '<span class="badge bg-info">' . $days . ' days</span>',
            'normal' => '<span class="badge bg-secondary">' . $days . ' days</span>'
        );

        return $badges[$urgency_class] ?? '';
    }

    private function get_urgency_icon($urgency_group)
    {
        $icons = array(
            'Overdue' => '🚨',
            'Due Today' => '⏰',
            'Due This Week' => '⚠️',
            'Due Later' => '📅'
        );

        return $icons[$urgency_group] ?? '📋';
    }

    private function analyze_deadline_summary($deadlines)
    {
        $summary = array(
            'overdue' => 0,
            'urgent' => 0,
            'warning' => 0,
            'normal' => 0
        );

        foreach ($deadlines as $deadline) {
            $urgency_class = $this->get_deadline_urgency_class($deadline);

            switch ($urgency_class) {
                case 'overdue':
                    $summary['overdue']++;
                    break;
                case 'due-today':
                case 'urgent':
                    $summary['urgent']++;
                    break;
                case 'warning':
                    $summary['warning']++;
                    break;
                default:
                    $summary['normal']++;
                    break;
            }
        }

        return $summary;
    }
    /**
     * Render supervisor dashboard shortcode - Comprehensive supervisor view
     */
    public function render_supervisor_dashboard($atts)
    {
        $atts = shortcode_atts(array(
            'supervisor_id' => 'current',
            'view' => 'overview',
            'show_stats' => 'true',
            'show_recent' => 'true',
            'limit' => '20',
            'auto_refresh' => 'true'
        ), $atts, 'wecoza_supervisor_dashboard');

        $container_id = 'wecoza-supervisor-dashboard-' . uniqid();
        $supervisor_id = $atts['supervisor_id'] === 'current' ? get_current_user_id() : intval($atts['supervisor_id']);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-supervisor-dashboard-container"
             data-wecoza-shortcode="supervisor_dashboard"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>"
             data-refresh-interval="<?php echo $atts['auto_refresh'] === 'true' ? '30' : '0'; ?>">

            <div class="wecoza-supervisor-header">
                <h3>👨‍💼 Supervisor Dashboard</h3>
                <div class="wecoza-supervisor-info">
                    <span class="wecoza-supervisor-name"><?php echo esc_html($this->get_supervisor_name($supervisor_id)); ?></span>
                    <span class="wecoza-last-login">Last login: <?php echo date('M j, g:i A'); ?></span>
                </div>
            </div>

            <?php if ($atts['show_stats'] === 'true'): ?>
                <div class="wecoza-supervisor-stats">
                    <?php echo $this->get_supervisor_stats($supervisor_id); ?>
                </div>
            <?php endif; ?>

            <div class="wecoza-supervisor-content">
                <?php echo $this->get_supervisor_dashboard_content($supervisor_id, $atts); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get supervisor dashboard content
     */
    private function get_supervisor_dashboard_content($supervisor_id, $atts)
    {
        ob_start();
        ?>
        <div class="wecoza-dashboard-sections">

            <!-- Classes Overview -->
            <div class="wecoza-dashboard-section">
                <h5>📚 My Classes Overview</h5>
                <div class="wecoza-classes-grid">
                    <?php echo $this->get_supervisor_classes($supervisor_id); ?>
                </div>
            </div>

            <!-- Priority Tasks -->
            <div class="wecoza-dashboard-section">
                <h5>🚨 Priority Tasks</h5>
                <div class="wecoza-priority-tasks">
                    <?php echo $this->get_supervisor_priority_tasks($supervisor_id); ?>
                </div>
            </div>

            <!-- Team Performance -->
            <div class="wecoza-dashboard-section">
                <h5>📊 Team Performance</h5>
                <div class="wecoza-team-performance">
                    <?php echo $this->get_team_performance($supervisor_id); ?>
                </div>
            </div>

            <?php if ($atts['show_recent'] === 'true'): ?>
                <!-- Recent Activity -->
                <div class="wecoza-dashboard-section">
                    <h5>📈 Recent Activity</h5>
                    <div class="wecoza-recent-supervisor-activity">
                        <?php echo $this->get_supervisor_recent_activity($supervisor_id, 5); ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get supervisor statistics
     */
    private function get_supervisor_stats($supervisor_id)
    {
        $stats = $this->calculate_supervisor_stats($supervisor_id);

        ob_start();
        ?>
        <div class="wecoza-stats-grid">
            <div class="wecoza-stat-card active-classes">
                <div class="wecoza-stat-number"><?php echo $stats['active_classes']; ?></div>
                <div class="wecoza-stat-label">Active Classes</div>
            </div>
            <div class="wecoza-stat-card pending-tasks">
                <div class="wecoza-stat-number"><?php echo $stats['pending_tasks']; ?></div>
                <div class="wecoza-stat-label">Pending Tasks</div>
            </div>
            <div class="wecoza-stat-card overdue-items">
                <div class="wecoza-stat-number"><?php echo $stats['overdue_items']; ?></div>
                <div class="wecoza-stat-label">Overdue Items</div>
            </div>
            <div class="wecoza-stat-card completion-rate">
                <div class="wecoza-stat-number"><?php echo $stats['completion_rate']; ?>%</div>
                <div class="wecoza-stat-label">Completion Rate</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper methods for supervisor dashboard
     */
    private function get_supervisor_name($supervisor_id)
    {
        $user = get_user_by('id', $supervisor_id);
        return $user ? $user->display_name : 'Unknown Supervisor';
    }

    private function calculate_supervisor_stats($supervisor_id)
    {
        // Mock data - would integrate with actual database
        return array(
            'active_classes' => 8,
            'pending_tasks' => 12,
            'overdue_items' => 3,
            'completion_rate' => 85
        );
    }

    private function get_supervisor_classes($supervisor_id)
    {
        // Mock data for supervisor's classes
        $classes = array(
            array('id' => 123, 'name' => 'Safety Training - ABC Corp', 'status' => 'in_progress', 'progress' => 60),
            array('id' => 124, 'name' => 'First Aid Certification - XYZ Inc', 'status' => 'pending_learners', 'progress' => 25),
            array('id' => 125, 'name' => 'Fire Safety - DEF Ltd', 'status' => 'scheduled', 'progress' => 90)
        );

        ob_start();
        foreach ($classes as $class) {
            $status_class = str_replace('_', '-', $class['status']);
            ?>
            <div class="wecoza-class-card <?php echo esc_attr($status_class); ?>">
                <h6><?php echo esc_html($class['name']); ?></h6>
                <div class="wecoza-class-progress">
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo $class['progress']; ?>%"></div>
                    </div>
                    <span><?php echo $class['progress']; ?>% Complete</span>
                </div>
                <div class="wecoza-class-status">
                    <?php echo $this->get_class_status_badge($class['status']); ?>
                </div>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    private function get_supervisor_priority_tasks($supervisor_id)
    {
        // Mock priority tasks
        $tasks = array(
            array('class_id' => 123, 'task' => 'load_learners', 'urgency' => 'high', 'due' => '-1 day'),
            array('class_id' => 124, 'task' => 'agent_order', 'urgency' => 'medium', 'due' => '+1 day'),
            array('class_id' => 125, 'task' => 'material_delivery', 'urgency' => 'low', 'due' => '+5 days')
        );

        ob_start();
        foreach ($tasks as $task) {
            ?>
            <div class="wecoza-priority-task <?php echo esc_attr($task['urgency']); ?>">
                <span class="wecoza-task-icon"><?php echo $this->get_task_icon($task['task']); ?></span>
                <div class="wecoza-task-details">
                    <strong><?php echo esc_html($this->get_task_title($task['task'])); ?></strong>
                    <span>Class #<?php echo $task['class_id']; ?></span>
                </div>
                <span class="wecoza-urgency-badge <?php echo esc_attr($task['urgency']); ?>">
                    <?php echo ucfirst($task['urgency']); ?>
                </span>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    private function get_team_performance($supervisor_id)
    {
        ob_start();
        ?>
        <div class="wecoza-performance-metrics">
            <div class="wecoza-metric">
                <label>On-time Completion</label>
                <div class="wecoza-metric-bar">
                    <div class="wecoza-metric-fill" style="width: 85%"></div>
                </div>
                <span>85%</span>
            </div>
            <div class="wecoza-metric">
                <label>Quality Score</label>
                <div class="wecoza-metric-bar">
                    <div class="wecoza-metric-fill" style="width: 92%"></div>
                </div>
                <span>92%</span>
            </div>
            <div class="wecoza-metric">
                <label>Task Efficiency</label>
                <div class="wecoza-metric-bar">
                    <div class="wecoza-metric-fill" style="width: 78%"></div>
                </div>
                <span>78%</span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_supervisor_recent_activity($supervisor_id, $limit)
    {
        // Mock recent activity for supervisor
        $activities = array(
            array('type' => 'class_created', 'class_id' => 123, 'time' => '-2 hours'),
            array('type' => 'learners_loaded', 'class_id' => 124, 'time' => '-4 hours'),
            array('type' => 'agent_ordered', 'class_id' => 125, 'time' => '-1 day')
        );

        ob_start();
        ?>
        <div class="wecoza-supervisor-activity-feed">
            <?php foreach (array_slice($activities, 0, $limit) as $activity): ?>
                <div class="wecoza-activity-item">
                    <span class="wecoza-activity-icon"><?php echo $this->get_activity_icon((object)$activity); ?></span>
                    <div class="wecoza-activity-content">
                        <span><?php echo esc_html($this->get_activity_title((object)$activity)); ?></span>
                        <small>Class #<?php echo $activity['class_id']; ?> • <?php echo $activity['time']; ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_class_status_badge($status)
    {
        $badges = array(
            'in_progress' => '<span class="badge bg-primary">In Progress</span>',
            'pending_learners' => '<span class="badge bg-warning">Pending Learners</span>',
            'scheduled' => '<span class="badge bg-success">Scheduled</span>',
            'completed' => '<span class="badge bg-success">Completed</span>'
        );

        return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
    /**
     * Render quick actions shortcode - Action buttons and shortcuts
     */
    public function render_quick_actions($atts)
    {
        $atts = shortcode_atts(array(
            'user_id' => 'current',
            'context' => 'general',
            'class_id' => '',
            'layout' => 'grid',
            'size' => 'medium',
            'show_counts' => 'true'
        ), $atts, 'wecoza_quick_actions');

        $container_id = 'wecoza-quick-actions-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-quick-actions-container <?php echo esc_attr($atts['layout'] . ' ' . $atts['size']); ?>"
             data-wecoza-shortcode="quick_actions"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>">

            <div class="wecoza-quick-actions-header">
                <h4>⚡ Quick Actions</h4>
            </div>

            <div class="wecoza-actions-grid">
                <?php echo $this->get_quick_actions_content($atts); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get quick actions content
     */
    private function get_quick_actions_content($atts)
    {
        $actions = $this->get_available_actions($atts);

        ob_start();
        foreach ($actions as $action) {
            $count_text = $atts['show_counts'] === 'true' && $action['count'] > 0 ?
                ' (' . $action['count'] . ')' : '';
            ?>
            <div class="wecoza-action-button <?php echo esc_attr($action['type'] . ' ' . $action['priority']); ?>"
                 data-action="<?php echo esc_attr($action['action']); ?>"
                 data-class-id="<?php echo esc_attr($atts['class_id']); ?>">

                <div class="wecoza-action-icon">
                    <?php echo $action['icon']; ?>
                </div>

                <div class="wecoza-action-content">
                    <h6><?php echo esc_html($action['title'] . $count_text); ?></h6>
                    <p><?php echo esc_html($action['description']); ?></p>
                </div>

                <?php if ($action['count'] > 0 && $atts['show_counts'] === 'true'): ?>
                    <div class="wecoza-action-badge">
                        <span class="badge bg-<?php echo $action['badge_color']; ?>">
                            <?php echo $action['count']; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($action['url'])): ?>
                    <a href="<?php echo esc_url($action['url']); ?>" class="wecoza-action-link"></a>
                <?php endif; ?>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Get available actions based on context
     */
    private function get_available_actions($atts)
    {
        $user_id = $atts['user_id'] === 'current' ? get_current_user_id() : intval($atts['user_id']);
        $context = $atts['context'];
        $class_id = $atts['class_id'];

        $actions = array();

        if ($context === 'general' || empty($context)) {
            $actions = array(
                array(
                    'action' => 'create_class',
                    'title' => 'Create New Class',
                    'description' => 'Start a new training class',
                    'icon' => '🆕',
                    'type' => 'primary',
                    'priority' => 'high',
                    'count' => 0,
                    'badge_color' => 'primary',
                    'url' => admin_url('admin.php?page=wecoza-classes&action=create')
                ),
                array(
                    'action' => 'pending_approvals',
                    'title' => 'Pending Approvals',
                    'description' => 'Review items awaiting approval',
                    'icon' => '✅',
                    'type' => 'warning',
                    'priority' => 'high',
                    'count' => 5,
                    'badge_color' => 'warning',
                    'url' => admin_url('admin.php?page=wecoza-approvals')
                ),
                array(
                    'action' => 'overdue_tasks',
                    'title' => 'Overdue Tasks',
                    'description' => 'Address overdue items',
                    'icon' => '🚨',
                    'type' => 'danger',
                    'priority' => 'urgent',
                    'count' => 3,
                    'badge_color' => 'danger',
                    'url' => admin_url('admin.php?page=wecoza-tasks&filter=overdue')
                ),
                array(
                    'action' => 'upload_learners',
                    'title' => 'Upload Learners',
                    'description' => 'Bulk upload learner data',
                    'icon' => '👥',
                    'type' => 'info',
                    'priority' => 'medium',
                    'count' => 2,
                    'badge_color' => 'info',
                    'url' => admin_url('admin.php?page=wecoza-learners&action=upload')
                ),
                array(
                    'action' => 'generate_reports',
                    'title' => 'Generate Reports',
                    'description' => 'Create progress reports',
                    'icon' => '📊',
                    'type' => 'secondary',
                    'priority' => 'low',
                    'count' => 0,
                    'badge_color' => 'secondary',
                    'url' => admin_url('admin.php?page=wecoza-reports')
                ),
                array(
                    'action' => 'schedule_training',
                    'title' => 'Schedule Training',
                    'description' => 'Set training dates',
                    'icon' => '📅',
                    'type' => 'success',
                    'priority' => 'medium',
                    'count' => 7,
                    'badge_color' => 'success',
                    'url' => admin_url('admin.php?page=wecoza-schedule')
                )
            );
        } elseif ($context === 'class' && !empty($class_id)) {
            $actions = array(
                array(
                    'action' => 'load_learners',
                    'title' => 'Load Learners',
                    'description' => 'Upload learner roster',
                    'icon' => '👥',
                    'type' => 'primary',
                    'priority' => 'high',
                    'count' => 0,
                    'badge_color' => 'primary',
                    'url' => admin_url('admin.php?page=wecoza-classes&action=load_learners&class_id=' . $class_id)
                ),
                array(
                    'action' => 'order_agent',
                    'title' => 'Order Agent',
                    'description' => 'Submit agent request',
                    'icon' => '👨‍🏫',
                    'type' => 'warning',
                    'priority' => 'high',
                    'count' => 0,
                    'badge_color' => 'warning',
                    'url' => admin_url('admin.php?page=wecoza-classes&action=order_agent&class_id=' . $class_id)
                ),
                array(
                    'action' => 'set_schedule',
                    'title' => 'Set Schedule',
                    'description' => 'Configure training dates',
                    'icon' => '📅',
                    'type' => 'info',
                    'priority' => 'medium',
                    'count' => 0,
                    'badge_color' => 'info',
                    'url' => admin_url('admin.php?page=wecoza-classes&action=set_schedule&class_id=' . $class_id)
                ),
                array(
                    'action' => 'arrange_materials',
                    'title' => 'Arrange Materials',
                    'description' => 'Setup material delivery',
                    'icon' => '📦',
                    'type' => 'success',
                    'priority' => 'medium',
                    'count' => 0,
                    'badge_color' => 'success',
                    'url' => admin_url('admin.php?page=wecoza-classes&action=materials&class_id=' . $class_id)
                )
            );
        } elseif ($context === 'supervisor') {
            $actions = array(
                array(
                    'action' => 'approve_classes',
                    'title' => 'Approve Classes',
                    'description' => 'Review pending class approvals',
                    'icon' => '✅',
                    'type' => 'primary',
                    'priority' => 'high',
                    'count' => 4,
                    'badge_color' => 'primary',
                    'url' => admin_url('admin.php?page=wecoza-supervisor&action=approvals')
                ),
                array(
                    'action' => 'review_reports',
                    'title' => 'Review Reports',
                    'description' => 'Check team performance',
                    'icon' => '📋',
                    'type' => 'info',
                    'priority' => 'medium',
                    'count' => 2,
                    'badge_color' => 'info',
                    'url' => admin_url('admin.php?page=wecoza-supervisor&action=reports')
                ),
                array(
                    'action' => 'manage_team',
                    'title' => 'Manage Team',
                    'description' => 'Team member assignments',
                    'icon' => '👥',
                    'type' => 'secondary',
                    'priority' => 'low',
                    'count' => 0,
                    'badge_color' => 'secondary',
                    'url' => admin_url('admin.php?page=wecoza-supervisor&action=team')
                )
            );
        }

        // Sort actions by priority
        usort($actions, function($a, $b) {
            $priority_order = array('urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3);
            return $priority_order[$a['priority']] - $priority_order[$b['priority']];
        });

        return $actions;
    }
    /**
     * Render class timeline shortcode - Visual timeline of class progress
     */
    public function render_class_timeline($atts)
    {
        $atts = shortcode_atts(array(
            'class_id' => '',
            'view' => 'horizontal',
            'show_completed' => 'true',
            'show_dates' => 'true',
            'show_details' => 'true',
            'compact' => 'false'
        ), $atts, 'wecoza_class_timeline');

        if (empty($atts['class_id'])) {
            return '<div class="wecoza-error">Error: class_id is required for timeline.</div>';
        }

        $container_id = 'wecoza-class-timeline-' . uniqid();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>"
             class="wecoza-shortcode-container wecoza-class-timeline-container <?php echo esc_attr($atts['view'] . ' ' . ($atts['compact'] === 'true' ? 'compact' : '')); ?>"
             data-wecoza-shortcode="class_timeline"
             data-wecoza-params="<?php echo esc_attr(json_encode($atts)); ?>">

            <div class="wecoza-timeline-header">
                <h4>📈 Class Timeline</h4>
                <div class="wecoza-timeline-info">
                    <span class="wecoza-class-name"><?php echo esc_html($this->get_class_name($atts['class_id'])); ?></span>
                </div>
            </div>

            <div class="wecoza-timeline-content">
                <?php echo $this->get_class_timeline_content($atts); ?>
            </div>

            <div class="wecoza-timeline-legend">
                <div class="wecoza-legend-item completed">
                    <span class="wecoza-legend-marker"></span>
                    <span>Completed</span>
                </div>
                <div class="wecoza-legend-item current">
                    <span class="wecoza-legend-marker"></span>
                    <span>Current</span>
                </div>
                <div class="wecoza-legend-item pending">
                    <span class="wecoza-legend-marker"></span>
                    <span>Pending</span>
                </div>
                <div class="wecoza-legend-item overdue">
                    <span class="wecoza-legend-marker"></span>
                    <span>Overdue</span>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get class timeline content
     */
    private function get_class_timeline_content($atts)
    {
        $timeline_items = $this->get_timeline_items($atts['class_id']);

        ob_start();
        ?>
        <div class="wecoza-timeline-track">
            <?php foreach ($timeline_items as $index => $item): ?>
                <?php
                $status_class = $this->get_timeline_item_status($item);
                $is_current = $item['status'] === 'current';
                ?>
                <div class="wecoza-timeline-item <?php echo esc_attr($status_class . ($is_current ? ' current-item' : '')); ?>"
                     data-step="<?php echo esc_attr($item['step']); ?>"
                     data-status="<?php echo esc_attr($item['status']); ?>">

                    <div class="wecoza-timeline-marker">
                        <div class="wecoza-timeline-dot">
                            <?php if ($item['status'] === 'completed'): ?>
                                ✓
                            <?php elseif ($item['status'] === 'overdue'): ?>
                                ⚠
                            <?php else: ?>
                                <?php echo $item['step']; ?>
                            <?php endif; ?>
                        </div>
                        <div class="wecoza-timeline-icon">
                            <?php echo $this->get_task_icon($item['task_type']); ?>
                        </div>
                    </div>

                    <div class="wecoza-timeline-content">
                        <div class="wecoza-timeline-title">
                            <h6><?php echo esc_html($item['title']); ?></h6>
                            <span class="wecoza-timeline-status-badge <?php echo esc_attr($item['status']); ?>">
                                <?php echo ucfirst($item['status']); ?>
                            </span>
                        </div>

                        <?php if ($atts['show_details'] === 'true'): ?>
                            <div class="wecoza-timeline-details">
                                <p><?php echo esc_html($item['description']); ?></p>

                                <?php if ($atts['show_dates'] === 'true'): ?>
                                    <div class="wecoza-timeline-dates">
                                        <?php if (!empty($item['start_date'])): ?>
                                            <span class="wecoza-start-date">
                                                Started: <?php echo date('M j, Y', strtotime($item['start_date'])); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($item['due_date'])): ?>
                                            <span class="wecoza-due-date <?php echo $this->is_task_overdue($item) ? 'overdue' : ''; ?>">
                                                Due: <?php echo date('M j, Y', strtotime($item['due_date'])); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($item['completed_date'])): ?>
                                            <span class="wecoza-completed-date">
                                                Completed: <?php echo date('M j, Y', strtotime($item['completed_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($item['status'] === 'current' || $item['status'] === 'overdue'): ?>
                            <div class="wecoza-timeline-actions">
                                <button class="btn btn-sm btn-primary wecoza-complete-task"
                                        data-class-id="<?php echo esc_attr($atts['class_id']); ?>"
                                        data-task="<?php echo esc_attr($item['task_type']); ?>">
                                    Complete Task
                                </button>
                                <a href="<?php echo esc_url($this->get_task_url($item)); ?>"
                                   class="btn btn-sm btn-outline-secondary">
                                    View Details
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($index < count($timeline_items) - 1): ?>
                        <div class="wecoza-timeline-connector"></div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

        <div class="wecoza-timeline-progress">
            <div class="wecoza-progress-header">
                <span>Overall Progress</span>
                <span><?php echo $this->calculate_timeline_progress($timeline_items); ?>%</span>
            </div>
            <div class="wecoza-progress-bar">
                <div class="wecoza-progress-fill"
                     style="width: <?php echo $this->calculate_timeline_progress($timeline_items); ?>%"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get timeline items for a class
     */
    private function get_timeline_items($class_id)
    {
        // Mock timeline data - would integrate with dashboard_status table
        return array(
            array(
                'step' => 1,
                'task_type' => 'class_created',
                'title' => 'Class Created',
                'description' => 'Training class has been created and registered',
                'status' => 'completed',
                'start_date' => '2025-01-10',
                'due_date' => '2025-01-10',
                'completed_date' => '2025-01-10'
            ),
            array(
                'step' => 2,
                'task_type' => 'load_learners',
                'title' => 'Load Learners',
                'description' => 'Upload and validate learner roster',
                'status' => 'completed',
                'start_date' => '2025-01-11',
                'due_date' => '2025-01-15',
                'completed_date' => '2025-01-14'
            ),
            array(
                'step' => 3,
                'task_type' => 'agent_order',
                'title' => 'Order Training Agent',
                'description' => 'Submit training agent order for instructor assignment',
                'status' => 'current',
                'start_date' => '2025-01-15',
                'due_date' => '2025-01-20',
                'completed_date' => null
            ),
            array(
                'step' => 4,
                'task_type' => 'training_schedule',
                'title' => 'Set Training Schedule',
                'description' => 'Configure training dates and times',
                'status' => 'pending',
                'start_date' => null,
                'due_date' => '2025-01-25',
                'completed_date' => null
            ),
            array(
                'step' => 5,
                'task_type' => 'material_delivery',
                'title' => 'Arrange Material Delivery',
                'description' => 'Setup delivery of training materials to venue',
                'status' => 'pending',
                'start_date' => null,
                'due_date' => '2025-02-01',
                'completed_date' => null
            ),
            array(
                'step' => 6,
                'task_type' => 'agent_paperwork',
                'title' => 'Complete Agent Paperwork',
                'description' => 'Finalize all agent documentation and contracts',
                'status' => 'pending',
                'start_date' => null,
                'due_date' => '2025-02-05',
                'completed_date' => null
            )
        );
    }

    /**
     * Get timeline item status class
     */
    private function get_timeline_item_status($item)
    {
        if ($item['status'] === 'completed') {
            return 'completed';
        } elseif ($item['status'] === 'current') {
            // Check if overdue
            if (!empty($item['due_date']) && strtotime($item['due_date']) < time()) {
                return 'overdue';
            }
            return 'current';
        } else {
            return 'pending';
        }
    }

    /**
     * Calculate overall timeline progress
     */
    private function calculate_timeline_progress($timeline_items)
    {
        $total_items = count($timeline_items);
        $completed_items = 0;

        foreach ($timeline_items as $item) {
            if ($item['status'] === 'completed') {
                $completed_items++;
            }
        }

        return $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
    }
}
