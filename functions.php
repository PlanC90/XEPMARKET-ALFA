<?php
/**
 * XepMarket2 functions and definitions
 */

// Include TGM Plugin Activation
require_once get_template_directory() . '/inc/plugin-config.php';
require_once get_template_directory() . '/inc/demo-importer.php';
require_once get_template_directory() . '/inc/seo-config.php';
require_once get_template_directory() . '/inc/ali-sync/helper.php';
require_once get_template_directory() . '/inc/live-search.php';
require_once get_template_directory() . '/inc/theme-updater.php';

if (!defined('ABSPATH')) {
    exit;
}

// Define Theme Version: 1.3.2 for Cache Management & Portability
define('XEPMARKET_ALFA_VERSION', '1.4.3'); // Post-sync stable release
/**
 * COMPATIBILITY: Prevent Fatal Error if mail() is disabled on server
 * This prevents the site from crashing when WooCommerce or other plugins try to send emails
 * on servers that have the PHP mail() function completely removed/disabled.
 */
add_filter('pre_wp_mail', function ($return) {
    if (!function_exists('mail')) {
        return false; // Stop wp_mail from proceeding to PHPMailer and crashing
    }
    return $return;
}, 1);

/**
 * PERFORMANCE: Bulk load all theme options in one query and cache them
 * This reduces database queries from 20-30 to just 1 per page load
 */
function xepmarket2_get_all_options()
{
    static $options_cache = null;

    if ($options_cache !== null) {
        return $options_cache;
    }

    // Get all theme options in one shot from a transient cache
    $cache_key = 'xepmarket2_all_options';
    $options_cache = get_transient($cache_key);

    if ($options_cache === false) {
        global $wpdb;

        // Fetch all xepmarket2_ options in one query
        $results = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'xepmarket2_%'",
            ARRAY_A
        );

        $options_cache = array();
        foreach ($results as $row) {
            $options_cache[$row['option_name']] = maybe_unserialize($row['option_value']);
        }

        // Cache for 5 minutes
        set_transient($cache_key, $options_cache, 300);
    }

    return $options_cache;
}

/**
 * PERFORMANCE: Fast theme option getter using bulk cache
 */
function xepmarket2_get_option_fast($key, $default = '')
{
    $all_options = xepmarket2_get_all_options();
    return isset($all_options[$key]) && $all_options[$key] !== '' ? $all_options[$key] : $default;
}

/**
 * Clear theme options cache when any option is updated
 */
function xepmarket2_clear_options_cache($option_name)
{
    if (strpos($option_name, 'xepmarket2_') === 0) {
        delete_transient('xepmarket2_all_options');
    }
}
add_action('updated_option', 'xepmarket2_clear_options_cache');
add_action('added_option', 'xepmarket2_clear_options_cache');
add_action('after_switch_theme', function () {
    delete_transient('xepmarket2_all_options');
});

/**
 * Setup Theme
 */
function xepmarket2_setup()
{
    // Add default posts and comments RSS feed links to head.
    add_theme_support('automatic-feed-links');

    // Enable support for Post Thumbnails on posts and pages.
    add_theme_support('post-thumbnails');

    // This theme uses wp_nav_menu() in one location.
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'xepmarket2'),
        'footer' => __('Footer Menu', 'xepmarket2'),
    ));

    // Switch default core markup for search form, comment form, and comments to output valid HTML5.
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));

    // WooCommerce Support
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    // Site Icon (Favicon) Support
    add_theme_support('site-icon');
}
add_action('after_setup_theme', 'xepmarket2_setup');

/**
 * Enqueue WooCommerce Gallery Scripts
 */
function xepmarket2_enqueue_gallery_scripts()
{
    if (function_exists('is_product') && is_product()) {
        wp_enqueue_script('wc-single-product');
    }
}
add_action('wp_enqueue_scripts', 'xepmarket2_enqueue_gallery_scripts');

/**
 * Enqueue scripts and styles - PERFORMANCE OPTIMIZED
 */
function xepmarket2_scripts()
{
    // Google Fonts with display=swap for faster rendering
    wp_enqueue_style('xepmarket2-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;600;700;900&display=swap', array(), null);

    // Font Awesome - load only on pages that need it
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1');

    // Dashicons
    wp_enqueue_style('dashicons');

    // Main Stylesheet
    wp_enqueue_style('xepmarket2-style', get_stylesheet_uri(), array(), XEPMARKET_ALFA_VERSION);

    // Custom CSS
    wp_enqueue_style('xepmarket2-main', get_template_directory_uri() . '/assets/css/main.css', array(), XEPMARKET_ALFA_VERSION);

    // Custom JS - load in footer with defer
    wp_enqueue_script('xepmarket2-js', get_template_directory_uri() . '/assets/js/main.js', array('jquery'), XEPMARKET_ALFA_VERSION, true);

    wp_localize_script('xepmarket2-js', 'xep_live_search', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('xep_search_nonce'),
        'theme_version' => XEPMARKET_ALFA_VERSION
    ));
}
add_action('wp_enqueue_scripts', 'xepmarket2_scripts');

/**
 * PERFORMANCE: Add preconnect hints for external resources
 */
function xepmarket2_preconnect_hints()
{
    echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>';
    echo '<link rel="dns-prefetch" href="https://fonts.googleapis.com">';
    echo '<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">';
}
add_action('wp_head', 'xepmarket2_preconnect_hints', 1);

/**
 * Helper: Convert Hex to RGB
 */
