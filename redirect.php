<?php
/**
 * Plugin Name: WP 301 Redirect Manager
 * Description: WordPress plugin to manage 301 redirects and update .htaccess file.
 * Version: 1.0
 * Author: D.Kandekore
 */

// Add a new submenu under Settings
function redirect_manager_menu() {
    add_options_page('301 Redirects Manager', 'Redirects Manager', 'manage_options', '301-redirects-manager', 'redirect_manager_page');
}
add_action('admin_menu', 'redirect_manager_menu');

function redirect_manager_page() {
    global $wpdb;

    // Check nonce for security
    check_admin_referer('301_redirect_manager_update');

    // Add handling
    if (isset($_POST['old_url']) && isset($_POST['new_url'])) {
        $old_url = esc_url_raw($_POST['old_url']);
        $new_url = esc_url_raw($_POST['new_url']);

        // Get the relative paths
        $old_path = wp_make_link_relative($old_url);
        $new_path = wp_make_link_relative($new_url);

        // Save the redirect to the database
        $wpdb->insert($wpdb->prefix . 'redirect_manager',
            ['redirect' => "Redirect 301 " . $old_path . " " . $new_path]);
    }

    // Remove handling
    if (isset($_POST['remove_redirect'])) {
        // Remove from the database
        $wpdb->delete($wpdb->prefix . 'redirect_manager', ['id' => $_POST['remove_redirect']]);
    }

    // Add all redirects to the .htaccess file
    $redirects = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "redirect_manager");
    $htaccess_lines = array_map(function($redirect) {
        return $redirect->redirect;
    }, $redirects);
    insert_with_markers(get_home_path() . '.htaccess', '# 301 Redirects Manager', $htaccess_lines);

    // Redirects list
    $redirects = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "redirect_manager");
    echo '<h2>Stored Redirects</h2>';
    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr><th>Redirects</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    foreach ($redirects as $redirect) {
        echo '<tr>';
        echo '<td>';
        echo '<a href="' . get_home_url() . substr(explode(' ', esc_html($redirect->redirect))[1], 1) . '" target="_blank">';
        echo esc_html($redirect->redirect);
        echo '</a>';
        echo '</td>';
        echo '<td>';
        echo '<form method="POST">';
        wp_nonce_field('301_redirect_manager_update');
        echo '<input type="hidden" name="remove_redirect" value="' . esc_attr($redirect->id) . '">';
        echo '<input type="submit" value="Remove">';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Form to add new redirect
    echo '<h2>Add New Redirect</h2>';
    echo '<form method="POST">';
    wp_nonce_field('301_redirect_manager_update');
    echo '<label for="old_url">Old URL:</label><br>';
    echo '<input type="text" id="old_url" name="old_url"><br>';
    echo '<label for="new_url">New URL:</label><br>';
    echo '<input type="text" id="new_url" name="new_url"><br>';
    echo '<input type="submit" value="Add Redirect">';
    echo '</form>';
}


// Create table for storing redirects
function redirect_manager_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'redirect_manager';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // table not in database. Create new table
        $sql = "CREATE TABLE `$table_name` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `redirect` text NOT NULL,
            PRIMARY KEY (`id`)
         ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'redirect_manager_install');
