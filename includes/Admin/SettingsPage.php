<?php
declare(strict_types=1);

namespace WeCozaEvents\Admin;

use function add_action;
use function add_menu_page;
use function add_submenu_page;
use function remove_submenu_page;
use function add_settings_field;
use function add_settings_section;
use function current_user_can;
use function do_settings_sections;
use function esc_attr;
use function esc_html__;
use function get_option;
use function is_admin;
use function register_setting;
use function sanitize_email;
use function settings_fields;
use function submit_button;

final class SettingsPage
{
    private const OPTION_GROUP = 'wecoza_events_notifications';
    private const PAGE_SLUG = 'wecoza-events-notifications';
    private const SECTION_ID = 'wecoza_events_notifications_section';
    private const OPTION_INSERT = 'wecoza_notify_insert_email';
    private const OPTION_UPDATE = 'wecoza_notify_update_email';

    public static function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_menu', [self::class, 'registerMenu']);
    }

    public static function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, self::OPTION_INSERT, [
            'sanitize_callback' => [self::class, 'sanitizeEmail'],
        ]);

        register_setting(self::OPTION_GROUP, self::OPTION_UPDATE, [
            'sanitize_callback' => [self::class, 'sanitizeEmail'],
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
    }

    public static function registerMenu(): void
    {
        add_menu_page(
            esc_html__('WeCoza Events Notifications', 'wecoza-events'),
            esc_html__('Notifications', 'wecoza-events'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage'],
            'dashicons-email'
        );

        add_submenu_page(
            self::PAGE_SLUG,
            esc_html__('WeCoza Events Notifications', 'wecoza-events'),
            esc_html__('Notifications', 'wecoza-events'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );

        remove_submenu_page(self::PAGE_SLUG, self::PAGE_SLUG);
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

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WeCoza Event Notifications', 'wecoza-events'); ?></h1>
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

    private static function renderEmailField(string $optionName, string $description): void
    {
        $value = (string) get_option($optionName, '');
        ?>
        <input type="email" name="<?php echo esc_attr($optionName); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php
    }
}
