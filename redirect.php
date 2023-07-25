<?php
/**
 * Plugin Name: WP 301 Redirect Manager
 * Description: WordPress plugin to manage 301 redirects and update .htaccess file.
 * Version: 1.0
 * Author: D.Kandekore
 */

function wp301_admin_menu() {
    add_submenu_page(
        'options-general.php',
        '301 Redirects',
        '301 Redirects',
        'manage_options',
        'wp301-redirects',
        'wp301_redirects_page'
    );
}
add_action('admin_menu', 'wp301_admin_menu');

function wp301_redirects_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . '301redirects';

    if (isset($_POST['old_url']) && isset($_POST['new_url'])) {
        $old_url = sanitize_text_field($_POST['old_url']);
        $new_url = sanitize_text_field($_POST['new_url']);

        // Ensure the URL is valid
        if (!filter_var($old_url, FILTER_VALIDATE_URL) || !filter_var($new_url, FILTER_VALIDATE_URL)) {
            echo '<p>Invalid URL provided.</p>';
            return;
        }

        // Standardize URLs to relative format
        $old_path = wp_parse_url($old_url, PHP_URL_PATH);
        $new_path = wp_parse_url($new_url, PHP_URL_PATH);

        // Check for cyclic redirects
        $existing_redirect = $wpdb->get_row("SELECT * FROM $table_name WHERE redirect LIKE '%$old_path%'");
        if ($existing_redirect && strpos($existing_redirect->redirect, $new_path) !== false) {
            echo '<p>Cyclic redirect detected. Please enter a different URL.</p>';
            return;
        }

        $redirect = "Redirect 301 $old_path $new_path\n";

        // Ensure the redirect does not already exist
        $existing_redirects = $wpdb->get_results("SELECT * FROM $table_name WHERE redirect = '$redirect'");
        if (!empty($existing_redirects)) {
            echo '<p>Redirect already exists.</p>';
            return;
        }

        // Attempt to update .htaccess file
        $htaccess_file = ABSPATH . '.htaccess';
        if (!is_writable($htaccess_file)) {
            echo '<p>.htaccess file is not writable. Please change the file permissions to allow the plugin to write to it.</p>';
            return;
        }

        // Check number of existing redirects and limit for performance
        $redirect_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($redirect_count >= 100) {  // adjust limit as needed
            echo '<p>The maximum number of redirects has been reached. Please delete some before adding more.</p>';
            return;
        }

        // If all checks pass, add the redirect
        $wpdb->insert($table_name, array('redirect' => $redirect));
        insert_with_markers($htaccess_file, 'WP 301 Redirects', array($redirect));
    }

    $redirects = $wpdb->get_results("SELECT * FROM $table_name");
    ?>

    <h1>301 Redirect Generator</h1>
    <form method="post">
        <label for="old_url">Old URL:</label><br>
        <input type="text" id="old_url" name="old_url"><br>
        <label for="new_url">New URL:</label><br>
        <input type="text" id="new_url" name="new_url"><br>
        <input type="submit" value="Generate">
    </form>
    <h2>Stored Redirects:</h2>
    <?php foreach ($redirects as $redirect) : ?>
        <p><?php echo esc_html($redirect->redirect); ?></p>
    <?php endforeach; ?>

    <?php
}

function wp301_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . '301redirects';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            redirect text NOT NULL,
            PRIMARY KEY  (id)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'wp301_install');
