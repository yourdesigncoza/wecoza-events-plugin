<?php
/*------------------YDCOZA-----------------------*/
/* Create Top-Level Menu for Wecoza Plugin        */
/* Adds a new top-level menu item in WP admin     */
/*-----------------------------------------------*/
function wecoza3_create_menu() {
    // Add the top-level menu
    add_menu_page(
        __('Wecoza Plugin', 'wecoza'),
        __('Wecoza', 'wecoza'),
        'manage_options',
        'wecoza-dashboard',
        'wecoza3_main_page', // Function to display the main page
        'dashicons-admin-generic',
        59
    );

    // Add Database Settings submenu
    add_submenu_page(
        'wecoza-dashboard',
        __('Database Settings', 'wecoza'),
        __('Database Settings', 'wecoza'),
        'manage_options',
        'wecoza-db-settings',
        'wecoza3_db_settings_page'
    );

    // Add Redirects submenu
    add_submenu_page(
        'wecoza-dashboard',
        __('Redirects', 'wecoza'),
        __('Redirects', 'wecoza'),
        'manage_options',
        'wecoza-redirects',
        'wecoza3_redirects_page'
    );
}
add_action('admin_menu', 'wecoza3_create_menu');