function xepmarket2_hex2rgb($hex)
{
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

/**
 * Inject Custom Colors into Head
 */
function xepmarket2_inject_custom_colors()
{
    $primary = xepmarket2_get_option_fast('xepmarket2_color_primary', '#00f2ff');
    $secondary = xepmarket2_get_option_fast('xepmarket2_color_secondary', '#7000ff');
    $bg = xepmarket2_get_option_fast('xepmarket2_color_bg', '#05060a');
    $text = xepmarket2_get_option_fast('xepmarket2_color_text', '#ffffff');
    $text_muted = xepmarket2_get_option_fast('xepmarket2_color_text_muted', '#a0a0b8');

    $primary_rgb = xepmarket2_hex2rgb($primary);
    $secondary_rgb = xepmarket2_hex2rgb($secondary);

    echo '<style id="xepmarket2-custom-colors">
        :root {
            --primary: ' . esc_attr($primary) . ';
            --primary-rgb: ' . esc_attr($primary_rgb) . ';
            --secondary: ' . esc_attr($secondary) . ';
            --secondary-rgb: ' . esc_attr($secondary_rgb) . ';
            --bg-dark: ' . esc_attr($bg) . ';
            --text-main: ' . esc_attr($text) . ';
            --text-muted: ' . esc_attr($text_muted) . ';
        }
    </style>';
}
add_action('wp_head', 'xepmarket2_inject_custom_colors', 999);

/**
 * PERFORMANCE: Remove WordPress emoji scripts (not needed)
 */
function xepmarket2_disable_emojis()
{
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'xepmarket2_disable_emojis');

/**
 * PERFORMANCE: Add defer attribute to scripts
 */
function xepmarket2_defer_scripts($tag, $handle, $src)
{
    // Scripts to defer
    $defer_scripts = array('xepmarket2-js');

    if (in_array($handle, $defer_scripts)) {
        return str_replace(' src', ' defer src', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'xepmarket2_defer_scripts', 10, 3);

/**
 * PERFORMANCE: Remove query strings from static resources
 */
function xepmarket2_remove_query_strings($src)
{
    if (strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('style_loader_src', 'xepmarket2_remove_query_strings', 10, 1);
add_filter('script_loader_src', 'xepmarket2_remove_query_strings', 10, 1);

/**
 * Enqueue Admin Scripts and Styles
 */
function xepmarket2_admin_scripts($hook)
{
    if ('toplevel_page_xepmarket2-settings' !== $hook) {
        return;
    }

    wp_enqueue_style('xepmarket2-admin-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;600;700;900&display=swap', array(), null);
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1');
    wp_enqueue_style('xepmarket2-admin-style', get_template_directory_uri() . '/assets/css/admin-style.css', array(), time());
    wp_enqueue_script('xepmarket2-admin-js', get_template_directory_uri() . '/assets/js/admin-script.js', array('jquery'), time(), true);

    wp_localize_script('xepmarket2-admin-js', 'xep_admin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('xep_admin_nonce')
    ));

    // Enqueue Media Library
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'xepmarket2_admin_scripts');

/**
 * PERFORMANCE: Disable WooCommerce cart fragments on non-cart pages
 * Cart fragments AJAX is one of the biggest performance killers
 */
function xepmarket2_disable_cart_fragments()
{
    // Check if WooCommerce functions exist before using them
    if (function_exists('is_cart') && function_exists('is_checkout')) {
        if (!is_cart() && !is_checkout()) {
            wp_dequeue_script('wc-cart-fragments');
        }
    }
}
add_action('wp_enqueue_scripts', 'xepmarket2_disable_cart_fragments', 99);

/**
 * PERFORMANCE: Disable WooCommerce password strength meter (saves ~200KB)
 */
function xepmarket2_disable_password_strength()
{
    // Check if WooCommerce function exists before using it
    if (function_exists('is_account_page') && !is_account_page()) {
        wp_dequeue_script('wc-password-strength-meter');
    }
}
add_action('wp_enqueue_scripts', 'xepmarket2_disable_password_strength', 99);

/**
 * ============================================================================
 * ULTRA PERFORMANCE OPTIMIZER: XepMarket Elite Speed Boost
 * ============================================================================
 */

/**
 * 1. CLEAN UP WP_HEAD
 */
add_action('init', function () {
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
    remove_action('wp_head', 'wp_oembed_add_host_js');
});

/**
 * 2. DISABLE EMOJIS - Saves 1 HTTP request
 */
add_action('init', function () {
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
});

/**
 * 3. DEFER NON-CRITICAL SCRIPTS
 */
add_filter('script_loader_tag', function ($tag, $handle) {
    $defer_scripts = array('xepmarket2-js', 'wc-cart-fragment', 'woocommerce', 'js-cookie');
    if (in_array($handle, $defer_scripts)) {
        return str_replace(' src', ' defer="defer" src', $tag);
    }
    return $tag;
}, 10, 2);

/**
 * 4. OPTIMIZE WOOCOMMERCE ASSETS
 * Only load WC scripts on WooCommerce related pages
 */
add_action('wp_enqueue_scripts', function () {
    if (function_exists('is_woocommerce')) {
        if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
            // Dequeue WooCommerce scripts (keep wc-add-to-cart for AJAX add-to-cart popup)
            wp_dequeue_script('woocommerce');
            wp_dequeue_script('wc-cart-fragments');
            wp_dequeue_script('js-cookie');

            // Dequeue WooCommerce styles
            wp_dequeue_style('woocommerce-layout');
            wp_dequeue_style('woocommerce-smallscreen');
            wp_dequeue_style('woocommerce-general');
        }
    }

    // Dequeue Block Library CSS if not on a blog post
    if (!is_singular('post')) {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-blocks-style');
    }
}, 99);

/**
 * 5. SLOW DOWN HEARTBEAT API
 * Default is 15-60s, slowing it down saves CPU
 */
add_action('init', function () {
    add_filter('heartbeat_settings', function ($settings) {
        $settings['interval'] = 60;
        return $settings;
    });
}, 1);

/**
 * 6. REMOVE QUERY STRINGS FROM STATIC RESOURCES
 * Helps with proxy caching (older browser support)
 */
function xepmarket2_remove_script_version($src)
{
    if (strpos($src, '?ver=')) {
        return remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('script_loader_src', 'xepmarket2_remove_script_version', 15);
/**
 * 7. DISABLE GRAVATARS - Saves multiple external DNS lookups
 */
add_filter('get_avatar', '__return_false');

/**
 * 8. DISABLE JQUERY MIGRATE for non-admin - Saves an HTTP request
 */
add_action('wp_default_scripts', function ($scripts) {
    if (!is_admin() && isset($scripts->registered['jquery'])) {
        $script = $scripts->registered['jquery'];
        if ($script->deps) {
            $script->deps = array_diff($script->deps, array('jquery-migrate'));
        }
    }
});

/**
 * 9. AGGRESSIVE WP BLOAD REMOVAL
 */
add_action('wp_enqueue_scripts', function () {
    // Disable classic-theme-styles-css
    wp_dequeue_style('classic-theme-styles');

    // Disable global-styles-inline-css
    wp_dequeue_style('global-styles');
}, 100);

/**
 * WooCommerce Overrides
 */
// Remove WooCommerce sidebar
remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);

// Change number of products per row
add_filter('loop_shop_columns', function () {
    return 4;
}, 999);

/**
 * Handle Custom Products Per Page
 */
add_filter('loop_shop_per_page', function ($cols) {
    if (isset($_GET['ppp'])) {
        return intval($_GET['ppp']);
    }
    return 25; // Default for Alpha theme
}, 9999);

/**
 * Display Products Per Page Selector
 */
add_action('woocommerce_before_shop_loop', function () {
    $ppp = isset($_GET['ppp']) ? intval($_GET['ppp']) : 25;
    $options = array(25, 50, 75, 100);

    echo '<div class="xep-ppp-selector">';
    echo '<span class="ppp-label">SHOW:</span>';
    foreach ($options as $opt) {
        $active = ($ppp == $opt) ? 'active' : '';
        // Remove paged query arg and also handle permalink pagination (/page/N/)
        $current_url = preg_replace('/\/page\/[0-9]+\//', '/', $_SERVER['REQUEST_URI']);
        $url = add_query_arg(array('ppp' => $opt, 'paged' => 1), $current_url);
        echo '<a href="' . esc_url($url) . '" class="ppp-opt ' . $active . '">' . $opt . '</a>';
    }
    echo '</div>';
}, 25);

/**
 * Restore Native WooCommerce Variation Dropdowns (Server-side Sanitization)
 * Force ONLY the select element to be returned for all product variations
 */
add_filter('woocommerce_dropdown_variation_attribute_options_html', function ($html, $args) {
    if (function_exists('is_product') && is_product()) {
        preg_match('/<select.*?<\/select>/s', $html, $matches);
        if (isset($matches[0])) {
            return $matches[0];
        }
    }
    return $html;
}, 9999, 2);

/**
 * Kill Swatch Plugin Assets (CSS/JS) to prevent interference
 */
add_action('wp_enqueue_scripts', function () {
    if (function_exists('is_product') && is_product()) {
        wp_dequeue_style('vi-wpvs-frontend-style');
        wp_dequeue_script('vi-wpvs-frontend-script');
    }
}, 9999);

/**
 * Add identification classes for variation selects
 */
add_filter('woocommerce_dropdown_variation_attribute_options_args', function ($args) {
    // Add specific class for all variation selects for premium styling
    $args['class'] = isset($args['class']) ? $args['class'] . ' alpha-modern-select' : 'alpha-modern-select';

    // Add specific class for color attributes
    if (stripos($args['attribute'], 'color') !== false || stripos($args['attribute'], 'colour') !== false || stripos($args['attribute'], 'renk') !== false) {
        $args['class'] .= ' color-variation-select';
    }
    return $args;
}, 9999);

// Use full size images in loop to avoid cropping issues and match product page
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
add_action('woocommerce_before_shop_loop_item_title', 'xepmarket2_template_loop_product_thumbnail', 10);
function xepmarket2_template_loop_product_thumbnail()
{
    global $product;
    $size = 'full'; // Using full size for original aspect ratio, CSS will scale it down
    echo $product ? $product->get_image($size) : '';
}

// Change number of products per page
add_filter('loop_shop_per_page', function ($cols) {
    return 9;
}, 999);

// Customizing WooCommerce breadcrumb
if (get_option('xepmarket2_mod_breadcrumb_modern', '1') == '1') {
    add_filter('woocommerce_breadcrumb_defaults', function () {
        return array(
            'delimiter' => ' <span class="breadcrumb-sep">/</span> ',
            'wrap_before' => '<nav class="woocommerce-breadcrumb">',
            'wrap_after' => '</nav>',
            'before' => '',
            'after' => '',
            'home' => _x('Home', 'breadcrumb', 'woocommerce'),
        );
    });
}

/**
 * AJAX Cart Fragments
 */
add_filter('woocommerce_add_to_cart_fragments', 'xepmarket2_cart_count_fragments', 10, 1);
function xepmarket2_cart_count_fragments($fragments)
{
    ob_start();
    ?>
    <a class="cart-contents" href="<?php echo esc_url(wc_get_cart_url()); ?>"
        title="<?php esc_attr_e('View cart', 'xepmarket2'); ?>">
        <div class="cart-icon-wrapper">
            <i class="dashicons dashicons-cart"></i>
            <span class="cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
        </div>
    </a>
    <?php
    $fragments['a.cart-contents'] = ob_get_clean();
    return $fragments;
}

/**
 * Simplify Checkout Fields - Ideal for Digital Products
 */
if (get_option('xepmarket2_mod_custom_checkout', '1') == '1') {
    add_filter('woocommerce_checkout_fields', 'xepmarket2_simplify_checkout_fields', 9999);
    add_filter('woocommerce_default_address_fields', 'xepmarket2_override_default_address_labels', 9999);
    add_filter('woocommerce_get_country_locale', 'xepmarket2_override_country_locale_labels', 9999);
}

// Override base address field labels
function xepmarket2_override_default_address_labels($fields)
{
    $label_map = [
        'address_1' => 'Address Line 1',
        'address_2' => 'Address Line 2',
        'city' => 'City',
        'state' => 'State / County',
        'postcode' => 'Postcode / ZIP',
        'country' => 'Country',
    ];
    foreach ($label_map as $key => $default_label) {
        if (isset($fields[$key])) {
            $custom = get_option('xepmarket2_chk_name_' . $key, '');
            $fields[$key]['label'] = !empty($custom) ? $custom : $default_label;
        }
    }
    return $fields;
}

// Prevent country-specific locale from overriding our labels
function xepmarket2_override_country_locale_labels($locale)
{
    $label_map = [
        'address_1' => 'Address Line 1',
        'address_2' => 'Address Line 2',
        'city' => 'City',
        'state' => 'State / County',
        'postcode' => 'Postcode / ZIP',
    ];
    foreach ($locale as $country => &$fields) {
        foreach ($label_map as $key => $default_label) {
            if (isset($fields[$key]['label'])) {
                $custom = get_option('xepmarket2_chk_name_' . $key, '');
                $fields[$key]['label'] = !empty($custom) ? $custom : $default_label;
            }
        }
    }
    return $locale;
}
function xepmarket2_simplify_checkout_fields($fields)
{
    // Default labels and order values for each field
    $field_defaults = [
        'first_name' => ['label' => 'First Name', 'order' => '1'],
        'last_name' => ['label' => 'Last Name', 'order' => '1'],
        'company' => ['label' => 'Company', 'order' => '2'],
        'country' => ['label' => 'Country', 'order' => '3'],
        'address_1' => ['label' => 'Address Line 1', 'order' => '4'],
        'address_2' => ['label' => 'Address Line 2', 'order' => '5'],
        'city' => ['label' => 'City', 'order' => '6'],
        'state' => ['label' => 'State / County', 'order' => '6'],
        'postcode' => ['label' => 'Postcode / ZIP', 'order' => '7'],
        'phone' => ['label' => 'Phone Number', 'order' => '8'],
        'email' => ['label' => 'Email Address', 'order' => '9'],
        'telegram' => ['label' => 'Telegram Username', 'order' => '8'],
    ];

    $options = [
        'billing_first_name' => get_option('xepmarket2_chk_first_name', '1'),
        'billing_last_name' => get_option('xepmarket2_chk_last_name', '1'),
        'billing_company' => get_option('xepmarket2_chk_company', '0'),
        'billing_country' => get_option('xepmarket2_chk_country', '1'),
        'billing_address_1' => get_option('xepmarket2_chk_address_1', '1'),
        'billing_address_2' => get_option('xepmarket2_chk_address_2', '0'),
        'billing_city' => get_option('xepmarket2_chk_city', '1'),
        'billing_state' => get_option('xepmarket2_chk_state', '1'),
        'billing_postcode' => get_option('xepmarket2_chk_postcode', '1'),
        'billing_phone' => get_option('xepmarket2_chk_phone', '1'),
        'billing_email' => get_option('xepmarket2_chk_email', '1'),
    ];

    // Collect all active fields with their order numbers
    $all_fields_ordered = [];

    foreach ($options as $key => $status) {
        if ($status !== '1') {
            unset($fields['billing'][$key]);
        } else {
            $base_key = str_replace('billing_', '', $key);
            // Always apply label: use saved custom name, or fallback to our default label
            $custom_name = get_option('xepmarket2_chk_name_' . $base_key, '');
            $fallback_label = $field_defaults[$base_key]['label'] ?? '';
            $final_label = !empty($custom_name) ? $custom_name : $fallback_label;
            if (!empty($final_label) && isset($fields['billing'][$key])) {
                $fields['billing'][$key]['label'] = $final_label;
            }
            $is_required = get_option('xepmarket2_chk_req_' . $base_key, '1');
            if (isset($fields['billing'][$key])) {
                $fields['billing'][$key]['required'] = ($is_required === '1');
            }
            $order_num = intval(get_option('xepmarket2_chk_order_' . $base_key, $field_defaults[$base_key]['order'] ?? '99'));
            $all_fields_ordered[$key] = $order_num;
        }
    }

    // Phone field special settings
    if (isset($fields['billing']['billing_phone'])) {
        $custom_name = get_option('xepmarket2_chk_name_phone', '');
        $fields['billing']['billing_phone']['label'] = !empty($custom_name) ? $custom_name : 'Phone Number';
        $fields['billing']['billing_phone']['placeholder'] = 'e.g. +1 123 456 7890';
        $fields['billing']['billing_phone']['required'] = (get_option('xepmarket2_chk_req_phone', '1') === '1');
    }

    // Telegram field
    if (get_option('xepmarket2_chk_telegram', '1') == '1') {
        $custom_name = get_option('xepmarket2_chk_name_telegram', '');
        $fields['billing']['billing_telegram'] = array(
            'label' => !empty($custom_name) ? $custom_name : 'Telegram Username',
            'placeholder' => '@username',
            'required' => (get_option('xepmarket2_chk_req_telegram', '1') === '1'),
            'class' => array('form-row-wide'),
            'clear' => true,
            'priority' => 25
        );
        $order_num = intval(get_option('xepmarket2_chk_order_telegram', '8'));
        $all_fields_ordered['billing_telegram'] = $order_num;
    }

    // Dynamic Custom Fields
    $custom_fields_json = get_option('xepmarket2_chk_custom_fields', '[]');
    $custom_fields = json_decode($custom_fields_json, true);
    if (is_array($custom_fields)) {
        foreach ($custom_fields as $cf) {
            $field_key = 'billing_' . $cf['id'];
            $cf_order = isset($cf['order']) ? intval($cf['order']) : 99;
            $fields['billing'][$field_key] = array(
                'label' => !empty($cf['label']) ? $cf['label'] : 'Custom Field',
                'placeholder' => '',
                'required' => !empty($cf['required']),
                'class' => array('form-row-wide'),
                'clear' => true,
                'priority' => 100
            );
            $all_fields_ordered[$field_key] = $cf_order;
        }
    }

    // Group fields by order number and assign priorities + CSS classes
    $order_groups = [];
    foreach ($all_fields_ordered as $fkey => $forder) {
        $order_groups[$forder][] = $fkey;
    }
    ksort($order_groups);

    $priority = 10;
    $css_order_styles = '';
    foreach ($order_groups as $order_num => $group_keys) {
        $count = count($group_keys);
        $i = 0;
        while ($i < $count) {
            if ($i + 1 < $count) {
                // Pair two fields side by side
                $left_key = $group_keys[$i];
                $right_key = $group_keys[$i + 1];
                if (isset($fields['billing'][$left_key])) {
                    $fields['billing'][$left_key]['priority'] = $priority;
                    $fields['billing'][$left_key]['class'] = array('form-row-first');
                    $css_order_styles .= '#' . $left_key . '_field{order:' . $priority . ' !important;grid-column:span 1 !important;}';
                }
                if (isset($fields['billing'][$right_key])) {
                    $fields['billing'][$right_key]['priority'] = $priority + 1;
                    $fields['billing'][$right_key]['class'] = array('form-row-last');
                    $css_order_styles .= '#' . $right_key . '_field{order:' . ($priority + 1) . ' !important;grid-column:span 1 !important;}';
                }
                $priority += 2;
                $i += 2;
            } else {
                // Odd one out = full width
                $solo_key = $group_keys[$i];
                if (isset($fields['billing'][$solo_key])) {
                    $fields['billing'][$solo_key]['priority'] = $priority;
                    $fields['billing'][$solo_key]['class'] = array('form-row-wide');
                    $css_order_styles .= '#' . $solo_key . '_field{order:' . $priority . ' !important;grid-column:span 2 !important;}';
                }
                $priority++;
                $i++;
            }
        }
        $priority += 2;
    }

    // Output CSS for grid ordering (must use wp_footer because this filter runs after wp_head)
    if (!empty($css_order_styles)) {
        add_action('wp_footer', function () use ($css_order_styles) {
            echo '<style id="xep-checkout-order">' . $css_order_styles . '</style>';
        }, 1);
    }

    return $fields;
}

/**
 * ═══════════════════════════════════════════════════════════════════
 * Localized "No Shipping Available" Warning
 * Shows warning in the selected country's native language
 * ═══════════════════════════════════════════════════════════════════
 */
function xepmarket2_no_shipping_messages()
{
    return [
        'default' => 'We do not currently ship to this country.',
        'TR' => 'Şu anda bu ülkeye gönderim yapılmamaktadır.',
        'CN' => '我们目前不向该国家/地区发货。',
        'TW' => '我們目前不向該國家/地區發貨。',
        'HK' => '我們目前不向該國家/地區發貨。',
        'JP' => '現在、この国への配送は行っておりません。',
        'KR' => '현재 이 국가로는 배송이 불가합니다.',
        'DE' => 'Wir liefern derzeit nicht in dieses Land.',
        'AT' => 'Wir liefern derzeit nicht in dieses Land.',
        'CH' => 'Wir liefern derzeit nicht in dieses Land.',
        'FR' => 'Nous ne livrons pas actuellement dans ce pays.',
        'ES' => 'Actualmente no realizamos envíos a este país.',
        'MX' => 'Actualmente no realizamos envíos a este país.',
        'AR' => 'Actualmente no realizamos envíos a este país.',
        'CO' => 'Actualmente no realizamos envíos a este país.',
        'IT' => 'Al momento non effettuiamo spedizioni in questo paese.',
        'PT' => 'Atualmente não fazemos envios para este país.',
        'BR' => 'No momento, não fazemos envios para este país.',
        'NL' => 'Wij leveren momenteel niet in dit land.',
        'BE' => 'Wij leveren momenteel niet in dit land.',
        'PL' => 'Obecnie nie wysyłamy do tego kraju.',
        'RU' => 'В настоящее время мы не осуществляем доставку в эту страну.',
        'UA' => 'Наразі ми не здійснюємо доставку до цієї країни.',
        'CZ' => 'V současné době do této země nedoručujeme.',
        'SK' => 'V súčasnosti do tejto krajiny nedoručujeme.',
        'RO' => 'Momentan nu livrăm în această țară.',
        'HU' => 'Jelenleg nem szállítunk ebbe az országba.',
        'BG' => 'В момента не доставяме до тази държава.',
        'HR' => 'Trenutno ne isporučujemo u ovu zemlju.',
        'RS' => 'Тренутно не испоручујемо у ову земљу.',
        'GR' => 'Προς το παρόν δεν αποστέλλουμε σε αυτή τη χώρα.',
        'SE' => 'Vi levererar för närvarande inte till detta land.',
        'NO' => 'Vi leverer for øyeblikket ikke til dette landet.',
        'DK' => 'Vi leverer i øjeblikket ikke til dette land.',
        'FI' => 'Emme tällä hetkellä toimita tähän maahan.',
        'SA' => 'لا نقوم حالياً بالشحن إلى هذا البلد.',
        'AE' => 'لا نقوم حالياً بالشحن إلى هذا البلد.',
        'EG' => 'لا نقوم حالياً بالشحن إلى هذا البلد.',
        'IL' => 'אנחנו לא משלחים כרגע למדינה זו.',
        'IN' => 'वर्तमान में हम इस देश में शिपिंग नहीं करते हैं।',
        'TH' => 'ขณะนี้เราไม่จัดส่งไปยังประเทศนี้',
        'VN' => 'Hiện tại chúng tôi không giao hàng đến quốc gia này.',
        'ID' => 'Saat ini kami tidak melakukan pengiriman ke negara ini.',
        'MY' => 'Kami tidak menghantar ke negara ini buat masa ini.',
        'PH' => 'Hindi kami nagpapadala sa bansang ito sa kasalukuyan.',
        'NG' => 'We do not currently ship to this country.',
        'ZA' => 'We do not currently ship to this country.',
        'KE' => 'We do not currently ship to this country.',
        'AU' => 'We do not currently ship to this country.',
        'NZ' => 'We do not currently ship to this country.',
        'CA' => 'We do not currently ship to this country.',
        'GB' => 'We do not currently ship to this country.',
        'IE' => 'We do not currently ship to this country.',
        'US' => 'We do not currently ship to this country.',
    ];
}

// Override WooCommerce's default "no shipping" message
add_filter('woocommerce_no_shipping_available_html', 'xepmarket2_localized_no_shipping_msg');
add_filter('woocommerce_cart_no_shipping_available_html', 'xepmarket2_localized_no_shipping_msg');

function xepmarket2_localized_no_shipping_msg($msg)
{
    $country = WC()->customer ? WC()->customer->get_shipping_country() : '';
    if (!$country) {
        $country = WC()->customer ? WC()->customer->get_billing_country() : '';
    }

    $messages = xepmarket2_no_shipping_messages();
    $localized = isset($messages[$country]) ? $messages[$country] : $messages['default'];

    return '<div class="xep-no-shipping-notice" style="background: rgba(255, 69, 58, 0.08); border: 1px solid rgba(255, 69, 58, 0.25); border-radius: 12px; padding: 20px; text-align: center; margin: 15px 0;">
        <div style="font-size: 28px; margin-bottom: 8px;">🚫</div>
        <p style="color: #ff453a; font-weight: 700; font-size: 15px; margin: 0 0 6px;">' . esc_html($localized) . '</p>
        <p style="color: rgba(255,255,255,0.5); font-size: 12px; margin: 0;">' . esc_html($messages['default']) . '</p>
    </div>';
}

// Inject localization data into checkout page for instant JS feedback
add_action('wp_footer', 'xepmarket2_no_shipping_js', 50);
function xepmarket2_no_shipping_js()
{
    if (!function_exists('is_checkout') || !is_checkout())
        return;
    $messages = xepmarket2_no_shipping_messages();
    ?>
    <script>
        (function ($) {
            if (typeof $ === 'undefined') return;
            var noShipMsgs = <?php echo json_encode($messages, JSON_UNESCAPED_UNICODE); ?>;

            $(document.body).on('updated_checkout', function () {
                // Check if shipping notice exists in the updated checkout
                var $shippingRows = $('.woocommerce-shipping-totals');
                if ($shippingRows.length === 0) return;

                var country = $('#billing_country').val() || '';
                var $noShip = $shippingRows.find('.xep-no-shipping-notice');

                // If WooCommerce already shows our custom notice, we're done
                if ($noShip.length > 0) return;

                // Check if there's a "no shipping methods" notice
                var $wooNotice = $shippingRows.find('.shipping-destination, [data-title="Shipping"]');
                if ($wooNotice.length > 0) {
                    var text = $wooNotice.text().toLowerCase();
                    if (text.indexOf('no shipping') > -1 || text.indexOf('does not support') > -1) {
                        var msg = noShipMsgs[country] || noShipMsgs['default'];
                        $wooNotice.html(
                            '<div class="xep-no-shipping-notice" style="background:rgba(255,69,58,0.08);border:1px solid rgba(255,69,58,0.25);border-radius:12px;padding:20px;text-align:center;">' +
                            '<div style="font-size:28px;margin-bottom:8px;">🚫</div>' +
                            '<p style="color:#ff453a;font-weight:700;font-size:15px;margin:0 0 6px;">' + msg + '</p>' +
                            '<p style="color:rgba(255,255,255,0.5);font-size:12px;margin:0;">' + noShipMsgs['default'] + '</p>' +
                            '</div>'
                        );
                    }
                }
            });
        })(jQuery);
    </script>
    <?php
}

/**
 * ═══════════════════════════════════════════════════════════════════
 * PLACE ORDER BUTTON — Disable until all required fields are filled
 * and Privacy Policy is accepted
 * ═══════════════════════════════════════════════════════════════════
 */
require_once get_template_directory() . '/inc/checkout-validation.php';

if (get_option('xepmarket2_mod_custom_checkout', '1') == '1') {
    add_action('woocommerce_checkout_update_order_meta', 'xepmarket2_save_checkout_fields');
}
function xepmarket2_save_checkout_fields($order_id)
{
    if (!empty($_POST['billing_telegram'])) {
        update_post_meta($order_id, '_billing_telegram', sanitize_text_field($_POST['billing_telegram']));
    }

    // Save dynamic custom fields
    $custom_fields_json = get_option('xepmarket2_chk_custom_fields', '[]');
    $custom_fields = json_decode($custom_fields_json, true);
    if (is_array($custom_fields)) {
        foreach ($custom_fields as $cf) {
            $field_key = 'billing_' . $cf['id'];
            if (!empty($_POST[$field_key])) {
                update_post_meta($order_id, '_' . $field_key, sanitize_text_field($_POST[$field_key]));
            }
        }
    }
}

/**
 * Display Custom Fields in Admin Order Pages
 */
if (get_option('xepmarket2_mod_custom_checkout', '1') == '1') {
    add_action('woocommerce_admin_order_data_after_billing_address', 'xepmarket2_display_custom_fields_admin', 10, 1);
}
function xepmarket2_display_custom_fields_admin($order)
{
    $telegram = get_post_meta($order->get_id(), '_billing_telegram', true);
    if ($telegram) {
        $name = get_option('xepmarket2_chk_name_telegram', 'Telegram Username');
        if (empty($name))
            $name = 'Telegram Username';
        echo '<p><strong>' . esc_html($name) . ':</strong> ' . esc_html($telegram) . '</p>';
    }

    // Display dynamic custom fields
    $custom_fields_json = get_option('xepmarket2_chk_custom_fields', '[]');
    $custom_fields = json_decode($custom_fields_json, true);
    if (is_array($custom_fields)) {
        foreach ($custom_fields as $cf) {
            $field_key = '_billing_' . $cf['id'];
            $val = get_post_meta($order->get_id(), $field_key, true);
            if (!empty($val)) {
                echo '<p><strong>' . esc_html($cf['label']) . ':</strong> ' . esc_html($val) . '</p>';
            }
        }
    }
}

// Remove "Additional Information" (Order Notes)
add_filter('woocommerce_enable_order_notes_field', '__return_false');

// Disable "Ship to a different address?" - Always use billing address as shipping
add_filter('woocommerce_ship_to_different_address_checked', '__return_false');

// Hide the shipping address section completely throughout checkout and order pages
add_filter('woocommerce_cart_needs_shipping_address', '__return_false');
add_filter('woocommerce_order_needs_shipping_address', '__return_false');

// Remove Shipping Address block from My Account -> Addresses page
add_filter('woocommerce_my_account_get_addresses', function ($addresses) {
    if (isset($addresses['shipping'])) {
        unset($addresses['shipping']);
    }
    return $addresses;
}, 99);

// Change "Billing details" and "Billing address" to "Billing and Shipping Details/Address"
add_filter('gettext', 'xepmarket2_change_billing_details_text', 20, 3);
function xepmarket2_change_billing_details_text($translated_text, $text, $domain)
{
    if ($domain === 'woocommerce') {
        if ($text === 'Billing details') {
            return 'Billing and Shipping Details';
        }
        if ($text === 'Billing address') {
            return 'Billing and Shipping Address';
        }
    }
    return $translated_text;
}


/**
 * Force only OmniXEP Payment Gateway
 */
if (get_option('xepmarket2_mod_omnixep_restrict', '1') == '1') {
    add_filter('woocommerce_available_payment_gateways', 'xepmarket2_restrict_payment_gateways');
}
function xepmarket2_restrict_payment_gateways($available_gateways)
{
    if (is_admin()) {
        return $available_gateways;
    }

    // Keep only omnixep if it exists and is enabled
    if (isset($available_gateways['omnixep'])) {
        $omnixep = $available_gateways['omnixep'];
        return array('omnixep' => $omnixep);
    }

    return $available_gateways;
}

/**
 * Ensure OmniXEP is shown even in some block environments
 */
add_filter('woocommerce_payment_gateway_get_title', function ($title, $id) {
    if ($id === 'omnixep' && empty($title)) {
        return 'OmniXEP Payment';
    }
    return $title;
}, 10, 2);

/**
 * Modern Sale Badge with Discount Percentage
 */
if (get_option('xepmarket2_mod_sale_badges', '1') == '1') {
    add_filter('woocommerce_sale_flash', 'xepmarket2_custom_sale_badge', 10, 3);
}
function xepmarket2_custom_sale_badge($html, $post, $product)
{
    if ($product->is_type('variable')) {
        $percentages = array();
        $prices = $product->get_variation_prices();
        foreach ($prices['regular_price'] as $key => $regular_price) {
            $sale_price = $prices['sale_price'][$key];
            if ($sale_price < $regular_price) {
                $percentages[] = round(((floatval($regular_price) - floatval($sale_price)) / floatval($regular_price)) * 100);
            }
        }
        $percentage = max($percentages);
    } else {
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        if (!$regular_price || !$sale_price)
            return $html;
        $percentage = round(((floatval($regular_price) - floatval($sale_price)) / floatval($regular_price)) * 100);
    }

    if ($percentage > 0) {
        return '<span class="onsale">-' . $percentage . '%</span>';
    }
    return $html;
}

/**
 * XepMarket2 Theme Settings
 */
function xepmarket2_add_admin_menu()
{
    add_menu_page(
        'XEPMARKET-ALFA Settings',
        'XEPMARKET-ALFA',
        'manage_options',
        'xepmarket2-settings',
        'xepmarket2_settings_page',
        'dashicons-admin-appearance',
        60
    );
}
add_action('admin_menu', 'xepmarket2_add_admin_menu');

/**
 * Initialize Default Slider Values
 */
function xepmarket2_init_defaults()
{
    if (get_option('xepmarket2_defaults_set_v1_7') === 'yes') {
        return;
    }

    // Site Colors
    update_option('xepmarket2_color_primary', '#00f2ff');
    update_option('xepmarket2_color_secondary', '#7000ff');
    update_option('xepmarket2_color_bg', '#05060a');
    update_option('xepmarket2_color_text', '#ffffff');
    update_option('xepmarket2_color_text_muted', '#a0a0b8');

    // Slide 1
    update_option('xepmarket2_slider_title_1', 'Free <span class="logo-accent">Worldwide</span> Shipping');
    update_option('xepmarket2_slider_desc_1', 'We deliver the future of tech to your doorstep, anywhere in the world. Zero shipping costs, 100% secure.');
    update_option('xepmarket2_slider_btn_text_1', 'Learn More');
    update_option('xepmarket2_slider_btn_link_1', '/shop');
    update_option('xepmarket2_slider_img_1', get_template_directory_uri() . '/assets/images/slide2.png?v=1.5');

    // Slide 2
    update_option('xepmarket2_slider_title_2', 'Innovative <span class="logo-accent">Tech Products</span>');
    update_option('xepmarket2_slider_desc_2', 'Explore the latest in cutting-edge technology. From smart devices to premium gadgets, we bring you the future.');
    update_option('xepmarket2_slider_btn_text_2', 'Discover More');
    update_option('xepmarket2_slider_btn_link_2', '/shop');
    update_option('xepmarket2_slider_img_2', get_template_directory_uri() . '/assets/images/slide_tech.png?v=1.6');

    // Slide 3
    update_option('xepmarket2_slider_title_3', 'Flash Sale: <span class="logo-accent">80% OFF</span>');
    update_option('xepmarket2_slider_desc_3', 'Limited time offer on select premium items. Enhance your lifestyle with massive discounts.');
    update_option('xepmarket2_slider_btn_text_3', 'Claim Discount');
    update_option('xepmarket2_slider_btn_link_3', '/shop');
    update_option('xepmarket2_slider_img_3', get_template_directory_uri() . '/assets/images/slide3.png?v=1.5');

    // Static Hero
    update_option('xepmarket2_hero_title', 'Elevate Your <br><span class="logo-accent">Crypto Lifestyle</span>');
    update_option('xepmarket2_hero_subtitle', 'Premium hardware wallets, exclusive crypto apparel, and unique digital collectibles. Secure your assets and show your passion. Pay exclusively with XEP for secure and instant delivery.');
    update_option('xepmarket2_hero_btn1_text', 'Shop Crypto Gear');
    update_option('xepmarket2_hero_btn1_link', '/shop');
    update_option('xepmarket2_hero_btn2_text', 'Join Community');
    update_option('xepmarket2_hero_btn2_link', 'https://t.me/xepmarket_official');

    // Highlights
    $default_icons = ['dashicons-location-alt', 'dashicons-admin-site-alt3', 'dashicons-thumbs-up', 'dashicons-tag'];
    $default_titles = ['FREE SHIPPING', 'WORLDWIDE DELIVERY', '100% SATISFACTION', 'UP TO 80% OFF'];
    $default_descs = ['On all orders across the store', 'Shipping to every corner of the world', 'Guaranteed customer happiness', 'Massive sale on innovative tech'];
    for ($i = 1; $i <= 4; $i++) {
        update_option('xepmarket2_highlight_icon_' . $i, $default_icons[$i - 1]);
        update_option('xepmarket2_highlight_title_' . $i, $default_titles[$i - 1]);
        update_option('xepmarket2_highlight_desc_' . $i, $default_descs[$i - 1]);
    }

    // Flash Deals
    update_option('xepmarket2_flash_deals_enable', '1');
    update_option('xepmarket2_flash_deals_title', 'Flash <span class="logo-accent">Deals</span>');
    update_option('xepmarket2_flash_deals_subtitle', 'Exclusive discounts on premium gear.');
    update_option('xepmarket2_flash_deals_limit', '12');
    update_option('xepmarket2_flash_deals_columns', '4');

    // Featured
    update_option('xepmarket2_featured_title', 'Trending Gear');
    update_option('xepmarket2_featured_subtitle', 'Must-have items for every Web3 enthusiast');
    update_option('xepmarket2_featured_limit', '20');
    update_option('xepmarket2_featured_columns', '4');

    // Support
    update_option('xepmarket2_support_title', 'Web3 Native <span class="logo-accent">Support</span>');
    update_option('xepmarket2_support_desc', 'Need help with your order or crypto payment? Our decentralized support team is ready to help you navigate the future of commerce.');
    update_option('xepmarket2_support_email', 'crypto@xepmarket.com');
    update_option('xepmarket2_support_telegram', '@xepmarket');

    // Modules
    update_option('xepmarket2_mod_breadcrumb_modern', '1');

    // Logos
    update_option('xepmarket2_header_logo_type', 'text');
    update_option('xepmarket2_header_logo_text_1', 'XEP');
    update_option('xepmarket2_header_logo_text_2', 'MARKET');
    update_option('xepmarket2_footer_logo_type', 'text');
    update_option('xepmarket2_footer_logo_text_1', 'XEP');
    update_option('xepmarket2_footer_logo_text_2', 'MARKET');

    // Tokens
    update_option('xepmarket2_token_name_1', 'XEP');
    update_option('xepmarket2_token_status_1', 'live');
    update_option('xepmarket2_token_name_2', 'MMX');
    update_option('xepmarket2_token_status_2', 'soon');

    update_option('xepmarket2_defaults_set_v1_7', 'yes');
}
add_action('init', 'xepmarket2_init_defaults');


function xepmarket2_settings_init()
{
    register_setting('xepmarket2_settings_group', 'xepmarket2_favicon');

    // Site Design Colors
    register_setting('xepmarket2_settings_group', 'xepmarket2_color_primary');
    register_setting('xepmarket2_settings_group', 'xepmarket2_color_secondary');
    register_setting('xepmarket2_settings_group', 'xepmarket2_color_bg');
    register_setting('xepmarket2_settings_group', 'xepmarket2_color_text');
    register_setting('xepmarket2_settings_group', 'xepmarket2_color_text_muted');
    register_setting('xepmarket2_settings_group', 'xepmarket2_color_preset');

    // Header Logo Registration
    register_setting('xepmarket2_settings_group', 'xepmarket2_header_logo_type');
    register_setting('xepmarket2_settings_group', 'xepmarket2_header_logo_text_1');
    register_setting('xepmarket2_settings_group', 'xepmarket2_header_logo_text_2');
    register_setting('xepmarket2_settings_group', 'xepmarket2_header_logo_img');

    // Footer Logo Registration
    register_setting('xepmarket2_settings_group', 'xepmarket2_footer_logo_type');
    register_setting('xepmarket2_settings_group', 'xepmarket2_footer_logo_text_1');
    register_setting('xepmarket2_settings_group', 'xepmarket2_footer_logo_text_2');
    register_setting('xepmarket2_settings_group', 'xepmarket2_footer_logo_img');

    // Social Media Registration
    register_setting('xepmarket2_settings_group', 'xepmarket2_social_telegram');
    register_setting('xepmarket2_settings_group', 'xepmarket2_social_discord');
    register_setting('xepmarket2_settings_group', 'xepmarket2_social_twitter');
    register_setting('xepmarket2_settings_group', 'xepmarket2_social_instagram');
    register_setting('xepmarket2_settings_group', 'xepmarket2_social_youtube');
    register_setting('xepmarket2_settings_group', 'xepmarket2_social_pinterest');
    register_setting('xepmarket2_settings_group', 'xepmarket2_social_tiktok');

    // Payment Tokens Registration
    for ($i = 1; $i <= 4; $i++) {
        register_setting('xepmarket2_settings_group', 'xepmarket2_token_name_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_token_status_' . $i);
    }

    register_setting('xepmarket2_settings_group', 'xepmarket2_admin_banner_img');
    register_setting('xepmarket2_settings_group', 'xepmarket2_banner_main');
    register_setting('xepmarket2_settings_group', 'xepmarket2_banner_discount');
    register_setting('xepmarket2_settings_group', 'xepmarket2_banner_bg');
    register_setting('xepmarket2_settings_group', 'xepmarket2_banner_text_color');
    register_setting('xepmarket2_settings_group', 'xepmarket2_banner_visibility');
    register_setting('xepmarket2_settings_group', 'xepmarket2_hero_title');
    register_setting('xepmarket2_settings_group', 'xepmarket2_hero_subtitle');
    register_setting('xepmarket2_settings_group', 'xepmarket2_footer_desc');

    // Slider Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_slider_enable');
    for ($i = 1; $i <= 3; $i++) {
        register_setting('xepmarket2_settings_group', 'xepmarket2_slider_title_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_slider_desc_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_slider_btn_text_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_slider_btn_link_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_slider_img_' . $i);
    }

    // Static Hero Button Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_hero_btn1_text');
    register_setting('xepmarket2_settings_group', 'xepmarket2_hero_btn1_link');
    register_setting('xepmarket2_settings_group', 'xepmarket2_hero_btn2_text');
    register_setting('xepmarket2_settings_group', 'xepmarket2_hero_btn2_link');

    // Highlights Settings (4 items)
    for ($i = 1; $i <= 4; $i++) {
        register_setting('xepmarket2_settings_group', 'xepmarket2_highlight_icon_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_highlight_title_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_highlight_desc_' . $i);
    }

    // Flash Deals Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_flash_deals_enable');
    register_setting('xepmarket2_settings_group', 'xepmarket2_flash_deals_title');
    register_setting('xepmarket2_settings_group', 'xepmarket2_flash_deals_subtitle');
    register_setting('xepmarket2_settings_group', 'xepmarket2_flash_deals_limit');
    register_setting('xepmarket2_settings_group', 'xepmarket2_flash_deals_columns');

    // Featured Products Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_featured_title');
    register_setting('xepmarket2_settings_group', 'xepmarket2_featured_subtitle');
    register_setting('xepmarket2_settings_group', 'xepmarket2_featured_limit');
    register_setting('xepmarket2_settings_group', 'xepmarket2_featured_columns');

    // Support Section Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_support_title');
    register_setting('xepmarket2_settings_group', 'xepmarket2_support_desc');
    register_setting('xepmarket2_settings_group', 'xepmarket2_support_email');
    register_setting('xepmarket2_settings_group', 'xepmarket2_support_telegram');

    // Modules Toggles
    register_setting('xepmarket2_settings_group', 'xepmarket2_mod_omnixep_restrict');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mod_custom_checkout');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mod_sale_badges');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mod_breadcrumb_modern');

    // Checkout Customization Toggles
    $checkout_fields = ['first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email', 'telegram'];
    foreach ($checkout_fields as $field) {
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_' . $field);
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_name_' . $field);
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_req_' . $field);
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_order_' . $field);
    }

    // Register custom dynamic fields array (JSON string)
    register_setting('xepmarket2_settings_group', 'xepmarket2_chk_custom_fields', array(
        'type' => 'string',
        'sanitize_callback' => 'xepmarket2_sanitize_custom_fields_json',
    ));
}

function xepmarket2_sanitize_custom_fields_json($input)
{
    // Ensure we receive and store valid JSON
    if (empty($input))
        return '[]';
    $decoded = json_decode(wp_unslash($input), true);
    if (!is_array($decoded))
        return '[]';
    // Sanitize each field
    $clean = array();
    foreach ($decoded as $field) {
        if (!isset($field['id']))
            continue;
        $clean[] = array(
            'id' => sanitize_key($field['id']),
            'label' => sanitize_text_field($field['label'] ?? ''),
            'required' => !empty($field['required']),
            'order' => isset($field['order']) ? intval($field['order']) : 99,
        );
    }
    return wp_json_encode($clean);
}
add_action('admin_init', 'xepmarket2_settings_init');

function xepmarket2_get_option($key, $default = '')
{
    $option = get_option($key);
    return !empty($option) ? $option : $default;
}

function xepmarket2_settings_page()
{
    ?>
    <div class="xep-admin-wrap">
        <?php if ($admin_banner = get_option('xepmarket2_admin_banner_img')): ?>
            <div class="xep-admin-banner-area">
                <img src="<?php echo esc_url($admin_banner); ?>" alt="Admin Banner">
            </div>
        <?php endif; ?>

        <div class="xep-admin-header">
            <div class="xep-admin-title">
                <h1>XEPMARKET<span class="xep-logo-accent">-ALFA</span> Premium Settings</h1>
            </div>
            <div class="xep-admin-actions">
                <button type="button" class="xep-save-btn xep-trigger-save">Quick Save</button>
            </div>
        </div>

        <form method="post" action="options.php" id="xep-settings-form">
            <?php
            settings_fields('xepmarket2_settings_group');
            ?>

            <div class="xep-admin-container">
                <!-- Sidebar -->
                <div class="xep-admin-sidebar">
                    <div class="xep-nav-item active" data-tab="tab-general">
                        <i class="fas fa-cog"></i> General Settings
                    </div>
                    <div class="xep-nav-item" data-tab="tab-colors">
                        <i class="fas fa-palette"></i> Styling & Colors
                    </div>
                    <div class="xep-nav-item" data-tab="tab-hero">
                        <i class="fas fa-home"></i> Homepage & Slider
                    </div>
                    <div class="xep-nav-item" data-tab="tab-sections">
                        <i class="fas fa-th-large"></i> Page Sections
                    </div>
                    <div class="xep-nav-item" data-tab="tab-modules">
                        <i class="fas fa-puzzle-piece"></i> Theme Modules
                    </div>
                    <div class="xep-nav-item" data-tab="tab-support">
                        <i class="fas fa-headset"></i> Support & Contact
                    </div>
                    <div class="xep-nav-item" data-tab="tab-checkout">
                        <i class="fas fa-shopping-cart"></i> Checkout Customization
                    </div>
                    <div class="xep-nav-item" data-tab="tab-seo">
                        <i class="fas fa-search"></i> SEO & AI Settings
                    </div>
                    <div class="xep-nav-item" data-tab="tab-demo"
                        style="border-top: 1px solid var(--admin-border); padding-top: 15px; margin-top: 10px; color: var(--admin-primary);">
                        <i class="fas fa-magic"></i> Demo Setup
                    </div>
                    <div class="xep-nav-item" data-tab="tab-updater">
                        <i class="fas fa-sync-alt"></i> Auto Updater
                    </div>
                </div>

                <!-- Content Area -->
                <div class="xep-admin-content">

                    <!-- Tab: Colors -->
                    <div id="tab-colors" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Global Site Design Colors</h3>
                            <p class="description" style="margin-bottom: 25px;">Customize the main color palette and
                                typography colors for your store.</p>

                            <div class="xep-preset-grid"
                                style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 35px;">
                                <?php
                                $presets = array(
                                    'cyberpunk' => array('name' => 'Cyberpunk (Default)', 'primary' => '#00f2ff', 'secondary' => '#7000ff', 'bg' => '#05060a', 'text' => '#ffffff', 'muted' => '#a0a0b8'),
                                    'emerald' => array('name' => 'Emerald Night', 'primary' => '#00ff88', 'secondary' => '#0044ff', 'bg' => '#0a0f0d', 'text' => '#e0f2f1', 'muted' => '#80cbc4'),
                                    'lava' => array('name' => 'Ruby Lava', 'primary' => '#ff3333', 'secondary' => '#ffaa00', 'bg' => '#0f0a0a', 'text' => '#fff0f0', 'muted' => '#b8a0a0'),
                                    'gold' => array('name' => 'Royal Gold', 'primary' => '#ffd700', 'secondary' => '#ff8c00', 'bg' => '#0a0a05', 'text' => '#ffffff', 'muted' => '#b8b8a0'),
                                    'amethyst' => array('name' => 'Amethyst Dream', 'primary' => '#bf00ff', 'secondary' => '#ff0077', 'bg' => '#0d0a0f', 'text' => '#f3e5f5', 'muted' => '#b39ddb'),
                                    'oceanic' => array('name' => 'Oceanic Deep', 'primary' => '#00aaff', 'secondary' => '#00ffcc', 'bg' => '#050a0f', 'text' => '#e1f5fe', 'muted' => '#81d4fa'),
                                    'copper' => array('name' => 'Midnight Copper', 'primary' => '#b87333', 'secondary' => '#ff7f50', 'bg' => '#0c0805', 'text' => '#f5e6d3', 'muted' => '#a89080'),
                                    'forest' => array('name' => 'Forest Moss', 'primary' => '#6b8e23', 'secondary' => '#00ff88', 'bg' => '#080c05', 'text' => '#ddeedd', 'muted' => '#99aa99'),
                                    'arctic' => array('name' => 'Arctic Frost', 'primary' => '#a0d8ef', 'secondary' => '#ffffff', 'bg' => '#050a0f', 'text' => '#ffffff', 'muted' => '#a0b0b8'),
                                    'sunset' => array('name' => 'Neon Sunset', 'primary' => '#ff007f', 'secondary' => '#ffcc00', 'bg' => '#0f050a', 'text' => '#ffffff', 'muted' => '#b8a0b0'),
                                    'stealth' => array('name' => 'Carbon Stealth', 'primary' => '#444444', 'secondary' => '#888888', 'bg' => '#020202', 'text' => '#e0e0e0', 'muted' => '#777777'),
                                    'space' => array('name' => 'Deep Space', 'primary' => '#4b0082', 'secondary' => '#000000', 'bg' => '#020205', 'text' => '#e6e6fa', 'muted' => '#808090'),
                                    'matrix' => array('name' => 'Matrix Protocol', 'primary' => '#00ff41', 'secondary' => '#008f11', 'bg' => '#0a0a0a', 'text' => '#00ff41', 'muted' => '#006620'),
                                    'solana' => array('name' => 'Solana Vibes', 'primary' => '#14F195', 'secondary' => '#9945FF', 'bg' => '#0f1015', 'text' => '#ffffff', 'muted' => '#8e8e9f'),
                                    'crimson' => array('name' => 'Crimson Moon', 'primary' => '#ff2a2a', 'secondary' => '#a60000', 'bg' => '#0d0606', 'text' => '#ffe6e6', 'muted' => '#9e8080'),
                                    'sakura' => array('name' => 'Mystic Sakura', 'primary' => '#ffb7c5', 'secondary' => '#ff6b8b', 'bg' => '#120e10', 'text' => '#fdf5f7', 'muted' => '#a38992'),
                                    'abyssal' => array('name' => 'Abyssal Blue', 'primary' => '#00d2ff', 'secondary' => '#3a7bd5', 'bg' => '#050b14', 'text' => '#e0f2fe', 'muted' => '#7a95b5'),
                                    'solar' => array('name' => 'Solar Flare', 'primary' => '#ff5e62', 'secondary' => '#ff9966', 'bg' => '#140d0a', 'text' => '#fff0eb', 'muted' => '#a88a80'),
                                    'plasma' => array('name' => 'Plasma Void', 'primary' => '#b224ef', 'secondary' => '#7579ff', 'bg' => '#080512', 'text' => '#f6f0ff', 'muted' => '#9283a8'),
                                    'cyber_yellow' => array('name' => 'Cyber Yellow', 'primary' => '#fcee0a', 'secondary' => '#00fff5', 'bg' => '#080808', 'text' => '#ffffff', 'muted' => '#8c8c8c'),
                                    'hacker' => array('name' => 'Terminal Hacker', 'primary' => '#00ff00', 'secondary' => '#33cc33', 'bg' => '#000000', 'text' => '#00ff00', 'muted' => '#005500'),
                                    'bitcoin' => array('name' => 'Bitcoin Orange', 'primary' => '#f7931a', 'secondary' => '#ffaa33', 'bg' => '#1a1a1c', 'text' => '#f2f2f2', 'muted' => '#8f8f94'),
                                    'ethereum' => array('name' => 'Ethereum Classic', 'primary' => '#627eea', 'secondary' => '#454a75', 'bg' => '#14141a', 'text' => '#f0f0f5', 'muted' => '#838a9b'),
                                    'dracula' => array('name' => 'Dracula Void', 'primary' => '#ff79c6', 'secondary' => '#bd93f9', 'bg' => '#282a36', 'text' => '#f8f8f2', 'muted' => '#6272a4'),
                                    'synthwave' => array('name' => 'Synthwave \'84', 'primary' => '#f92aad', 'secondary' => '#2de2e6', 'bg' => '#1e1e2e', 'text' => '#f1f1f1', 'muted' => '#7d82a8'),
                                    'nord' => array('name' => 'Nordic Ice', 'primary' => '#88c0d0', 'secondary' => '#5e81ac', 'bg' => '#2e3440', 'text' => '#eceff4', 'muted' => '#4c566a'),
                                    'monokai' => array('name' => 'Monokai Pro', 'primary' => '#a6e22e', 'secondary' => '#fd971f', 'bg' => '#272822', 'text' => '#f8f8f2', 'muted' => '#75715e'),
                                    'gruvbox' => array('name' => 'Gruvbox Dark', 'primary' => '#fabd2f', 'secondary' => '#fe8019', 'bg' => '#282828', 'text' => '#ebdbb2', 'muted' => '#928374'),
                                    'outrun' => array('name' => 'Neon Outrun', 'primary' => '#ff0055', 'secondary' => '#00f0ff', 'bg' => '#0a0a14', 'text' => '#ffffff', 'muted' => '#aa88bb'),
                                    'lavender' => array('name' => 'Lavender Mist', 'primary' => '#cba6f7', 'secondary' => '#b4befe', 'bg' => '#1e1e2e', 'text' => '#cdd6f4', 'muted' => '#7f849c'),
                                    'obsidian' => array('name' => 'Obsidian Black', 'primary' => '#8c8c8c', 'secondary' => '#595959', 'bg' => '#000000', 'text' => '#e6e6e6', 'muted' => '#4d4d4d'),
                                    'toxic' => array('name' => 'Toxic Waste', 'primary' => '#ccff00', 'secondary' => '#99cc00', 'bg' => '#0d120a', 'text' => '#ffffff', 'muted' => '#6b8a51'),
                                    'ghost' => array('name' => 'Ghost White', 'primary' => '#ffffff', 'secondary' => '#cccccc', 'bg' => '#111111', 'text' => '#f8f8f8', 'muted' => '#666666'),
                                    'royal_purple' => array('name' => 'Royal Purple', 'primary' => '#9b59b6', 'secondary' => '#8e44ad', 'bg' => '#120a1a', 'text' => '#f1f1f1', 'muted' => '#735a82'),
                                    'holographic' => array('name' => 'Holographic', 'primary' => '#ff00ff', 'secondary' => '#00ffff', 'bg' => '#0c0c12', 'text' => '#ffffff', 'muted' => '#8b8b99'),
                                    'blood_orange' => array('name' => 'Blood Orange', 'primary' => '#ff4500', 'secondary' => '#cc3700', 'bg' => '#120500', 'text' => '#ffebe6', 'muted' => '#995945')
                                );

                                $current_primary = strtolower(xepmarket2_get_option_fast('xepmarket2_color_primary', '#00f2ff'));
                                $current_bg = strtolower(xepmarket2_get_option_fast('xepmarket2_color_bg', '#05060a'));

                                foreach ($presets as $id => $p):
                                    $is_active = (strtolower($p['primary']) === $current_primary && strtolower($p['bg']) === $current_bg);
                                    $border_color = $is_active ? $p['primary'] : 'var(--admin-border)';
                                    $border_width = $is_active ? '2px' : '1px';
                                    $box_shadow = $is_active ? '0 0 15px ' . $p['primary'] . '40' : 'none';
                                    $check_display = $is_active ? 'block' : 'none';
                                    ?>
                                    <div class="xep-preset-item"
                                        onclick="applyXepPreset(this, '<?php echo $p['primary']; ?>', '<?php echo $p['secondary']; ?>', '<?php echo $p['bg']; ?>', '<?php echo $p['text']; ?>', '<?php echo $p['muted']; ?>')"
                                        style="cursor: pointer; background: <?php echo $p['bg']; ?>; border: <?php echo $border_width; ?> solid <?php echo $border_color; ?>; box-shadow: <?php echo $box_shadow; ?>; border-radius: 12px; padding: 15px; position: relative; transition: all 0.3s; height: 100px; display: flex; flex-direction: column; justify-content: space-between;">

                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <div style="font-size: 12px; font-weight: 700; color: <?php echo $p['text']; ?>;">
                                                <?php echo $p['name']; ?>
                                            </div>
                                            <div class="xep-preset-check"
                                                style="color: <?php echo $p['primary']; ?>; display: <?php echo $check_display; ?>;">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </div>

                                        <div style="display: flex; gap: 5px;">
                                            <div
                                                style="width: 20px; height: 20px; border-radius: 4px; background: <?php echo $p['primary']; ?>; box-shadow: 0 0 10px <?php echo $p['primary']; ?>;">
                                            </div>
                                            <div
                                                style="width: 20px; height: 20px; border-radius: 4px; background: <?php echo $p['secondary']; ?>;">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <script>
                                function applyXepPreset(element, primary, secondary, bg, text, muted) {
                                    document.querySelector('input[name="xepmarket2_color_primary"]').value = primary;
                                    document.querySelector('input[name="xepmarket2_color_secondary"]').value = secondary;
                                    document.querySelector('input[name="xepmarket2_color_bg"]').value = bg;
                                    document.querySelector('input[name="xepmarket2_color_text"]').value = text;
                                    document.querySelector('input[name="xepmarket2_color_text_muted"]').value = muted;

                                    // Visual feedback
                                    const presetItems = document.querySelectorAll('.xep-preset-item');
                                    presetItems.forEach(item => {
                                        item.style.borderColor = 'var(--admin-border)';
                                        item.style.borderWidth = '1px';
                                        item.style.boxShadow = 'none';
                                        const check = item.querySelector('.xep-preset-check');
                                        if (check) check.style.display = 'none';
                                    });

                                    element.style.borderColor = primary;
                                    element.style.borderWidth = '2px';
                                    element.style.boxShadow = `0 0 15px ${primary}40`;
                                    const elCheck = element.querySelector('.xep-preset-check');
                                    if (elCheck) {
                                        elCheck.style.display = 'block';
                                        elCheck.style.color = primary;
                                    }
                                }
                            </script>

                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>Primary Brand Color</label>
                                    <input type="color" name="xepmarket2_color_primary"
                                        value="<?php echo esc_attr(xepmarket2_get_option_fast('xepmarket2_color_primary', '#00f2ff')); ?>"
                                        style="height: 50px;" />
                                    <p class="description">Used for buttons, icons, accents, and links.</p>
                                </div>
                                <div class="xep-form-group">
                                    <label>Secondary Accent Color</label>
                                    <input type="color" name="xepmarket2_color_secondary"
                                        value="<?php echo esc_attr(xepmarket2_get_option_fast('xepmarket2_color_secondary', '#7000ff')); ?>"
                                        style="height: 50px;" />
                                    <p class="description">Used for gradients and secondary highlights.</p>
                                </div>
                            </div>

                            <div class="xep-form-group" style="margin-top: 20px;">
                                <label>Site Background Color</label>
                                <input type="color" name="xepmarket2_color_bg"
                                    value="<?php echo esc_attr(xepmarket2_get_option_fast('xepmarket2_color_bg', '#05060a')); ?>"
                                    style="height: 50px;" />
                                <p class="description">The main background color for the entire site.</p>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--admin-border); margin: 25px 0;">

                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>Main Text Color</label>
                                    <input type="color" name="xepmarket2_color_text"
                                        value="<?php echo esc_attr(xepmarket2_get_option_fast('xepmarket2_color_text', '#ffffff')); ?>"
                                        style="height: 50px;" />
                                </div>
                                <div class="xep-form-group">
                                    <label>Muted Text Color</label>
                                    <input type="color" name="xepmarket2_color_text_muted"
                                        value="<?php echo esc_attr(xepmarket2_get_option_fast('xepmarket2_color_text_muted', '#a0a0b8')); ?>"
                                        style="height: 50px;" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: General -->
                    <div id="tab-general" class="xep-tab-content active">
                        <div class="xep-section-card">
                            <h3>Brand Identity & Logos</h3>
                            <div class="xep-form-group">
                                <label>Favicon URL</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" name="xepmarket2_favicon" id="xep_favicon_url"
                                        value="<?php echo esc_attr(get_option('xepmarket2_favicon')); ?>"
                                        style="flex: 1;" />
                                    <button type="button" class="xep-browse-btn" data-target="xep_favicon_url"
                                        style="padding: 10px 20px; font-size: 13px; width: auto; background: var(--admin-surface); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; cursor: pointer;">Browse</button>
                                </div>
                                <p class="description">Select or upload your site favicon.</p>
                            </div>

                            <div class="xep-form-group">
                                <label>Admin Panel Header Banner</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" name="xepmarket2_admin_banner_img" id="xep_admin_banner_img"
                                        value="<?php echo esc_attr(get_option('xepmarket2_admin_banner_img')); ?>"
                                        style="flex: 1;" />
                                    <button type="button" class="xep-browse-btn" data-target="xep_admin_banner_img"
                                        style="padding: 10px 20px; font-size: 13px; width: auto; background: var(--admin-surface); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; cursor: pointer;">Browse</button>
                                </div>
                                <p class="description">Upload a banner image (recommended: 1200x200px) that will appear at
                                    the top of this settings page.</p>
                                <?php if ($admin_banner_prev = get_option('xepmarket2_admin_banner_img')): ?>
                                    <div class="xep-image-preview"
                                        style="margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid var(--admin-border); background: #000; max-width: 400px;">
                                        <img src="<?php echo esc_url($admin_banner_prev); ?>"
                                            style="width: 100%; display: block;" id="preview_xep_admin_banner_img" />
                                    </div>
                                <?php endif; ?>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--admin-border); margin: 25px 0;">

                            <div class="xep-grid-2">
                                <!-- Header Logo -->
                                <div>
                                    <h4 style="color: var(--admin-primary); margin-bottom: 20px;">Header Logo</h4>
                                    <div class="xep-form-group">
                                        <label>Logo Type</label>
                                        <select name="xepmarket2_header_logo_type">
                                            <option value="text" <?php selected('text', get_option('xepmarket2_header_logo_type')); ?>>Text Only</option>
                                            <option value="image" <?php selected('image', get_option('xepmarket2_header_logo_type')); ?>>Image Only</option>
                                        </select>
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Logo First Part (e.g. XEP)</label>
                                        <input type="text" name="xepmarket2_header_logo_text_1"
                                            value="<?php echo esc_attr(get_option('xepmarket2_header_logo_text_1', 'XEP')); ?>" />
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Logo Accent Part (e.g. MARKET)</label>
                                        <input type="text" name="xepmarket2_header_logo_text_2"
                                            value="<?php echo esc_attr(get_option('xepmarket2_header_logo_text_2', 'MARKET')); ?>" />
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Header Logo Image URL</label>
                                        <div style="display: flex; gap: 10px;">
                                            <input type="text" name="xepmarket2_header_logo_img" id="xep_header_logo_img"
                                                value="<?php echo esc_attr(get_option('xepmarket2_header_logo_img')); ?>"
                                                style="flex: 1;" />
                                            <button type="button" class="xep-browse-btn" data-target="xep_header_logo_img"
                                                style="padding: 10px 20px; font-size: 13px; width: auto; background: var(--admin-surface); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; cursor: pointer;">Browse</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Logo -->
                                <div style="border-left: 1px solid var(--admin-border); padding-left: 20px;">
                                    <h4 style="color: var(--admin-primary); margin-bottom: 20px;">Footer Logo</h4>
                                    <div class="xep-form-group">
                                        <label>Logo Type</label>
                                        <select name="xepmarket2_footer_logo_type">
                                            <option value="text" <?php selected('text', get_option('xepmarket2_footer_logo_type')); ?>>Text Only</option>
                                            <option value="image" <?php selected('image', get_option('xepmarket2_footer_logo_type')); ?>>Image Only</option>
                                        </select>
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Logo First Part</label>
                                        <input type="text" name="xepmarket2_footer_logo_text_1"
                                            value="<?php echo esc_attr(get_option('xepmarket2_footer_logo_text_1', 'XEP')); ?>" />
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Logo Accent Part</label>
                                        <input type="text" name="xepmarket2_footer_logo_text_2"
                                            value="<?php echo esc_attr(get_option('xepmarket2_footer_logo_text_2', 'MARKET')); ?>" />
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Footer Logo Image URL</label>
                                        <div style="display: flex; gap: 10px;">
                                            <input type="text" name="xepmarket2_footer_logo_img" id="xep_footer_logo_img"
                                                value="<?php echo esc_attr(get_option('xepmarket2_footer_logo_img')); ?>"
                                                style="flex: 1;" />
                                            <button type="button" class="xep-browse-btn" data-target="xep_footer_logo_img"
                                                style="padding: 10px 20px; font-size: 13px; width: auto; background: var(--admin-surface); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; cursor: pointer;">Browse</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--admin-border); margin: 25px 0;">

                            <h4 style="color: var(--admin-primary); margin-bottom: 20px;">Social Media Presence</h4>
                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label><i class="fab fa-telegram-plane"></i> Telegram Channel URL</label>
                                    <input type="text" name="xepmarket2_social_telegram"
                                        value="<?php echo esc_attr(get_option('xepmarket2_social_telegram', 'https://t.me/xepmarket_official')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label><i class="fab fa-discord"></i> Discord Server URL</label>
                                    <input type="text" name="xepmarket2_social_discord"
                                        value="<?php echo esc_attr(get_option('xepmarket2_social_discord')); ?>"
                                        placeholder="https://discord.gg/..." />
                                </div>
                                <div class="xep-form-group">
                                    <label><i class="fab fa-twitter"></i> X / Twitter URL</label>
                                    <input type="text" name="xepmarket2_social_twitter"
                                        value="<?php echo esc_attr(get_option('xepmarket2_social_twitter')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label><i class="fab fa-instagram"></i> Instagram URL</label>
                                    <input type="text" name="xepmarket2_social_instagram"
                                        value="<?php echo esc_attr(get_option('xepmarket2_social_instagram')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label><i class="fab fa-youtube"></i> YouTube URL</label>
                                    <input type="text" name="xepmarket2_social_youtube"
                                        value="<?php echo esc_attr(get_option('xepmarket2_social_youtube')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label><i class="fab fa-pinterest"></i> Pinterest URL</label>
                                    <input type="text" name="xepmarket2_social_pinterest"
                                        value="<?php echo esc_attr(get_option('xepmarket2_social_pinterest')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label><i class="fab fa-tiktok"></i> TikTok URL</label>
                                    <input type="text" name="xepmarket2_social_tiktok"
                                        value="<?php echo esc_attr(get_option('xepmarket2_social_tiktok')); ?>" />
                                </div>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--admin-border); margin: 25px 0;">

                            <h4 style="color: var(--admin-primary); margin-bottom: 20px;">Payment Methods (Footer Badges)
                            </h4>
                            <div class="xep-grid-2">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <div class="xep-form-group"
                                        style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 12px; border: 1px solid var(--admin-border);">
                                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                                            <div style="flex: 2;">
                                                <label>Token Name (e.g. MMX)</label>
                                                <input type="text" name="xepmarket2_token_name_<?php echo $i; ?>"
                                                    value="<?php echo esc_attr(get_option('xepmarket2_token_name_' . $i)); ?>" />
                                            </div>
                                            <div style="flex: 1;">
                                                <label>Status</label>
                                                <select name="xepmarket2_token_status_<?php echo $i; ?>">
                                                    <option value="hidden" <?php selected('hidden', get_option('xepmarket2_token_status_' . $i)); ?>>Hidden</option>
                                                    <option value="live" <?php selected('live', get_option('xepmarket2_token_status_' . $i)); ?>>LIVE</option>
                                                    <option value="soon" <?php selected('soon', get_option('xepmarket2_token_status_' . $i)); ?>>COMING SOON</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="xep-section-card">
                            <h3>Header Announcement Banner</h3>
                            <div class="xep-form-group">
                                <label>Main Announcement Text</label>
                                <input type="text" name="xepmarket2_banner_main"
                                    value="<?php echo esc_attr(get_option('xepmarket2_banner_main', 'Welcome to the future of retail! Secure your Crypto Gear today. Pay exclusively with XEP.')); ?>" />
                                <p class="description">Displays on the very top of the site.</p>
                            </div>
                            <div class="xep-form-group">
                                <label>Promo/Discount Highlights</label>
                                <input type="text" name="xepmarket2_banner_discount"
                                    value="<?php echo esc_attr(get_option('xepmarket2_banner_discount', '🚀 Limited Time Offer: Up to 50% OFF! 🚀')); ?>" />
                                <p class="description">Bold text after the separator.</p>
                            </div>
                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>Banner Background Color</label>
                                    <input type="color" name="xepmarket2_banner_bg"
                                        value="<?php echo esc_attr(get_option('xepmarket2_banner_bg', '#00f2ff')); ?>"
                                        style="height: 50px;" />
                                </div>
                                <div class="xep-form-group">
                                    <label>Banner Text Color</label>
                                    <input type="color" name="xepmarket2_banner_text_color"
                                        value="<?php echo esc_attr(get_option('xepmarket2_banner_text_color', '#000000')); ?>"
                                        style="height: 50px;" />
                                </div>
                            </div>
                            <div class="xep-form-group">
                                <label>Banner Visibility</label>
                                <select name="xepmarket2_banner_visibility">
                                    <option value="both" <?php selected('both', get_option('xepmarket2_banner_visibility', 'both')); ?>>Display on Both Web & Mobile</option>
                                    <option value="web" <?php selected('web', get_option('xepmarket2_banner_visibility')); ?>>Only Web (Desktop)</option>
                                    <option value="mobile" <?php selected('mobile', get_option('xepmarket2_banner_visibility')); ?>>Only Mobile</option>
                                    <option value="hidden" <?php selected('hidden', get_option('xepmarket2_banner_visibility')); ?>>Hidden (Disable Everywhere)</option>
                                </select>
                                <p class="description">Select where the announcement banner should be visible.</p>
                            </div>
                        </div>

                        <div class="xep-section-card">
                            <h3>Footer Information</h3>
                            <div class="xep-form-group">
                                <label>Footer Description Text</label>
                                <textarea name="xepmarket2_footer_desc"
                                    rows="4"><?php echo esc_textarea(get_option('xepmarket2_footer_desc', 'Your premium destination for Web3 lifestyle gear. From hardware security to exclusive crypto apparel, we bring the blockchain to your doorstep.')); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Hero & Slider -->
                    <div id="tab-hero" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Main Hero Interaction</h3>
                            <div class="xep-form-group" style="display: flex; align-items: center; gap: 15px;">
                                <label class="xep-switch">
                                    <input type="checkbox" name="xepmarket2_slider_enable" value="1" <?php checked(1, get_option('xepmarket2_slider_enable'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                                <span>Enable Animated Hero Slider</span>
                            </div>
                            <p class="description">If enabled, the 3-slide slider will be used. If disabled, a static hero
                                section is shown.</p>
                        </div>

                        <!-- Static Hero Region -->
                        <div class="static-hero-group"
                            style="<?php echo get_option('xepmarket2_slider_enable') ? 'display:none;' : ''; ?>">
                            <div class="xep-section-card">
                                <h3>Static Hero Section Settings</h3>
                                <div class="xep-form-group">
                                    <label>Hero Title (HTML Allowed)</label>
                                    <input type="text" name="xepmarket2_hero_title"
                                        value="<?php echo esc_attr(get_option('xepmarket2_hero_title', 'Elevate Your <br><span class="logo-accent">Crypto Lifestyle</span>')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label>Hero Subtitle</label>
                                    <textarea name="xepmarket2_hero_subtitle"
                                        rows="3"><?php echo esc_textarea(get_option('xepmarket2_hero_subtitle', 'Premium hardware wallets, exclusive crypto apparel, and unique digital collectibles. Secure your assets and show your passion. Pay exclusively with XEP for secure and instant delivery.')); ?></textarea>
                                </div>
                                <div class="xep-grid-2">
                                    <div class="xep-form-group">
                                        <label>Button 1 Text</label>
                                        <input type="text" name="xepmarket2_hero_btn1_text"
                                            value="<?php echo esc_attr(get_option('xepmarket2_hero_btn1_text', 'Shop Crypto Gear')); ?>" />
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Button 1 Link</label>
                                        <input type="text" name="xepmarket2_hero_btn1_link"
                                            value="<?php echo esc_attr(get_option('xepmarket2_hero_btn1_link', '/shop')); ?>" />
                                    </div>
                                </div>
                                <div class="xep-grid-2">
                                    <div class="xep-form-group">
                                        <label>Button 2 Text</label>
                                        <input type="text" name="xepmarket2_hero_btn2_text"
                                            value="<?php echo esc_attr(get_option('xepmarket2_hero_btn2_text', 'Join Community')); ?>" />
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Button 2 Link</label>
                                        <input type="text" name="xepmarket2_hero_btn2_link"
                                            value="<?php echo esc_attr(get_option('xepmarket2_hero_btn2_link', 'https://t.me/xepmarket_official')); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Slider Region -->
                        <div class="slider-settings-group"
                            style="<?php echo !get_option('xepmarket2_slider_enable') ? 'display:none;' : ''; ?>">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <div class="xep-section-card">
                                    <h3>Slide #<?php echo $i; ?> Configuration</h3>
                                    <div class="xep-form-group">
                                        <label>Title</label>
                                        <input type="text" name="xepmarket2_slider_title_<?php echo $i; ?>"
                                            value="<?php echo esc_attr(get_option('xepmarket2_slider_title_' . $i)); ?>" />
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Subtitle / Description</label>
                                        <textarea name="xepmarket2_slider_desc_<?php echo $i; ?>"
                                            rows="2"><?php echo esc_textarea(get_option('xepmarket2_slider_desc_' . $i)); ?></textarea>
                                    </div>
                                    <div class="xep-grid-2">
                                        <div class="xep-form-group">
                                            <label>Button Text</label>
                                            <input type="text" name="xepmarket2_slider_btn_text_<?php echo $i; ?>"
                                                value="<?php echo esc_attr(get_option('xepmarket2_slider_btn_text_' . $i)); ?>" />
                                        </div>
                                        <div class="xep-form-group">
                                            <label>Button Link</label>
                                            <input type="text" name="xepmarket2_slider_btn_link_<?php echo $i; ?>"
                                                value="<?php echo esc_attr(get_option('xepmarket2_slider_btn_link_' . $i)); ?>" />
                                        </div>
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Background Image</label>
                                        <div style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px;">
                                            <div style="flex: 1;">
                                                <input type="text" name="xepmarket2_slider_img_<?php echo $i; ?>"
                                                    id="xep_slider_img_<?php echo $i; ?>"
                                                    value="<?php echo esc_attr(get_option('xepmarket2_slider_img_' . $i)); ?>" />
                                            </div>
                                            <button type="button" class="xep-browse-btn"
                                                data-target="xep_slider_img_<?php echo $i; ?>"
                                                style="padding: 10px 20px; font-size: 13px; width: auto; background: var(--admin-surface); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; cursor: pointer;">Browse</button>
                                        </div>
                                        <?php if ($img = get_option('xepmarket2_slider_img_' . $i)): ?>
                                            <div class="xep-image-preview"
                                                style="margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid var(--admin-border); background: #000; max-width: 300px;">
                                                <img src="<?php echo esc_url($img); ?>" style="width: 100%; display: block;"
                                                    id="preview_xep_slider_img_<?php echo $i; ?>" />
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Tab: Sections -->
                    <div id="tab-sections" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Highlights Grid (4 Cards)</h3>
                            <?php
                            $default_icons = ['dashicons-location-alt', 'dashicons-admin-site-alt3', 'dashicons-thumbs-up', 'dashicons-tag'];
                            $default_titles = ['FREE SHIPPING', 'WORLDWIDE DELIVERY', '100% SATISFACTION', 'UP TO 80% OFF'];
                            $default_descs = ['On all orders across the store', 'Shipping to every corner of the world', 'Guaranteed customer happiness', 'Massive sale on innovative tech'];

                            for ($i = 1; $i <= 4; $i++): ?>
                                <div
                                    style="padding: 15px; background: rgba(0,0,0,0.1); border-radius: 10px; margin-bottom: 15px;">
                                    <div class="xep-grid-2">
                                        <div class="xep-form-group">
                                            <label>Icon (Dashicon Class) #<?php echo $i; ?></label>
                                            <input type="text" name="xepmarket2_highlight_icon_<?php echo $i; ?>"
                                                value="<?php echo esc_attr(get_option('xepmarket2_highlight_icon_' . $i, $default_icons[$i - 1])); ?>" />
                                        </div>
                                        <div class="xep-form-group">
                                            <label>Title #<?php echo $i; ?></label>
                                            <input type="text" name="xepmarket2_highlight_title_<?php echo $i; ?>"
                                                value="<?php echo esc_attr(get_option('xepmarket2_highlight_title_' . $i, $default_titles[$i - 1])); ?>" />
                                        </div>
                                    </div>
                                    <div class="xep-form-group">
                                        <label>Description #<?php echo $i; ?></label>
                                        <input type="text" name="xepmarket2_highlight_desc_<?php echo $i; ?>"
                                            value="<?php echo esc_attr(get_option('xepmarket2_highlight_desc_' . $i, $default_descs[$i - 1])); ?>"
                                            style="width: 100%;" />
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="xep-section-card">
                            <h3>Flash Deals (Sale Products Slider)</h3>
                            <div class="xep-form-group"
                                style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--admin-border); padding-bottom: 20px; margin-bottom: 20px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">Enable Flash Deals</div>
                                    <div class="description">Show the on-sale products slider above Trending Gear.</div>
                                </div>
                                <label class="xep-switch">
                                    <input type="checkbox" name="xepmarket2_flash_deals_enable" value="1" <?php checked(1, get_option('xepmarket2_flash_deals_enable', '1'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>
                            <div class="xep-form-group">
                                <label>Section Title (HTML Allowed)</label>
                                <input type="text" name="xepmarket2_flash_deals_title"
                                    value="<?php echo esc_attr(get_option('xepmarket2_flash_deals_title', 'Flash <span class="logo-accent">Deals</span>')); ?>" />
                            </div>
                            <div class="xep-form-group">
                                <label>Section Subtitle</label>
                                <input type="text" name="xepmarket2_flash_deals_subtitle"
                                    value="<?php echo esc_attr(get_option('xepmarket2_flash_deals_subtitle', 'Exclusive discounts on premium gear.')); ?>" />
                            </div>
                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>Total Products to Show</label>
                                    <input type="number" name="xepmarket2_flash_deals_limit"
                                        value="<?php echo esc_attr(get_option('xepmarket2_flash_deals_limit', '12')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label>Columns</label>
                                    <input type="number" name="xepmarket2_flash_deals_columns"
                                        value="<?php echo esc_attr(get_option('xepmarket2_flash_deals_columns', '4')); ?>" />
                                </div>
                            </div>
                        </div>

                        <div class="xep-section-card">
                            <h3>Trending Gear (Products)</h3>
                            <div class="xep-form-group">
                                <label>Section Title</label>
                                <input type="text" name="xepmarket2_featured_title"
                                    value="<?php echo esc_attr(get_option('xepmarket2_featured_title', 'Trending Gear')); ?>" />
                            </div>
                            <div class="xep-form-group">
                                <label>Section Subtitle</label>
                                <input type="text" name="xepmarket2_featured_subtitle"
                                    value="<?php echo esc_attr(get_option('xepmarket2_featured_subtitle', 'Must-have items for every Web3 enthusiast')); ?>" />
                            </div>
                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>Total Products to Show</label>
                                    <input type="number" name="xepmarket2_featured_limit"
                                        value="<?php echo esc_attr(get_option('xepmarket2_featured_limit', '20')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label>Columns</label>
                                    <input type="number" name="xepmarket2_featured_columns"
                                        value="<?php echo esc_attr(get_option('xepmarket2_featured_columns', '4')); ?>" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Modules -->
                    <div id="tab-modules" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Functional Modules</h3>
                            <p class="description" style="margin-bottom: 25px;">Activate or deactivate advanced features of
                                the XEPMARKET-ALFA theme.</p>

                            <div class="xep-form-group"
                                style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--admin-border); padding-bottom: 20px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">Restriction: Only OmniXEP Gateway</div>
                                    <div class="description">Force the store to only show OmniXEP payment method at
                                        checkout.</div>
                                </div>
                                <label class="xep-switch">
                                    <input type="checkbox" name="xepmarket2_mod_omnixep_restrict" value="1" <?php checked(1, get_option('xepmarket2_mod_omnixep_restrict', '1'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>

                            <div class="xep-form-group"
                                style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--admin-border); padding-bottom: 20px; margin-top: 20px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">Simplified Checkout Fields</div>
                                    <div class="description">Optimize checkout fields for digital/crypto gear (adds Telegram
                                        field).</div>
                                </div>
                                <label class="xep-switch">
                                    <input type="checkbox" name="xepmarket2_mod_custom_checkout" value="1" <?php checked(1, get_option('xepmarket2_mod_custom_checkout', '1'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>

                            <div class="xep-form-group"
                                style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--admin-border); padding-bottom: 20px; margin-top: 20px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">Modern Sale Badges</div>
                                    <div class="description">Show percentage discount badges on products instead of standard
                                        "Sale!".</div>
                                </div>
                                <label class="xep-switch">
                                    <input type="checkbox" name="xepmarket2_mod_sale_badges" value="1" <?php checked(1, get_option('xepmarket2_mod_sale_badges', '1'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>

                            <div class="xep-form-group"
                                style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 20px; margin-top: 20px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">Modern Breadcrumbs</div>
                                    <div class="description">Enable premium styling for WooCommerce breadcrumbs.</div>
                                </div>
                                <label class="xep-switch">
                                    <input type="checkbox" name="xepmarket2_mod_breadcrumb_modern" value="1" <?php checked(1, get_option('xepmarket2_mod_breadcrumb_modern', '1'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="xep-section-card" style="margin-top: 30px;">
                            <h3 style="color: var(--admin-primary);"><i class="fas fa-cubes"></i> System Ecosystem & Plugins
                            </h3>
                            <p class="description" style="margin-bottom: 25px;">Live status of all modules integrated with
                                the XEPMARKET-ALFA premium ecosystem.</p>

                            <?php
                            $required_plugins = [
                                ['name' => 'WooCommerce', 'slug' => 'woocommerce', 'path' => 'woocommerce/woocommerce.php', 'required' => true, 'icon' => 'fa-shopping-cart'],
                                ['name' => 'OmniXEP Gateway', 'slug' => 'omnixep-woocommerce', 'path' => 'omnixep-woocommerce/omnixep-woocommerce.php', 'required' => true, 'icon' => 'fa-wallet'],
                                ['name' => 'XEP Market Affiliate', 'slug' => 'omnixep-affiliate', 'path' => 'omnixep-affiliate/omnixep-affiliate.php', 'required' => false, 'icon' => 'fa-handshake'],
                                ['name' => 'XEP Market Telegram Bot', 'slug' => 'xepmarket-telegram-bot', 'path' => 'xepmarket-telegram-bot/xepmarket-telegram-bot.php', 'required' => false, 'icon' => 'fa-robot'],
                                ['name' => 'AliSync Helper', 'slug' => 'ali-sync-helper', 'required' => false, 'icon' => 'fa-sync-alt', 'type' => 'core'],
                                ['name' => 'AliDropship', 'slug' => 'woo-alidropship', 'path' => 'woo-alidropship/woo-alidropship.php', 'required' => false, 'icon' => 'fa-ship'],
                                ['name' => 'Product Variations Swatches', 'slug' => 'product-variations-swatches-for-woocommerce', 'path' => 'product-variations-swatches-for-woocommerce/product-variations-swatches-for-woocommerce.php', 'required' => false, 'icon' => 'fa-palette'],
                                ['name' => 'Additional Variation Gallery', 'slug' => 'vargal-additional-variation-gallery-for-woo', 'path' => 'vargal-additional-variation-gallery-for-woo/vargal-additional-variation-gallery-for-woo.php', 'required' => false, 'icon' => 'fa-images'],
                                ['name' => 'Orders Tracking', 'slug' => 'woo-orders-tracking', 'path' => 'woo-orders-tracking/woo-orders-tracking.php', 'required' => false, 'icon' => 'fa-truck-fast'],
                                ['name' => 'WP Mail SMTP', 'slug' => 'wp-mail-smtp', 'path' => 'wp-mail-smtp/wp-mail-smtp.php', 'required' => false, 'icon' => 'fa-paper-plane'],
                            ];

                            if (!function_exists('is_plugin_active')) {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }

                            $all_plugins = get_plugins();

                            foreach ($required_plugins as $plug):
                                if (isset($plug['type']) && $plug['type'] === 'core') {
                                    $is_installed = true;
                                    $is_active = true;
                                } else {
                                    $is_installed = false;
                                    $is_active = false;
                                    $plugin_path = isset($plug['path']) ? $plug['path'] : '';

                                    // Check by explicit path first
                                    if ($plugin_path && array_key_exists($plugin_path, $all_plugins)) {
                                        $is_installed = true;
                                        $is_active = is_plugin_active($plugin_path);
                                    } else {
                                        // Backup: Match by folder (slug) prefix
                                        foreach ($all_plugins as $path => $data) {
                                            if (strpos($path, $plug['slug'] . '/') === 0) {
                                                $is_installed = true;
                                                $plugin_path = $path;
                                                $is_active = is_plugin_active($path);
                                                break;
                                            }
                                        }
                                    }

                                    if (!$is_installed && file_exists(WP_PLUGIN_DIR . '/' . $plug['slug'])) {
                                        $is_installed = true;
                                    }
                                }
                                ?>
                                <div class="xep-form-group"
                                    style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); padding: 15px 20px; border-radius: 12px; border: 1px solid var(--admin-border); margin-bottom: 12px; transition: all 0.3s ease;">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <div
                                            style="width: 40px; height: 40px; background: rgba(0, 242, 255, 0.05); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--admin-primary); font-size: 18px;">
                                            <i class="fas <?php echo esc_attr($plug['icon']); ?>"></i>
                                        </div>
                                        <div>
                                            <div
                                                style="font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                                                <?php echo esc_html($plug['name']); ?>
                                                <?php if (isset($plug['required']) && $plug['required']): ?>
                                                    <span
                                                        style="font-size: 9px; background: rgba(255, 69, 58, 0.1); color: #ff453a; padding: 1px 6px; border-radius: 4px; border: 1px solid rgba(255, 69, 58, 0.2); letter-spacing: 0.5px;">REQUIRED</span>
                                                <?php endif; ?>
                                                <?php if (isset($plug['type']) && $plug['type'] === 'core'): ?>
                                                    <span
                                                        style="font-size: 9px; background: rgba(0, 242, 255, 0.1); color: var(--admin-primary); padding: 1px 6px; border-radius: 4px; border: 1px solid rgba(0, 242, 255, 0.2); letter-spacing: 0.5px;">CORE</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="status-indicator"
                                                style="margin-top: 4px; font-size: 12px; opacity: 0.8;">
                                                <?php if ($is_active): ?>
                                                    <span style="color: #32d74b;"><i class="fas fa-circle-check"></i>
                                                        Activated</span>
                                                <?php elseif ($is_installed): ?>
                                                    <span style="color: #ff9f0a;"><i class="fas fa-clock"></i> Inactive</span>
                                                <?php else: ?>
                                                    <span style="color: #ff453a;"><i class="fas fa-circle-xmark"></i> Missing</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="plugin-action">
                                        <?php if (isset($plug['type']) && $plug['type'] === 'core'): ?>
                                            <a href="<?php echo admin_url('admin.php?page=ali-sync-helper'); ?>"
                                                class="xep-save-btn"
                                                style="padding: 6px 15px !important; font-size: 11px !important; width: auto !important; background: linear-gradient(135deg, #6366f1, #a855f7) !important; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2) !important;">DASHBOARD</a>
                                        <?php elseif ($is_active): ?>
                                            <i class="fas fa-shield-check"
                                                style="color: #32d74b; font-size: 20px; opacity: 0.5;"></i>
                                        <?php elseif ($is_installed): ?>
                                            <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . $plug['path']), 'activate-plugin_' . $plug['path']); ?>"
                                                class="xep-save-btn"
                                                style="padding: 6px 15px !important; font-size: 11px !important; width: auto !important; background: linear-gradient(135deg, #ff9f0a, #d35400) !important; box-shadow: 0 4px 10px rgba(211, 84, 0, 0.2) !important;">ACTIVATE</a>
                                        <?php else: ?>
                                            <a href="<?php echo admin_url('themes.php?page=tgmpa-install-plugins'); ?>"
                                                class="xep-save-btn"
                                                style="padding: 6px 15px !important; font-size: 11px !important; width: auto !important;">INSTALL</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tab: Checkout Customization -->
                    <div id="tab-checkout" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Checkout Field Settings</h3>
                            <p class="description" style="margin-bottom: 25px;">Enable or disable specific billing and
                                shipping fields required from customers during checkout.</p>

                            <?php
                            $chk_fields_config = [
                                'first_name' => ['label' => 'First Name', 'default' => '1', 'order' => '1'],
                                'last_name' => ['label' => 'Last Name', 'default' => '1', 'order' => '1'],
                                'company' => ['label' => 'Company', 'default' => '0', 'order' => '2'],
                                'country' => ['label' => 'Country', 'default' => '1', 'order' => '3'],
                                'address_1' => ['label' => 'Address Line 1', 'default' => '1', 'order' => '4'],
                                'address_2' => ['label' => 'Address Line 2', 'default' => '0', 'order' => '5'],
                                'city' => ['label' => 'City', 'default' => '1', 'order' => '6'],
                                'state' => ['label' => 'State / County', 'default' => '1', 'order' => '6'],
                                'postcode' => ['label' => 'Postcode / ZIP', 'default' => '1', 'order' => '7'],
                                'phone' => ['label' => 'Phone Number', 'default' => '1', 'order' => '8'],
                                'email' => ['label' => 'Email Address', 'default' => '1', 'order' => '9'],
                                'telegram' => ['label' => 'Telegram Username', 'default' => '1', 'order' => '8']
                            ];

                            // Sort fields by saved order for initial display
                            $sorted_fields = [];
                            foreach ($chk_fields_config as $fid => $fdata) {
                                $saved_order = intval(get_option('xepmarket2_chk_order_' . $fid, $fdata['order']));
                                $sorted_fields[$fid] = array_merge($fdata, ['saved_order' => $saved_order]);
                            }
                            uasort($sorted_fields, function ($a, $b) {
                                return $a['saved_order'] - $b['saved_order'];
                            });
                            ?>

                            <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;"><i
                                    class="fas fa-info-circle"></i> Drag fields to reorder or set order numbers manually.
                                <strong>Same number = side by side.</strong>
                            </p>

                            <div id="xep_default_fields_sortable" style="display:flex;flex-wrap:wrap;gap:8px;">
                                <?php foreach ($sorted_fields as $field_id => $field_data): ?>
                                    <div class="xep-sortable-row" data-field-id="<?php echo esc_attr($field_id); ?>"
                                        draggable="true"
                                        style="display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--admin-border); padding: 14px 16px; border-radius: 10px; background: rgba(255,255,255,0.02); cursor: grab; transition: all 0.2s ease; width: 100%; box-sizing: border-box;">

                                        <!-- Drag Handle -->
                                        <div class="xep-drag-handle"
                                            style="margin-right: 15px; color: var(--text-muted); font-size: 18px; opacity: 0.4; cursor: grab;"
                                            title="Drag to reorder">
                                            <i class="fas fa-grip-vertical"></i>
                                        </div>

                                        <!-- Field Name -->
                                        <div style="flex: 1; margin-right: 20px;">
                                            <input type="text" name="xepmarket2_chk_name_<?php echo esc_attr($field_id); ?>"
                                                value="<?php echo esc_attr(get_option('xepmarket2_chk_name_' . $field_id, $field_data['label'])); ?>"
                                                style="border: none; border-bottom: 1px dashed rgba(255,255,255,0.2); background: transparent; color: inherit; font-weight: 700; font-size: 15px; width: 100%; max-width: 280px; padding: 5px;"
                                                placeholder="<?php echo esc_attr($field_data['label']); ?>" />
                                        </div>

                                        <!-- Controls -->
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <!-- Order Number -->
                                            <div style="display: flex; align-items: center; gap: 5px; color: var(--text-muted); font-size: 12px;"
                                                title="Order number. Same number = side by side">
                                                <input type="number"
                                                    name="xepmarket2_chk_order_<?php echo esc_attr($field_id); ?>"
                                                    class="xep-order-input"
                                                    value="<?php echo esc_attr($field_data['saved_order']); ?>" min="1" max="99"
                                                    style="width: 44px; background: rgba(255,255,255,0.06); border: 1px solid var(--admin-border); color: var(--admin-primary); border-radius: 6px; padding: 4px 4px; text-align: center; font-size: 13px; font-weight: 700;" />
                                            </div>
                                            <!-- Required -->
                                            <label
                                                style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text-muted); font-size: 13px; white-space: nowrap;">
                                                <input type="checkbox"
                                                    name="xepmarket2_chk_req_<?php echo esc_attr($field_id); ?>" value="1" <?php checked(1, get_option('xepmarket2_chk_req_' . $field_id, '1'), true); ?> />
                                                REQ
                                            </label>
                                            <!-- Enable/Disable Toggle -->
                                            <label class="xep-switch">
                                                <input type="checkbox" name="xepmarket2_chk_<?php echo esc_attr($field_id); ?>"
                                                    value="1" <?php checked(1, get_option('xepmarket2_chk_' . $field_id, $field_data['default']), true); ?> />
                                                <span class="xep-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Drag & Drop Script for Default Fields -->
                            <script>
                                (function () {
                                    var sortable = document.getElementById('xep_default_fields_sortable');
                                    var dragEl = null;
                                    var dropMode = 'between'; // 'between' or 'pair'
                                    var dropTarget = null;

                                    sortable.addEventListener('dragstart', function (e) {
                                        dragEl = e.target.closest('.xep-sortable-row');
                                        if (!dragEl) return;
                                        dragEl.style.opacity = '0.4';
                                        e.dataTransfer.effectAllowed = 'move';
                                        e.dataTransfer.setData('text/plain', '');
                                    });

                                    sortable.addEventListener('dragend', function () {
                                        if (dragEl) {
                                            dragEl.style.opacity = '1';
                                        }
                                        clearHighlights();
                                        dragEl = null;
                                        dropTarget = null;
                                    });

                                    sortable.addEventListener('dragover', function (e) {
                                        e.preventDefault();
                                        e.dataTransfer.dropEffect = 'move';
                                        var target = e.target.closest('.xep-sortable-row');
                                        if (!target || target === dragEl) return;

                                        clearHighlights();
                                        dropTarget = target;

                                        var rect = target.getBoundingClientRect();
                                        var relY = e.clientY - rect.top;
                                        var height = rect.height;

                                        if (relY > height * 0.25 && relY < height * 0.75) {
                                            // Middle zone = PAIR (same order number)
                                            dropMode = 'pair';
                                            target.style.background = 'rgba(0, 242, 255, 0.08)';
                                            target.style.border = '1px solid rgba(0, 242, 255, 0.5)';
                                            target.style.boxShadow = '0 0 12px rgba(0, 242, 255, 0.15)';
                                        } else if (relY <= height * 0.25) {
                                            // Top zone = INSERT BEFORE
                                            dropMode = 'before';
                                            target.style.borderTop = '2px solid var(--admin-primary)';
                                        } else {
                                            // Bottom zone = INSERT AFTER
                                            dropMode = 'after';
                                            target.style.borderBottom = '2px solid var(--admin-primary)';
                                        }
                                    });

                                    sortable.addEventListener('drop', function (e) {
                                        e.preventDefault();
                                        if (!dropTarget || !dragEl || dropTarget === dragEl) return;

                                        var dragInput = dragEl.querySelector('.xep-order-input');
                                        var targetInput = dropTarget.querySelector('.xep-order-input');

                                        if (dropMode === 'pair') {
                                            // Give dragged field the SAME order number as target
                                            dragInput.value = targetInput.value;
                                            // Move next to target in DOM
                                            sortable.insertBefore(dragEl, dropTarget.nextSibling);
                                        } else if (dropMode === 'before') {
                                            sortable.insertBefore(dragEl, dropTarget);
                                        } else {
                                            sortable.insertBefore(dragEl, dropTarget.nextSibling);
                                        }

                                        clearHighlights();
                                        highlightPairs();
                                    });

                                    function clearHighlights() {
                                        sortable.querySelectorAll('.xep-sortable-row').forEach(function (r) {
                                            r.style.borderTop = '';
                                            r.style.borderBottom = '';
                                            r.style.background = 'rgba(255,255,255,0.02)';
                                            r.style.border = '1px solid var(--admin-border)';
                                            r.style.boxShadow = 'none';
                                            r.style.width = '100%';
                                            r.style.borderLeft = '';
                                        });
                                    }

                                    // Highlight rows only if they share the SAME order number AND are neighbors
                                    function highlightPairs() {
                                        var rows = Array.from(sortable.querySelectorAll('.xep-sortable-row'));

                                        // Reset everything first
                                        rows.forEach(function (row) {
                                            row.style.width = '100%';
                                            row.style.borderLeft = '';
                                            row.classList.remove('xep-paired');
                                        });

                                        for (var i = 0; i < rows.length - 1; i++) {
                                            var current = rows[i];
                                            var next = rows[i + 1];
                                            var curVal = current.querySelector('.xep-order-input').value;
                                            var nextVal = next.querySelector('.xep-order-input').value;

                                            // Only pair if values match and are valid numbers
                                            if (curVal !== '' && curVal === nextVal && parseInt(curVal) > 0) {
                                                current.style.width = 'calc(50% - 4px)';
                                                next.style.width = 'calc(50% - 4px)';
                                                current.style.borderLeft = '3px solid var(--admin-primary)';
                                                next.style.borderLeft = '3px solid var(--admin-primary)';
                                                current.classList.add('xep-paired');
                                                next.classList.add('xep-paired');
                                                i++; // Skip the next index as it's already paired
                                            }
                                        }
                                    }

                                    // When order number is manually changed, re-sort the rows
                                    sortable.addEventListener('change', function (e) {
                                        if (!e.target.classList.contains('xep-order-input')) return;

                                        var rows = Array.from(sortable.querySelectorAll('.xep-sortable-row'));
                                        rows.sort(function (a, b) {
                                            var aVal = parseInt(a.querySelector('.xep-order-input').value) || 99;
                                            var bVal = parseInt(b.querySelector('.xep-order-input').value) || 99;
                                            return aVal - bVal;
                                        });

                                        rows.forEach(function (row) {
                                            sortable.appendChild(row);
                                        });

                                        clearHighlights();
                                        highlightPairs();
                                    });

                                    // Hover effect
                                    sortable.addEventListener('mouseover', function (e) {
                                        var row = e.target.closest('.xep-sortable-row');
                                        if (row && !dragEl) {
                                            row.style.background = 'rgba(0, 242, 255, 0.03)';
                                        }
                                    });
                                    sortable.addEventListener('mouseout', function (e) {
                                        var row = e.target.closest('.xep-sortable-row');
                                        if (row && !dragEl) {
                                            row.style.background = 'rgba(255,255,255,0.02)';
                                        }
                                    });

                                    // Initial pair highlight
                                    highlightPairs();
                                })();
                            </script>

                            <!-- Custom Fields Repeater -->
                            <div style="margin-top: 40px; border-top: 2px solid var(--admin-border); padding-top: 20px;">
                                <h3 style="color: var(--admin-primary);"><i class="fas fa-plus-circle"></i> Add Custom
                                    Fields</h3>
                                <p class="description" style="margin-bottom: 20px;">You can add, rename, and remove your own
                                    custom text fields here. They will appear at the bottom of the checkout form.</p>

                                <input type="hidden" name="xepmarket2_chk_custom_fields" id="xep_custom_fields_data"
                                    value="<?php echo esc_attr(get_option('xepmarket2_chk_custom_fields', '[]')); ?>" />

                                <div id="xep_custom_fields_container"></div>

                                <button type="button" class="xep-save-btn" id="xep_add_custom_field_btn"
                                    style="margin-top: 20px; width: auto; background: rgba(0, 242, 255, 0.1) !important; color: var(--admin-primary) !important; border: 1px dashed var(--admin-primary) !important; margin-right: 15px;">
                                    <i class="fas fa-plus"></i> Add New Field
                                </button>

                                <button type="button" class="xep-save-btn xep-trigger-save"
                                    style="margin-top: 20px; width: auto; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </div>

                            <script>
                                (function () {
                                    var container = document.getElementById('xep_custom_fields_container');
                                    var btn = document.getElementById('xep_add_custom_field_btn');
                                    var dataInput = document.getElementById('xep_custom_fields_data');
                                    var form = document.getElementById('xep-settings-form');

                                    var customFields = [];
                                    try {
                                        customFields = JSON.parse(dataInput.value || '[]');
                                        if (!Array.isArray(customFields)) customFields = [];
                                    } catch (e) {
                                        customFields = [];
                                    }

                                    function syncData() {
                                        dataInput.value = JSON.stringify(customFields);
                                    }

                                    function renderFields() {
                                        container.innerHTML = '';
                                        customFields.forEach(function (field, index) {
                                            var div = document.createElement('div');
                                            div.className = 'xep-form-group';
                                            div.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:20px;margin-top:20px;background:rgba(255,255,255,0.02);border-radius:12px;border:1px solid var(--admin-border);';

                                            var labelVal = field.label || '';
                                            var checkedAttr = field.required ? ' checked' : '';
                                            var orderVal = field.order || 99;

                                            div.innerHTML = '<div style="flex:1;margin-right:20px;">' +
                                                '<input type="text" class="cf-label" value="' + labelVal.replace(/"/g, '&quot;') + '" placeholder="Custom Field Label (e.g. Tax ID Number)" style="width:100%;border:none;border-bottom:1px dashed rgba(255,255,255,0.2);background:transparent;color:#fff;font-weight:700;font-size:16px;padding:5px;" />' +
                                                '</div>' +
                                                '<div style="display:flex;align-items:center;gap:15px;">' +
                                                '<div style="display:flex;align-items:center;gap:6px;color:var(--text-muted);font-size:13px;" title="Order number. Same number = side by side">' +
                                                '<i class="fas fa-sort-numeric-up" style="opacity:0.5;"></i>' +
                                                '<input type="number" class="cf-order" value="' + orderVal + '" min="1" max="99" style="width:50px;background:rgba(255,255,255,0.05);border:1px solid var(--admin-border);color:#fff;border-radius:6px;padding:4px 6px;text-align:center;font-size:13px;" />' +
                                                '</div>' +
                                                '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text-muted);font-size:14px;">' +
                                                '<input type="checkbox" class="cf-required"' + checkedAttr + ' /> REQUIRED' +
                                                '</label>' +
                                                '<button type="button" class="cf-remove" style="background:rgba(255,69,58,0.1);color:#ff453a;border:1px solid rgba(255,69,58,0.2);padding:8px 12px;border-radius:8px;cursor:pointer;">' +
                                                '<i class="fas fa-trash"></i> Remove' +
                                                '</button>' +
                                                '</div>';

                                            var labelInput = div.querySelector('.cf-label');
                                            var requiredInput = div.querySelector('.cf-required');
                                            var orderInput = div.querySelector('.cf-order');
                                            var removeBtn = div.querySelector('.cf-remove');

                                            (function (idx) {
                                                labelInput.addEventListener('input', function () {
                                                    customFields[idx].label = this.value;
                                                    syncData();
                                                });
                                                requiredInput.addEventListener('change', function () {
                                                    customFields[idx].required = this.checked;
                                                    syncData();
                                                });
                                                orderInput.addEventListener('input', function () {
                                                    customFields[idx].order = parseInt(this.value) || 99;
                                                    syncData();
                                                });
                                                removeBtn.addEventListener('click', function () {
                                                    if (confirm('Are you sure you want to remove this custom field?')) {
                                                        customFields.splice(idx, 1);
                                                        renderFields();
                                                    }
                                                });
                                            })(index);

                                            container.appendChild(div);
                                        });
                                        syncData();
                                    }

                                    btn.addEventListener('click', function () {
                                        customFields.push({
                                            id: 'custom_' + Date.now().toString(36),
                                            label: '',
                                            required: false,
                                            order: 99
                                        });
                                        renderFields();
                                    });

                                    form.addEventListener('submit', function () {
                                        syncData();
                                    });

                                    renderFields();
                                })();
                            </script>
                        </div>
                    </div>

                    <!-- Tab: Support -->
                    <div id="tab-support" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Support Section Customization</h3>
                            <div class="xep-form-group">
                                <label>Section Title</label>
                                <input type="text" name="xepmarket2_support_title"
                                    value="<?php echo esc_attr(get_option('xepmarket2_support_title', 'Web3 Native <span class="logo-accent">Support</span>')); ?>" />
                            </div>
                            <div class="xep-form-group">
                                <label>Section Description</label>
                                <textarea name="xepmarket2_support_desc"
                                    rows="3"><?php echo esc_textarea(get_option('xepmarket2_support_desc', 'Need help with your order or crypto payment? Our decentralized support team is ready to help you navigate the future of commerce.')); ?></textarea>
                            </div>
                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>Support Email Address</label>
                                    <input type="text" name="xepmarket2_support_email"
                                        value="<?php echo esc_attr(get_option('xepmarket2_support_email', 'crypto@xepmarket.com')); ?>" />
                                </div>
                                <div class="xep-form-group">
                                    <label>Support Telegram Handle</label>
                                    <input type="text" name="xepmarket2_support_telegram"
                                        value="<?php echo esc_attr(get_option('xepmarket2_support_telegram', '@xepmarket')); ?>" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: SEO -->
                    <?php if (function_exists('xepmarket2_seo_settings_tab_content'))
                        xepmarket2_seo_settings_tab_content(); ?>

                    <!-- Tab: Demo Import -->
                    <div id="tab-demo" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3 style="color: var(--admin-primary);"><i class="fas fa-magic"></i> One-Click Demo Setup</h3>
                            <p class="description" style="margin-bottom: 25px;">This tool will automatically configure your
                                site to match the XEPMARKET-ALFA live preview by creating essential pages, setting up menus,
                                and configuring core theme settings.</p>

                            <div class="xep-demo-box"
                                style="background: rgba(0, 242, 255, 0.03); border: 2px dashed rgba(0, 242, 255, 0.2); padding: 50px 30px; border-radius: 20px; text-align: center;">
                                <div
                                    style="width: 80px; height: 80px; background: rgba(0, 242, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px; color: var(--admin-primary); font-size: 32px;">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <h3 style="margin-bottom: 15px;">Automated Store Installation</h3>
                                <p style="max-width: 500px; margin: 0 auto 35px; opacity: 0.8; line-height: 1.6;">Ready to
                                    build your crypto merchandise empire? Clicking below will generate your Home, Shop, and
                                    Swap pages, configure the navigation menu, and apply premium theme defaults.</p>

                                <button type="button" id="xep-run-demo-import" class="xep-save-btn"
                                    style="padding: 15px 45px; font-size: 16px; width: auto !important; cursor: pointer; background: linear-gradient(135deg, var(--admin-primary), #00d2ff) !important; box-shadow: 0 10px 25px rgba(0, 242, 255, 0.2) !important;">
                                    RUN ONE-CLICK INSTALL
                                </button>

                                <div id="xep-demo-status"
                                    style="margin-top: 30px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                </div>
                                <div id="xep-demo-progress"
                                    style="display: none; width: 100%; max-width: 400px; height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; margin: 20px auto 0; overflow: hidden;">
                                    <div class="xep-progress-bar"
                                        style="width: 0%; height: 100%; background: var(--admin-primary); transition: width 0.3s ease;">
                                    </div>
                                </div>
                            </div>

                            <div class="xep-factory-reset-box"
                                style="margin-top: 40px; padding: 30px; border-radius: 20px; background: rgba(255, 69, 58, 0.03); border: 1px solid rgba(255, 69, 58, 0.1);">
                                <div style="display: flex; align-items: flex-start; gap: 20px;">
                                    <div
                                        style="width: 50px; height: 50px; background: rgba(255, 69, 58, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #ff453a; font-size: 20px; shrink: 0;">
                                        <i class="fas fa-trash-alt"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <h4 style="color: #ff453a; margin-bottom: 5px;">Factory Reset Theme</h4>
                                        <p style="font-size: 13px; opacity: 0.7; margin-bottom: 15px;">Wipe all
                                            theme-specific settings, logos, and custom menus back to their original state.
                                            <strong>This action cannot be undone.</strong>
                                        </p>
                                        <button type="button" id="xep-factory-reset" class="xep-save-btn"
                                            style="background: linear-gradient(135deg, #ff453a, #d32f2f) !important; width: auto !important; padding: 10px 25px !important; font-size: 12px !important;">WIPE
                                            & RESET EVERYTHING</button>
                                    </div>
                                </div>
                            </div>

                            <div
                                style="margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid var(--admin-border);">
                                <h4 style="margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Important Information
                                </h4>
                                <ul style="margin: 0; padding-left: 20px; font-size: 13px; opacity: 0.7; line-height: 1.8;">
                                    <li>Existing posts, products and pages will <strong>not</strong> be modified.</li>
                                    <li>New pages (Home, Shop, Swap) will be created if they don't exist.</li>
                                    <li>Theme settings will be updated to match the premium demo defaults.</li>
                                    <li>Navigation menus will be automatically assigned to their respective positions.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Auto Updater -->
                    <div id="tab-updater" class="xep-tab-content">
                        <?php
                        global $xepmarket_updater;
                        if (isset($xepmarket_updater) && is_object($xepmarket_updater)) {
                            $xepmarket_updater->updater_page_html();
                        } else {
                            echo '<div class="xep-section-card"><p>Updater module is loading...</p></div>';
                        }
                        ?>
                    </div>

                </div>
            </div>
            <input type="hidden" id="xep_admin_ajax_nonce" value="<?php echo wp_create_nonce('xep_admin_nonce'); ?>">
        </form>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            // Tab Switching Logic
            $('.xep-nav-item').on('click', function () {
                var tabId = $(this).data('tab');

                // Update Sidebar
                $('.xep-nav-item').removeClass('active');
                $(this).addClass('active');

                // Update Content
                $('.xep-tab-content').removeClass('active');
                $('#' + tabId).addClass('active');

                // Update URL for persistence
                window.location.hash = tabId;
            });

            // Handle URL Hash for persistent tabs on refresh
            var hash = window.location.hash;
            if (hash) {
                var $targetTab = $('.xep-nav-item[data-tab="' + hash.replace('#', '') + '"]');
                if ($targetTab.length > 0) {
                    $targetTab.trigger('click');
                }
            }

            // Quick Save Trigger
            $('.xep-trigger-save').on('click', function () {
                $('#xep-settings-form').submit();
            });

            // ── AJAX: Demo Import ──
            $('#xep-run-demo-import').on('click', function () {
                if (!confirm('Run One-Click Setup? This will create essential pages and apply demo defaults.')) return;

                var $btn = $(this);
                var $status = $('#xep-demo-status');
                var $progWrap = $('#xep-demo-progress');
                var $progBar = $progWrap.find('.xep-progress-bar');
                var nonce = $('#xep_admin_ajax_nonce').val();

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> IMPORTING...');
                $status.text('Starting data import...').css('color', 'var(--admin-primary)');
                $progWrap.fadeIn();
                $progBar.css('width', '10%');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'xep_import_demo', nonce: nonce },
                    success: function (response) {
                        if (response.success) {
                            $progBar.css('width', '100%');
                            $status.text('SETUP COMPLETE! REFRESHING...').css('color', '#32d74b');
                            setTimeout(function () { window.location.reload(); }, 1500);
                        } else {
                            $status.text('ERROR: ' + response.data).css('color', '#ff453a');
                            $btn.prop('disabled', false).text('RUN ONE-CLICK INSTALL');
                        }
                    },
                    error: function () {
                        $status.text('CONNECTION ERROR. TRY AGAIN.').css('color', '#ff453a');
                        $btn.prop('disabled', false).text('RUN ONE-CLICK INSTALL');
                    }
                });
            });

            // ── AJAX: Factory Reset ──
            $('#xep-factory-reset').on('click', function () {
                if (!confirm('⚠️ CRITICAL: Are you sure? This will wipe ALL theme settings and cannot be undone.')) return;
                if (!confirm('Second Confirmation: Wipe all data?')) return;

                var $btn = $(this);
                var nonce = $('#xep_admin_ajax_nonce').val();

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> WIPING DATA...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'xep_factory_reset', nonce: nonce },
                    success: function (response) {
                        if (response.success) {
                            alert('Factory reset successful. The page will now reload.');
                            window.location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            $btn.prop('disabled', false).text('WIPE & RESET EVERYTHING');
                        }
                    }
                });
            });

            // Status message auto-hide
            if ($('.updated, .error').length > 0) {
                setTimeout(function () {
                    $('.updated, .error').fadeOut();
                }, 5000);
            }
        });
    </script>
    <?php
}


