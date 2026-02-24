<?php
/**
 * Override WordPress Plugin Upgrader for Bundled Plugins
 * 
 * This file handles specialized unzipping logic for TGM bundled plugins.
 * Fixed in 1.5.1: Removed infinite loop in unzip_file filter.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load dependencies and define the class ONLY when in TGM context to avoid Fatal Errors
 */
if (is_admin() && (isset($_GET['tgmpa-install']) || isset($_GET['tgmpa-update']))) {

    // Make sure the original class is loaded first
    if (!class_exists('Plugin_Upgrader')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    if (class_exists('Plugin_Upgrader') && !class_exists('XEPMARKET_Plugin_Upgrader')) {
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
                return $source;
            }
        }
    }

    /**
     * Override the global upgrader class
     */
    add_filter('upgrader_package_options', function ($options) {
        if (class_exists('XEPMARKET_Plugin_Upgrader')) {
            $options['upgrader_class'] = 'XEPMARKET_Plugin_Upgrader';
        }
        return $options;
    });
}

/**
 * Custom zip structure handling for TGM
 * IMPORTANT: Return null to allow WordPress to use its default unzip method.
 */
add_filter('unzip_file', 'xepmarket_custom_unzip', 10, 3); // Note: 3 args if we wanted $result
function xepmarket_custom_unzip($result, $file, $to)
{
    // Only apply during TGM installations
    if (!is_admin() || (!isset($_GET['tgmpa-install']) && !isset($_GET['tgmpa-update']))) {
        return null; // Return null to let WP handle it normally (Avoids infinite loop)
    }

    global $wp_filesystem;

    if (!$wp_filesystem) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    // Use PHP's ZipArchive directly to bypass WordPress validation for specific TGM cases
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $open_result = $zip->open($file);

        if ($open_result === TRUE) {
            // Extract all files
            $zip->extractTo($to);
            $zip->close();
            return true;
        }
    }

    // Fallback to WordPress's default method
    return null;
}
