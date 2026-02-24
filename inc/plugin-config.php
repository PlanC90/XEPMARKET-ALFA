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
            'version' => '1.8.5',
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
