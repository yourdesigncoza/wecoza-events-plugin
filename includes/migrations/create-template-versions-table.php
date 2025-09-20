<?php

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_create_template_versions_table()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'wecoza_template_versions';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        template_id varchar(100) NOT NULL,
        version varchar(20) NOT NULL,
        subject text NOT NULL,
        body longtext NOT NULL,
        custom_css longtext,
        created_by bigint(20) unsigned,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        is_backup tinyint(1) DEFAULT 0,
        is_restore tinyint(1) DEFAULT 0,
        notes text,
        PRIMARY KEY (id),
        KEY template_id (template_id),
        KEY version (version),
        KEY created_at (created_at),
        KEY created_by (created_by)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    update_option('wecoza_template_versions_db_version', '1.0');
}

add_action('wecoza_plugin_activate', 'wecoza_create_template_versions_table');