<?php
declare(strict_types=1);

namespace WeCozaEvents\Admin;

use WeCozaEvents\Support\OpenAIConfig;

use function add_action;
use function add_settings_error;
use function add_settings_field;
use function add_settings_section;
use function admin_url;
use function checked;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_option;
use function is_admin;
use function is_wp_error;
use function register_setting;
use function sanitize_email;
use function settings_errors;
use function settings_fields;
use function sprintf;
use function submit_button;
use function wp_safe_redirect;
use function wp_get_referer;
use function wp_unslash;
use function wp_verify_nonce;
use function wp_remote_get;
use function wp_remote_retrieve_response_code;

final class SettingsPage
{
    private const OPTION_GROUP = 'wecoza_events_notifications';
    private const PAGE_SLUG = 'wecoza-events-notifications';
    private const SECTION_ID = 'wecoza_events_notifications_section';
    private const OPTION_INSERT = 'wecoza_notification_class_created';
    private const OPTION_UPDATE = 'wecoza_notification_class_updated';
    private const OPTION_MATERIAL = 'wecoza_notification_material_delivery';
    private const SECTION_AI = 'wecoza_events_ai_summaries_section';
    private const OPTION_AI_ENABLED = OpenAIConfig::OPTION_ENABLED;
    private const OPTION_AI_API_KEY = OpenAIConfig::OPTION_API_KEY;
    private const NONCE_ACTION_TEST = 'wecoza_ai_summary_test';
    private const NONCE_NAME = 'wecoza_ai_summary_nonce';

