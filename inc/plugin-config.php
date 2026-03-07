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
 * Modül listesinde "Güncelleme mevcut" için beklenen sürümü döndürür.
 * OmniXEP Gateway ve Telegram Bot için tema sürümü kullanılır (senkron).
 */
function xepmarket2_get_plugin_expected_version($slug)
{
    $theme_version = wp_get_theme(get_template())->get('Version');
    // Allow activating plugins independently from theme version.
    // If you want to enforce lockstep versions, add slugs via this filter.
    $use_theme_version = apply_filters('xepmarket2_use_theme_version_for_plugins', array());
    if (in_array($slug, $use_theme_version, true) && $theme_version) {
        return $theme_version;
    }
    return null;
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
            'source' => xepmarket2_get_github_plugin_zip_url('woocommerce') ?: null,
        ),
        array(
            'name' => 'OmniXEP WooCommerce Payment Gateway',
            'slug' => 'omnixep-woocommerce',
            'source' => xepmarket2_get_github_plugin_zip_url('omnixep-woocommerce') ?: $plugin_path . 'omnixep-woocommerce.zip',
            'required' => true,
            'version' => xepmarket2_get_plugin_expected_version('omnixep-woocommerce'),
            'force_activation' => false,
            'force_deactivation' => false,
        ),
        // OmniXEP Affiliate: not in TGMPA – built into theme (Settings → Affiliate tab). No plugin needed.

        // ── Bundled Modules (Recommended) ──
        array(
            'name' => 'ALD – AliExpress Dropshipping',
            'slug' => 'woo-alidropship',
            'source' => xepmarket2_get_github_plugin_zip_url('woo-alidropship') ?: 'woo-alidropship.zip',
            'required' => false,
        ),
        array(
            'name' => 'Product Variations Swatches',
            'slug' => 'product-variations-swatches-for-woocommerce',
            'source' => xepmarket2_get_github_plugin_zip_url('product-variations-swatches-for-woocommerce') ?: 'product-variations-swatches-for-woocommerce.zip',
            'required' => false,
        ),
        array(
            'name' => 'Additional Variation Gallery',
            'slug' => 'vargal-additional-variation-gallery-for-woo',
            'source' => xepmarket2_get_github_plugin_zip_url('vargal-additional-variation-gallery-for-woo') ?: 'vargal-additional-variation-gallery-for-woo.zip',
            'required' => false,
        ),
        array(
            'name' => 'Orders Tracking',
            'slug' => 'woo-orders-tracking',
            'source' => xepmarket2_get_github_plugin_zip_url('woo-orders-tracking') ?: 'woo-orders-tracking.zip',
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
