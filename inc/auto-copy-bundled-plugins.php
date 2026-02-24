<?php
/**
 * Auto-Copy Bundled Plugins - Aggressive Approach
 * 
 * This intercepts TGM installation BEFORE WordPress tries to use zip files.
 * It directly copies plugin folders and prevents any zip-related errors.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Intercept TGM install/update requests at the earliest possible point
 * Priority 1 ensures this runs before TGM's own handlers
 */
add_action('load-appearance_page_tgmpa-install-plugins', 'xepmarket_intercept_tgm_install', 1);
function xepmarket_intercept_tgm_install() {
    // Check if this is an install or update action
    if (!isset($_GET['tgmpa-install']) && !isset($_GET['tgmpa-update'])) {
        return;
    }
    
    // Get plugin slug
    if (!isset($_GET['plugin'])) {
        return;
    }
    
    $plugin_slug = sanitize_key($_GET['plugin']);
    
    // List of plugins that should be copied from folders instead of zip
    $folder_plugins = array(
        'omnixep-woocommerce',
        'omnixep-affiliate',
        'xepmarket-telegram-bot',
        'woo-alidropship',
        'product-variations-swatches-for-woocommerce',
        'vargal-additional-variation-gallery-for-woo',
        'woo-orders-tracking',
        'wp-mail-smtp',
    );
    
    // Only handle folder-based plugins
    if (!in_array($plugin_slug, $folder_plugins)) {
        return;
    }
    
    // Define paths
    $theme_plugin_dir = get_template_directory() . '/inc/plugins/' . $plugin_slug;
    $wp_plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
    $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
    
    // Check if source folder exists
    if (!is_dir($theme_plugin_dir)) {
        return; // No folder, let TGM try zip
    }
    
    // If already installed and active, just redirect
    if (is_dir($wp_plugin_dir) && function_exists('is_plugin_active') && is_plugin_active($plugin_file)) {
        wp_safe_redirect(admin_url('themes.php?page=tgmpa-install-plugins'));
        exit;
    }
    
    // Perform the copy
    $success = xepmarket_copy_plugin_folder($theme_plugin_dir, $wp_plugin_dir);
    
    if ($success) {
        // Clear caches
        if (function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }
        
        // Try to activate
        if (function_exists('activate_plugin')) {
            activate_plugin($plugin_file, '', false, true);
        }
        
        // Redirect with success
        wp_safe_redirect(admin_url('themes.php?page=tgmpa-install-plugins'));
        exit;
    }
}

/**
 * Copy plugin folder recursively
 */
function xepmarket_copy_plugin_folder($src, $dst) {
    // Remove destination if it exists
    if (is_dir($dst)) {
        xepmarket_remove_directory($dst);
    }
    
    // Create destination
    if (!wp_mkdir_p($dst)) {
        return false;
    }
    
    // Open source directory
    $dir = @opendir($src);
    if (!$dir) {
        return false;
    }
    
    // Copy all files and subdirectories
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $src_path = $src . '/' . $file;
        $dst_path = $dst . '/' . $file;
        
        if (is_dir($src_path)) {
            xepmarket_copy_plugin_folder($src_path, $dst_path);
        } else {
            @copy($src_path, $dst_path);
        }
    }
    
    closedir($dir);
    return true;
}

/**
 * Remove directory recursively
 */
function xepmarket_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = @scandir($dir);
    if (!$files) {
        return false;
    }
    
    $files = array_diff($files, array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            xepmarket_remove_directory($path);
        } else {
            @unlink($path);
        }
    }
    
    return @rmdir($dir);
}
