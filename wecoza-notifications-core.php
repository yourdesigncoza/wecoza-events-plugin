<?php
/**
 * Plugin Name: WECOZA Notifications Core
 * Plugin URI: https://yourdesign.co.za
 * Description: Centralized notification system for WECOZA that handles reminders and confirmations across all modules via event-driven architecture.
 * Version: 1.0.0
 * Author: John @ YourDesign.co.za
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wecoza-notifications
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WECOZA_NOTIFICATIONS_VERSION', '1.0.0');
define('WECOZA_NOTIFICATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WECOZA_NOTIFICATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WECOZA_NOTIFICATIONS_PLUGIN_FILE', __FILE__);
define('WECOZA_NOTIFICATIONS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require the autoloader
require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-autoloader.php';

/**
 * Main plugin class
 */
final class WecozaNotificationsCore
{
    /**
     * The single instance of the class
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Define additional constants if needed
     */
    private function define_constants()
    {
        // Additional constants can be defined here
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('WecozaNotificationsCore', 'uninstall'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-activator.php';
        WecozaNotifications\Activator::activate();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-deactivator.php';
        WecozaNotifications\Deactivator::deactivate();
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall()
    {
        require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-uninstaller.php';
        WecozaNotifications\Uninstaller::uninstall();
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Initialize the main plugin class
        require_once WECOZA_NOTIFICATIONS_PLUGIN_DIR . 'includes/class-wecoza-notifications-core.php';
        new WecozaNotifications\Core();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'wecoza-notifications',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
}

/**
 * Initialize the plugin
 */
function wecoza_notifications_core()
{
    return WecozaNotificationsCore::instance();
}

// Initialize the plugin
wecoza_notifications_core();