/**
 * Display Discount Percentage and Free Shipping on Single Product
 */
function xepmarket2_display_product_advantages()
{
    global $product;

    if (!$product || !function_exists('is_product') || !is_product())
        return;

    echo '<div class="product-advantages-wrapper">';

    // Discount Calculation
    if ($product->is_on_sale()) {
        $regular_price = (float) $product->get_regular_price();
        $sale_price = (float) $product->get_sale_price();

        if ($regular_price > 0 && $sale_price > 0) {
            $percentage = round((($regular_price - $sale_price) / $regular_price) * 100);
            echo '<span class="discount-label">-' . $percentage . '% Off</span>';
        }
    }

    // Free Shipping Badge
    echo '<span class="free-shipping-label"><i class="dashicons dashicons-location-alt"></i> Free Shipping</span>';

    echo '</div>';
}
add_action('woocommerce_single_product_summary', 'xepmarket2_display_product_advantages', 11);

/**
 * Force Related Products to English and 3 Column Layout
 */
add_filter('woocommerce_product_related_products_heading', function () {
    return 'Related Products';
});

add_filter('woocommerce_output_related_products_args', function ($args) {
    $args['posts_per_page'] = 3;
    $args['columns'] = 3;
    return $args;
}, 999);

// Temporarily disabled to debug memory exhaustion
/*
add_filter('gettext', function ($translated, $text, $domain) {
    if ($text === 'Related products' || $text === 'İlgili ürünler') {
        return 'Related Products';
    }
    return $translated;
}, 20, 3);
*/

