<?php
/**
 * Override WordPress Plugin Upgrader for Bundled Plugins
 * 
 * This completely replaces WordPress's zip validation logic
 * for TGM bundled plugin installations.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Plugin Upgrader that bypasses zip validation
 */
class XEPMARKET_Plugin_Upgrader extends Plugin_Upgrader
{

    /**
     * Override the install method to bypass zip validation
     */
    public function install($package, $args = array())
    {
        // Only override for TGM installations
        if (!isset($_GET['tgmpa-install']) && !isset($_GET['tgmpa-update'])) {
            return parent::install($package, $args);
        }

        $defaults = array(
            'clear_update_cache' => true,
        );
        $parsed_args = wp_parse_args($args, $defaults);

        $this->init();
        $this->install_strings();

        add_filter('upgrader_source_selection', array($this, 'bypass_check_package'), 10, 2);

        // Skip the normal validation and go straight to extraction
        $res = $this->run(array(
            'package' => $package,
            'destination' => WP_PLUGIN_DIR,
            'clear_destination' => false,
            'clear_working' => true,
            'hook_extra' => array(
                'type' => 'plugin',
                'action' => 'install',
            ),
        ));

        remove_filter('upgrader_source_selection', array($this, 'bypass_check_package'));

        if (!$res || is_wp_error($res)) {
            return $res;
        }

        // Flush plugins cache so WP recognizes the new plugin.
        wp_clean_plugins_cache($parsed_args['clear_update_cache']);

        return true;
    }

    /**
     * Custom source selection that's more lenient for bundled plugins
     */
    public function bypass_check_package($source, $remote_source)
    {
        global $wp_filesystem;

        // Always allow the source as-is for TGM installations
        if (isset($_GET['tgmpa-install']) || isset($_GET['tgmpa-update'])) {
            return $source;
        }

        return $source;
    }
}

/**
 * Replace WordPress's Plugin_Upgrader with our custom one during TGM installations
 */
add_action('init', 'xepmarket_override_plugin_upgrader', 1);
function xepmarket_override_plugin_upgrader()
{
    // Only override during TGM installations
    if (!isset($_GET['tgmpa-install']) && !isset($_GET['tgmpa-update'])) {
        return;
    }

    // Make sure the original class is loaded first
    if (!class_exists('Plugin_Upgrader')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    // Override the global upgrader class
    add_filter('upgrader_package_options', function ($options) {
        $options['upgrader_class'] = 'XEPMARKET_Plugin_Upgrader';
        return $options;
    });
}

/**
 * Completely disable WordPress's zip structure validation
 */
add_filter('unzip_file', 'xepmarket_custom_unzip', 10, 2);
function xepmarket_custom_unzip($file, $to)
{
    // Only apply during TGM installations
    if (!isset($_GET['tgmpa-install']) && !isset($_GET['tgmpa-update'])) {
        return unzip_file($file, $to);
    }

    global $wp_filesystem;

    if (!$wp_filesystem) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    // Use PHP's ZipArchive directly to bypass WordPress validation
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $result = $zip->open($file);

        if ($result === TRUE) {
            // Extract all files
            $zip->extractTo($to);
            $zip->close();
            return true;
        }
    }

    // Fallback to WordPress's method
    return unzip_file($file, $to);
}