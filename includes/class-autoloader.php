<?php
/**
 * Autoloader for WECOZA Notifications Core plugin
 */

namespace WecozaNotifications;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader class
 */
class Autoloader
{
    /**
     * Register autoloader
     */
    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Autoload classes
     */
    public static function autoload($class)
    {
        // Check if this is a WecozaNotifications class
        if (strpos($class, 'WecozaNotifications\\') !== 0) {
            return;
        }

        // Remove namespace prefix
        $class = str_replace('WecozaNotifications\\', '', $class);

        // Convert class name to file path
        $file = self::get_file_path($class);

        if ($file && file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Get file path for class
     */
    private static function get_file_path($class)
    {
        $base_dir = WECOZA_NOTIFICATIONS_PLUGIN_DIR;

        // Map class names to file paths
        $class_map = array(
            // Core classes
            'Core' => 'includes/class-wecoza-notifications-core.php',
            'Activator' => 'includes/class-activator.php',
            'Deactivator' => 'includes/class-deactivator.php',
            'Uninstaller' => 'includes/class-uninstaller.php',

            // Services
            'EventProcessor' => 'app/Services/EventProcessor.php',
            'EmailService' => 'app/Services/EmailService.php',
            'TemplateService' => 'app/Services/TemplateService.php',
            'DatabaseService' => 'app/Services/DatabaseService.php',
            'CronService' => 'app/Services/CronService.php',

            // Controllers
            'NotificationController' => 'app/Controllers/NotificationController.php',
            'SupervisorController' => 'app/Controllers/SupervisorController.php',
            'ShortcodeController' => 'app/Controllers/ShortcodeController.php',

            // Models
            'SupervisorModel' => 'app/Models/SupervisorModel.php',
            'EventModel' => 'app/Models/EventModel.php',
            'NotificationModel' => 'app/Models/NotificationModel.php',
        );

        if (isset($class_map[$class])) {
            return $base_dir . $class_map[$class];
        }

        // Fallback: convert class name to file path automatically
        $file_name = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

        // Check in includes directory first
        $includes_file = $base_dir . 'includes/' . $file_name;
        if (file_exists($includes_file)) {
            return $includes_file;
        }

        // Check in app directories
        $directories = array('Controllers', 'Services', 'Models');
        foreach ($directories as $dir) {
            $app_file = $base_dir . 'app/' . $dir . '/' . $class . '.php';
            if (file_exists($app_file)) {
                return $app_file;
            }
        }

        return false;
    }
}

// Register the autoloader
Autoloader::register();