/**
 * Force English labels for WooCommerce buttons
 */
add_filter('woocommerce_product_single_add_to_cart_text', function () {
    return 'ADD TO CART';
});
add_filter('woocommerce_product_add_to_cart_text', function () {
    return 'ADD TO CART';
});

/**
 * One Click Demo Import - Configuration
 * This integrates with the "One Click Demo Import" plugin to import demo products and content
 */
function xepmarket2_ocdi_import_files()
{
    $demo_content_path = get_template_directory() . '/demo-content/demo-content.xml';

    // Only show import option if demo-content.xml exists
    if (!file_exists($demo_content_path)) {
        return array();
    }

    return array(
        array(
            'import_file_name' => 'XEPMARKET Full Demo',
            'import_file_url' => get_template_directory_uri() . '/demo-content/demo-content.xml',
            'import_preview_image_url' => get_template_directory_uri() . '/screenshot.png',
            'preview_url' => 'https://xepmarket.com/',
            'import_notice' => __('This will import all demo products, pages, and media. Please ensure you have backed up your existing content.', 'xepmarket2'),
        ),
    );
}
add_filter('ocdi/import_files', 'xepmarket2_ocdi_import_files');

/**
 * After import actions - Set up pages, menus, and theme options
 */
function xepmarket2_ocdi_after_import_setup()
{
    // Run our existing demo setup function
    xepmarket2_setup_demo_data();

    // Set WooCommerce shop page
    $shop_page = get_page_by_path('shop');
    if ($shop_page) {
        update_option('woocommerce_shop_page_id', $shop_page->ID);
    }

    // Set Cart page
    $cart_page = get_page_by_path('cart');
    if ($cart_page) {
        update_option('woocommerce_cart_page_id', $cart_page->ID);
    }

    // Set Checkout page
    $checkout_page = get_page_by_path('checkout');
    if ($checkout_page) {
        update_option('woocommerce_checkout_page_id', $checkout_page->ID);
    }

    // Set My Account page
    $myaccount_page = get_page_by_path('my-account');
    if ($myaccount_page) {
        update_option('woocommerce_myaccount_page_id', $myaccount_page->ID);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
add_action('ocdi/after_import', 'xepmarket2_ocdi_after_import_setup');


/**
 * WooCommerce: Allow users to set their own password during registration
 * This ensures the password field is visible on the register form
 */
add_action('init', function () {
    if (get_option('woocommerce_registration_generate_password') !== 'no') {
        update_option('woocommerce_registration_generate_password', 'no');
    }
});

/**
 * Customize Privacy Policy Text at Checkout to include a Checkbox
 */





/**
 * Remove "Cart updated" notice
 */

add_filter('woocommerce_add_success', function ($message) {
    if (strpos($message, 'Cart updated') !== false || strpos($message, 'Sepet güncellendi') !== false) {
        return false;
    }
    return $message;
}, 10, 1);

/**
 * Force WooCommerce AJAX add-to-cart (required for add-to-cart popup)
 * - Enable AJAX add to cart on archive pages
 * - Disable redirect to cart after adding
 */
add_filter('pre_option_woocommerce_enable_ajax_add_to_cart', function () {
    return 'yes';
});
add_filter('pre_option_woocommerce_cart_redirect_after_add', function () {
    return 'no';
});
/**
 * Remove "Cart updated" notice
 */
add_filter('woocommerce_add_success', function ($message) {
    if (strpos($message, 'Cart updated') !== false || strpos($message, 'Sepet güncellendi') !== false) {
        return false;
    }
    return $message;
}, 10, 1);
