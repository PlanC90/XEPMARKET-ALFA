<?php
/**
 * TGM Plugin Activation configuration
 * 
 * All plugins are bundled as .zip files inside /inc/plugins/
 * TGMPA will install them directly from theme package — no external downloads needed.
 * 
 * NOTE: 'default_path' in config already points to inc/plugins/,
 * so 'source' only needs the filename (not the full path).
 */

require_once get_template_directory() . '/inc/class-tgm-plugin-activation.php';

add_action('tgmpa_register', 'xepmarket2_register_required_plugins');

/**
 * Bypass WordPress zip validation for bundled plugins
 * This fixes the "files are not packaged in a folder" error
 */
add_filter('upgrader_source_selection', 'xepmarket_fix_plugin_zip_structure', 1, 4);
function xepmarket_fix_plugin_zip_structure($source, $remote_source, $upgrader, $hook_extra = null) {
    global $wp_filesystem;
    
    // Only apply to TGM plugin installations
    if (!isset($_GET['tgmpa-install']) && !isset($_GET['tgmpa-update'])) {
        return $source;
    }
    
    // Check if source is valid
    if (is_wp_error($source)) {
        return $source;
    }
    
    // Get the plugin slug from the source path
    $plugin_slug = basename($source);
    
    // If the source already has the correct structure, return it
    if ($plugin_slug === 'omnixep-woocommerce' || $plugin_slug === 'omnixep-affiliate' || $plugin_slug === 'xepmarket-telegram-bot') {
        return $source;
    }
    
    // Check if there's a single directory in the source
    $source_files = array_keys($wp_filesystem->dirlist($remote_source));
    if (count($source_files) === 1 && $wp_filesystem->is_dir(trailingslashit($remote_source) . $source_files[0])) {
        // Single directory found, use it as source
        $new_source = trailingslashit($remote_source) . trailingslashit($source_files[0]);
        return $new_source;
    }
    
    return $source;
}

function xepmarket2_register_required_plugins()
{
    $plugin_path = get_template_directory() . '/inc/plugins/';

    $plugins = array(
        // ── Core Plugins (Required) ──
        array(
            'name' => 'WooCommerce',
            'slug' => 'woocommerce',
            'required' => true,
        ),
        array(
            'name' => 'OmniXEP WooCommerce Payment Gateway',
            'slug' => 'omnixep-woocommerce',
            'source' => 'omnixep-woocommerce.zip',
            'required' => true,
            'version' => '1.8.8',
            'force_activation' => false,
            'force_deactivation' => false,
        ),
        array(
            'name' => 'OmniXEP Affiliate',
            'slug' => 'omnixep-affiliate',
            'source' => 'omnixep-affiliate.zip',
            'required' => false,
        ),
        array(
            'name' => 'XEP Market Telegram Bot',
            'slug' => 'xepmarket-telegram-bot',
            'source' => 'xepmarket-telegram-bot.zip',
            'required' => false,
            'version' => '1.0.0',
        ),

        // ── Bundled Modules (Recommended) ──
        array(
            'name' => 'ALD – AliExpress Dropshipping',
            'slug' => 'woo-alidropship',
            'source' => 'woo-alidropship.zip',
            'required' => false,
        ),
        array(
            'name' => 'Product Variations Swatches',
            'slug' => 'product-variations-swatches-for-woocommerce',
            'source' => 'product-variations-swatches-for-woocommerce.zip',
            'required' => false,
        ),
        array(
            'name' => 'Additional Variation Gallery',
            'slug' => 'vargal-additional-variation-gallery-for-woo',
            'source' => 'vargal-additional-variation-gallery-for-woo.zip',
            'required' => false,
        ),
        array(
            'name' => 'Orders Tracking',
            'slug' => 'woo-orders-tracking',
            'source' => 'woo-orders-tracking.zip',
            'required' => false,
        ),


        array(
            'name' => 'WP Mail SMTP',
            'slug' => 'wp-mail-smtp',
            'source' => 'wp-mail-smtp.zip',
            'required' => false,
        ),
    );

    $config = array(
        'id' => 'xepmarket2',
        'default_path' => $plugin_path,
        'menu' => 'tgmpa-install-plugins',
        'has_notices' => true,
        'dismissable' => true,
        'dismiss_msg' => '',
        'is_automatic' => true,   // Automatically activate after install
        'message' => '',
    );

    tgmpa($plugins, $config);
}
