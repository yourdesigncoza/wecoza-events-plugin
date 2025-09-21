<?php

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_create_audit_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Analytics table remains in WordPress for legacy reporting
    $analytics_table = $wpdb->prefix . 'wecoza_analytics';
    $analytics_sql = "CREATE TABLE $analytics_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        metric_type varchar(50) NOT NULL,
        metric_key varchar(100) NOT NULL,
        metric_value longtext NOT NULL,
        date date NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_metric (metric_type, metric_key, date),
        KEY metric_type (metric_type),
        KEY date (date),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($analytics_sql);

    update_option('wecoza_audit_db_version', '1.0');
}

add_action('wecoza_plugin_activate', 'wecoza_create_audit_tables');
