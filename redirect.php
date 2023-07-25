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

    // Form handling
    if (isset($_POST['old_url']) && isset($_POST['new_url'])) {
        // Use wp_make_link_relative to get a relative URL
        $old_path = wp_make_link_relative($_POST['old_url']);
        $new_path = wp_make_link_relative($_POST['new_url']);

        // Add the redirect to the .htaccess file
        insert_with_markers(get_home_path() . '.htaccess', '# 301 Redirects Manager',
            ["Redirect 301 " . $old_path . " " . $new_path]);

        // Save the redirect to the database
        $wpdb->insert($wpdb->prefix . 'redirect_manager',
            ['redirect' => "Redirect 301 " . $old_path . " " . $new_path]);
    }

    // Removal handling
    if (isset($_POST['remove_redirect'])) {
        // Remove from the database
        $redirect = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "redirect_manager WHERE id = %d", $_POST['remove_redirect']));
        $wpdb->delete($wpdb->prefix . 'redirect_manager', ['id' => $_POST['remove_redirect']]);

        // Remove from the .htaccess file
        $htaccess = file(get_home_path() . '.htaccess');
        $lines = array_filter($htaccess, function($line) use ($redirect) {
            return trim($line) !== $redirect->redirect;
        });
        insert_with_markers(get_home_path() . '.htaccess', '# 301 Redirects Manager', $lines);
    }

    // Display
    $redirects = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "redirect_manager");
    ?>
    <div class="wrap">
        <h1>301 Redirects Manager</h1>
        <form method="post">
            <label for="old_url">Old URL:</label><br>
            <input type="text" id="old_url" name="old_url" required><br>
            <label for="new_url">New URL:</label><br>
            <input type="text" id="new_url" name="new_url" required><br>
            <input type="submit" value="Add Redirect">
        </form>
        <h2>Stored Redirects:</h2>
        <?php foreach ($redirects as $redirect) : ?>
            <p>
                <?php 
                    $parts = explode(' ', $redirect->redirect);
                    $old_path = trim($parts[2]);
                    $old_url = get_home_url(null, $old_path);
                ?>
                <a href="<?php echo esc_url($old_url); ?>" target="_blank">
                    <?php echo esc_html($redirect->redirect); ?>
                </a>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="remove_redirect" value="<?php echo $redirect->id; ?>">
                    <input type="submit" value="Remove">
                </form>
            </p>
        <?php endforeach; ?>
    </div>
    <?php
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
