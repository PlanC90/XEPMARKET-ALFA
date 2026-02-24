<?php
/**
 * Auto-Copy Bundled Plugins
 * 
 * Instead of using zip files, this automatically copies plugin folders
 * from theme/inc/plugins/ to wp-content/plugins/ when install is clicked.
 * 
 * This is much simpler and avoids all WordPress zip validation issues.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Intercept TGM install action and copy plugin folder instead
 */
add_action('admin_init', 'xepmarket_auto_copy_bundled_plugins', 1);
function xepmarket_auto_copy_bundled_plugins() {
    // Only run on TGM install/update actions
    if (!isset($_GET['tgmpa-install']) && !isset($_GET['tgmpa-update'])) {
        return;
    }
    
    // Get plugin slug from URL
    if (!isset($_GET['plugin'])) {
        return;
    }
    
    $plugin_slug = sanitize_key($_GET['plugin']);
    
    // Define bundled plugins that should be copied instead of installed from zip
    $bundled_plugins = array(
        'omnixep-woocommerce',
        'omnixep-affiliate',
        'xepmarket-telegram-bot',
        'woo-alidropship',
        'product-variations-swatches-for-woocommerce',
        'vargal-additional-variation-gallery-for-woo',
        'woo-orders-tracking',
        'wp-mail-smtp',
    );
    
    // Only handle our bundled plugins
    if (!in_array($plugin_slug, $bundled_plugins)) {
        return;
    }
    
    // Define paths
    $theme_plugin_dir = get_template_directory() . '/inc/plugins/' . $plugin_slug;
    $wp_plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
    $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
    
    // Check if theme plugin folder exists
    if (!is_dir($theme_plugin_dir)) {
        return; // Let TGM handle it normally (will try zip)
    }
    
    // Copy plugin folder
    $copied = xepmarket_copy_plugin_directory($theme_plugin_dir, $wp_plugin_dir);
    
    if ($copied) {
        // Clear plugin cache
        wp_clean_plugins_cache(true);
        
        // Activate plugin if auto-activation is enabled
        if (!is_plugin_active($plugin_file)) {
            $result = activate_plugin($plugin_file, '', false, true);
            if (!is_wp_error($result)) {
                // Success! Redirect back to TGM page
                wp_redirect(admin_url('themes.php?page=tgmpa-install-plugins&plugin_status=all'));
                exit;
            }
        } else {
            // Already active, just redirect
            wp_redirect(admin_url('themes.php?page=tgmpa-install-plugins&plugin_status=all'));
            exit;
        }
    }
}

/**
 * Recursively copy plugin directory
 */
function xepmarket_copy_plugin_directory($src, $dst) {
    // Remove destination if exists
    if (is_dir($dst)) {
        xepmarket_delete_directory($dst);
    }
    
    // Create destination directory
    if (!wp_mkdir_p($dst)) {
        return false;
    }
    
    // Copy all files and subdirectories
    $dir = opendir($src);
    if (!$dir) {
        return false;
    }
    
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $src_path = $src . '/' . $file;
        $dst_path = $dst . '/' . $file;
        
        if (is_dir($src_path)) {
            xepmarket_copy_plugin_directory($src_path, $dst_path);
        } else {
            copy($src_path, $dst_path);
        }
    }
    
    closedir($dir);
    return true;
}

/**
 * Recursively delete directory
 */
function xepmarket_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? xepmarket_delete_directory($path) : unlink($path);
    }
    
    return rmdir($dir);
}

/**
 * Show success message after auto-copy
 */
add_action('admin_notices', 'xepmarket_show_plugin_copy_notice');
function xepmarket_show_plugin_copy_notice() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'tgmpa-install-plugins') {
        return;
    }
    
    // Check if we just copied a plugin
    if (isset($_GET['plugin']) && isset($_GET['tgmpa-install'])) {
        $plugin_slug = sanitize_key($_GET['plugin']);
        $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
        
        if (is_plugin_active($plugin_file)) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Plugin installed and activated successfully from theme bundle!</strong></p>';
            echo '</div>';
        }
    }
}