    public static function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_post_wecoza_ai_summary_test', [self::class, 'handleTestConnection']);
    }

    public static function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, self::OPTION_INSERT, [
            'sanitize_callback' => [self::class, 'sanitizeEmail'],
        ]);

        register_setting(self::OPTION_GROUP, self::OPTION_UPDATE, [
            'sanitize_callback' => [self::class, 'sanitizeEmail'],
        ]);

        register_setting(self::OPTION_GROUP, self::OPTION_MATERIAL, [
            'sanitize_callback' => [self::class, 'sanitizeEmail'],
        ]);

        register_setting(self::OPTION_GROUP, self::OPTION_AI_ENABLED, [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitizeCheckbox'],
            'default' => false,
        ]);

        register_setting(self::OPTION_GROUP, self::OPTION_AI_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeApiKey'],
            'default' => '',
            'autoload' => false,
        ]);

        add_settings_section(
            self::SECTION_ID,
            esc_html__('Notification Recipients', 'wecoza-events'),
            [self::class, 'renderSectionIntro'],
            self::PAGE_SLUG
        );

        add_settings_field(
            self::OPTION_INSERT,
            esc_html__('New Class notifications email', 'wecoza-events'),
            [self::class, 'renderInsertField'],
            self::PAGE_SLUG,
            self::SECTION_ID
        );

        add_settings_field(
            self::OPTION_UPDATE,
            esc_html__('Update Class notifications email', 'wecoza-events'),
            [self::class, 'renderUpdateField'],
            self::PAGE_SLUG,
            self::SECTION_ID
        );

        add_settings_field(
            self::OPTION_MATERIAL,
            esc_html__('Material Delivery notifications email', 'wecoza-events'),
            [self::class, 'renderMaterialField'],
            self::PAGE_SLUG,
            self::SECTION_ID
        );

        add_settings_section(
            self::SECTION_AI,
            esc_html__('AI Summaries', 'wecoza-events'),
            [self::class, 'renderAiSectionIntro'],
            self::PAGE_SLUG
        );

        add_settings_field(
            self::OPTION_AI_ENABLED,
            esc_html__('Enable AI summaries', 'wecoza-events'),
            [self::class, 'renderAiEnabledField'],
            self::PAGE_SLUG,
            self::SECTION_AI
        );

        add_settings_field(
            self::OPTION_AI_API_KEY,
            esc_html__('OpenAI API key', 'wecoza-events'),
            [self::class, 'renderAiApiKeyField'],
            self::PAGE_SLUG,
            self::SECTION_AI
        );
    }

    public static function renderSectionIntro(): void
    {
        echo '<p>' . esc_html__('Configure email recipients for automated class notifications.', 'wecoza-events') . '</p>';
    }

    public static function renderInsertField(): void
    {
        self::renderEmailField(self::OPTION_INSERT, esc_html__('Address to notify when a class is created.', 'wecoza-events'));
    }

    public static function renderUpdateField(): void
    {
        self::renderEmailField(self::OPTION_UPDATE, esc_html__('Address to notify when a class is updated.', 'wecoza-events'));
    }

    public static function renderMaterialField(): void
    {
        self::renderEmailField(self::OPTION_MATERIAL, esc_html__('Address to notify for material delivery reminders (7 days and 5 days before class start).', 'wecoza-events'));
    }

    public static function renderAiSectionIntro(): void
    {
        echo '<p>' . esc_html__('Configure the AI-generated summary workflow and credentials.', 'wecoza-events') . '</p>';
    }

    public static function renderAiEnabledField(): void
    {
        $value = (bool) get_option(self::OPTION_AI_ENABLED, false);
        ?>
        <input type="hidden" name="<?php echo esc_attr(self::OPTION_AI_ENABLED); ?>" value="0" />
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_AI_ENABLED); ?>" value="1" <?php checked($value); ?> />
            <?php echo esc_html__('Enable AI summaries for eligible notifications.', 'wecoza-events'); ?>
        </label>
        <p class="description"><?php echo esc_html__('When disabled, notification emails fall back to the legacy JSON payload.', 'wecoza-events'); ?></p>
        <?php
    }

    public static function renderAiApiKeyField(): void
    {
        $config = new OpenAIConfig();
        $masked = $config->maskApiKey($config->getApiKey());
        ?>
        <input type="password" name="<?php echo esc_attr(self::OPTION_AI_API_KEY); ?>" value="" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php echo esc_html__('Paste a valid OpenAI API key (sk-...) or leave blank to remove the stored key.', 'wecoza-events'); ?>
            <?php
            if ($masked !== null) {
                echo '<br />' . esc_html(sprintf(__('Current key: %s', 'wecoza-events'), $masked));
            }
            ?>
        </p>
        <?php
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WeCoza Event Notifications', 'wecoza-events'); ?></h1>
            <?php settings_errors(self::OPTION_GROUP); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param mixed $value
     */
    public static function sanitizeEmail($value): string
    {
        $sanitised = sanitize_email((string) $value);
        return $sanitised ?: '';
    }

    public static function sanitizeCheckbox($value): string
    {
        return (string) ((int) (!empty($value)));
    }

    public static function sanitizeApiKey($value): string
    {
        $config = new OpenAIConfig();
        return $config->sanitizeApiKey((string) $value);
    }

    public static function handleTestConnection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        $nonce = isset($_GET[self::NONCE_NAME]) ? wp_unslash((string) $_GET[self::NONCE_NAME]) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION_TEST)) {
            add_settings_error(self::OPTION_GROUP, 'wecoza_ai_summary_test_nonce', esc_html__('Nonce verification failed.', 'wecoza-events'));
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        $config = new OpenAIConfig();
        $apiKey = $config->getApiKey();

        if ($apiKey === null) {
            add_settings_error(self::OPTION_GROUP, 'wecoza_ai_summary_test_missing', esc_html__('No OpenAI API key is configured.', 'wecoza-events'));
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        $response = wp_remote_get('https://api.openai.com/v1/models?limit=1', [
            'timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        if (is_wp_error($response)) {
            add_settings_error(
                self::OPTION_GROUP,
                'wecoza_ai_summary_test_request',
                esc_html(sprintf(__('OpenAI request failed: %s', 'wecoza-events'), $response->get_error_message()))
            );
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            add_settings_error(
                self::OPTION_GROUP,
                'wecoza_ai_summary_test_status',
                esc_html(sprintf(__('OpenAI returned HTTP %d during connection test.', 'wecoza-events'), $status))
            );
            wp_safe_redirect(self::redirectUrl());
            exit;
        }

        add_settings_error(
            self::OPTION_GROUP,
            'wecoza_ai_summary_test_success',
            esc_html__('OpenAI connection succeeded.', 'wecoza-events'),
            'updated'
        );

        wp_safe_redirect(self::redirectUrl());
        exit;
    }

    private static function redirectUrl(): string
    {
        $referer = wp_get_referer();
        if (is_string($referer) && $referer !== '') {
            return $referer;
        }

        return admin_url('admin.php?page=' . self::PAGE_SLUG);
    }

    private static function renderEmailField(string $optionName, string $description): void
    {
        $value = (string) get_option($optionName, '');
        ?>
        <input type="email" name="<?php echo esc_attr($optionName); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php
    }
}
