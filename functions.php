<?php ob_start(); // Prevent headers already sent warnings

/**
 * XepMarket2 functions and definitions
 */

// Define Theme Version
define('XEPMARKET_ALFA_VERSION', '1.77');

// Include TGM Plugin Activation (github-plugins-sync first so TGMPA can use GitHub zip URL)
require_once get_template_directory() . '/inc/github-plugins-sync.php';
require_once get_template_directory() . '/inc/plugin-config.php';
require_once get_template_directory() . '/inc/demo-importer.php';
require_once get_template_directory() . '/inc/seo-config.php';
require_once get_template_directory() . '/inc/ali-sync/helper.php';
require_once get_template_directory() . '/inc/live-search.php';
require_once get_template_directory() . '/inc/theme-updater.php';
require_once get_template_directory() . '/inc/admin-cockpit.php';

// Telegram order notifications (theme-integrated; loads when WooCommerce is active)
add_action('init', function () {
    if (class_exists('WooCommerce') && !function_exists('xepmarket2_telegram_send_message')) {
        require_once get_template_directory() . '/inc/telegram-bot.php';
    }
}, 1);

// Affiliate system (theme-integrated; loads when WooCommerce is active)
add_action('init', function () {
    if (class_exists('WooCommerce') && !function_exists('xepmarket2_affiliate_admin_page')) {
        require_once get_template_directory() . '/inc/affiliate.php';
    }
    
    // Automatically ensure Swap page is up-to-date
    if (function_exists('xepmarket2_auto_update_swap_page')) {
        xepmarket2_auto_update_swap_page();
    }
}, 1);

if (!defined('ABSPATH')) {
    exit;
}
/**
 * COMPATIBILITY: Prevent Fatal Error if mail() is disabled on server
 * This prevents the site from crashing when WooCommerce or other plugins try to send emails
 * on servers that have the PHP mail() function completely removed/disabled.
 * Skipped when SMTP module is active (SMTP doesn't need native mail()).
 */
add_filter('pre_wp_mail', function ($return) {
    // If SMTP module is active, allow wp_mail to proceed (PHPMailer uses sockets, not mail())
    if (get_option('xep_smtp_enable', '0') == '1' && !empty(get_option('xep_smtp_host'))) {
        return $return;
    }
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
/**
 * MIGRATION: Clear broken local/demo URLs from database
 * NOTE: Aggressive clearing of xepmarket.com and xepmarket.local was removed as it was interfering with user settings on those domains.
 */
function xepmarket2_migrate_broken_demo_urls()
{
    // Migration logic removed to prevent accidental data loss for users on these domains.
}
add_action('after_setup_theme', 'xepmarket2_migrate_broken_demo_urls');

add_action('after_switch_theme', function () {
    delete_transient('xepmarket2_all_options');
    update_option('xepmarket2_affiliate_flush_rules', 1);
    
    // Disable comments and reviews by default on theme activation
    update_option('default_comment_status', 'closed');
    update_option('default_ping_status', 'closed');
    update_option('woocommerce_enable_reviews', 'no');
    update_option('xepmarket2_enable_comments', '0'); // Theme custom toggle

    // Auto-setup Swap page on activation
    if (function_exists('xepmarket2_auto_update_swap_page')) {
        xepmarket2_auto_update_swap_page();
    }
});

/**
 * GLOBAL TOGGLE: Comments & WooCommerce Reviews
 * If disabled in theme settings, we shut down comments and remove the reviews tab.
 */
function xepmarket2_enforce_comment_settings() {
    if (get_option('xepmarket2_enable_comments', '0') !== '1') {
        // Disable post comments
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        
        // Hide existing comments
        add_filter('get_comments_number', '__return_zero', 20);
        
        // Disable WooCommerce reviews
        add_filter('woocommerce_product_tabs', function($tabs) {
            unset($tabs['reviews']);
            return $tabs;
        }, 98);
        
        // Disable review display on product page if tabs are not used
        remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
    }
}
add_action('wp', 'xepmarket2_enforce_comment_settings');

/**
 * FAILSAFE: Content Security Policy for Swap Page
 * Overrides restrictive plugin headers if they don't explicitly allow StealthEX.
 * This ensures the widget works automatically on all sites using this theme.
 */
add_action('send_headers', function() {
    // We check if we are on the swap page or if it's the swap request
    // Since is_page() might be too early here, we check the request URI
    $request_uri = $_SERVER['REQUEST_URI'];
    if (strpos($request_uri, '/swap') !== false) {
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://api.qrserver.com https://cdn.brevo.com https://cdn.by.wonderpush.com https://sibautomation.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
               "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https: http:; " .
               "connect-src 'self' https://api.omnixep.com https://api.coingecko.com https://mexc.com https://dextrade.com https://in-automate.brevo.com; " .
               "frame-src 'self' https://stealthex.io https://*.stealthex.io; " .
               "frame-ancestors 'self'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
        
        header("Content-Security-Policy: " . $csp, true);
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self \"https://stealthex.io\")", true);
    }
}, 9999);

/**
 * PERMANENT SELF-HEALING: Auto-sync Database with Folder Path
 * This ensures that if GitHub renames the folder (e.g. PlanC90-...),
 * WordPress automatically updates its internal paths to match.
 * Prevents 404/MIME errors on all pages and admin panel.
 */
add_action('after_setup_theme', function () {
    $current_folder = basename(__DIR__); // Current directory name (e.g. PlanC90-XEPMARKET-ALFA-xxx)
    $active_slug = get_option('stylesheet'); // What WP thinks the folder is

    // Only sync if they don't match AND we are definitely in a XEPMARKET theme folder
    if ($current_folder !== $active_slug && (strpos($current_folder, 'XEPMARKET-ALFA') !== false)) {
        update_option('template', $current_folder);
        update_option('stylesheet', $current_folder);
        
        // Use a transient to refresh rules only once
        set_transient('xep_path_healed', 1, 60);
        
        // If we are in admin, notify the system but don't disrupt the user
        error_log("XEPMARKET: Path mismatch detected ($active_slug -> $current_folder). Database synced automatically.");
    }
}, 1);

/**
 * EMERGENCY SELF-REPAIR: Triggered via ?xep_repair=1
 * Fixes folder naming and nested directory issues on remote servers.
 */
add_action('init', function () {
    // Check for secret key OR admin permission
    $is_secret = (isset($_GET['xep_repair']) && $_GET['xep_repair'] === 'fix_folder_now');
    $is_admin = (isset($_GET['xep_repair']) && current_user_can('manage_options'));

    if ($is_secret || $is_admin) {
        $themes_dir = trailingslashit(get_theme_root());
        $expected_slug = 'XEPMARKET-ALFA';
        $found_broken = false;
        $log = [];

        // Scan themes directory
        $folders = scandir($themes_dir);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') continue;

            // Check for GitHub-style renamed folder
            if (strpos($folder, 'PlanC90-XEPMARKET-ALFA') === 0) {
                $broken_path = $themes_dir . $folder;
                $target_path = $themes_dir . $expected_slug;

                // A. Detect and Flatten Nested Structure (folder/folder/style.css)
                // This happens when GitHub updates place files in a sub-sub-directory
                $sub_path = $broken_path . '/' . $folder;
                if (!is_dir($sub_path)) {
                    // Try searching for any subfolder that contains style.css
                    $subdirs = scandir($broken_path);
                    foreach($subdirs as $sd) {
                        if($sd !== '.' && $sd !== '..' && is_dir($broken_path . '/' . $sd)) {
                            if(file_exists($broken_path . '/' . $sd . '/style.css')) {
                                $sub_path = $broken_path . '/' . $sd;
                                break;
                            }
                        }
                    }
                }

                if (is_dir($sub_path) && file_exists($sub_path . '/style.css')) {
                    $log[] = "Found nested files in $folder. Moving them to root...";
                    
                    $files = scandir($sub_path);
                    foreach ($files as $f) {
                        if ($f === '.' || $f === '..') continue;
                        rename($sub_path . '/' . $f, $broken_path . '/' . $f);
                    }
                    @rmdir($sub_path);
                    $found_broken = true;
                }

                // B. Rename to standard slug if needed
                if ($folder !== $expected_slug && !file_exists($themes_dir . $expected_slug)) {
                    if (rename($broken_path, $themes_dir . $expected_slug)) {
                        $log[] = "Renamed $folder to $expected_slug on disk.";
                        $found_broken = true;
                    }
                }
            }
        }

        // C. DATABASE SYNC: Update WordPress to use the correct slug
        // If the current active theme is still pointing to the old name, update it.
        $current_theme = get_option('stylesheet');
        if ($current_theme !== $expected_slug && file_exists($themes_dir . $expected_slug)) {
            update_option('template', $expected_slug);
            update_option('stylesheet', $expected_slug);
            $log[] = "Updated WordPress database: Active theme is now <strong>$expected_slug</strong>.";
            $found_broken = true;
            
            // Re-activate to clear all caches
            switch_theme($expected_slug);
        }

        // D. FORCE SWAP PAGE UPDATE
        $swap_log = 'No update action taken';
        if (function_exists('xepmarket2_auto_update_swap_page')) {
            delete_option('xepmarket_swap_page_version');
            $swap_log = xepmarket2_auto_update_swap_page();
            $log[] = "Forced <strong>Swap Page</strong> update: $swap_log.";
            $found_broken = true;
        }

        // E. CLEAR CACHES & FLUSH RULES
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
        $log[] = "Object cache and rewrite rules flushed.";

        if ($found_broken || !empty($log)) {
            $msg = "<h3>Theme Structure Repair Log:</h3><ul><li>" . implode("</li><li>", $log) . "</li></ul>";
            if ($found_broken) {
                $msg .= "<p style='color:green; font-weight:bold;'>FOLDER REPAIR COMPLETE! Siteniz Ãƒâ€¦Ã…Â¸imdi dÃƒÆ’Ã‚Â¼zelmiÃƒâ€¦Ã…Â¸ olmalÃƒâ€Ã‚Â±.</p>";
            }
            wp_die($msg . '<p><a href="' . admin_url('themes.php') . '">Temalar Sekmesine Git</a> | <a href="' . home_url() . '">Siteyi GÃƒÆ’Ã‚Â¶r</a></p>');
        } else {
            wp_die('Herhangi bir yapÃƒâ€Ã‚Â±sal hata bulunamadÃƒâ€Ã‚Â± veya tamir zaten yapÃƒâ€Ã‚Â±lmÃƒâ€Ã‚Â±Ãƒâ€¦Ã…Â¸. Siteniz normal gÃƒÆ’Ã‚Â¶rÃƒÆ’Ã‚Â¼nÃƒÆ’Ã‚Â¼yor olmalÃƒâ€Ã‚Â±.');
        }
    }
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

    // Force product category/tag archives to use woocommerce.php so products display (fixes "Nothing Found")
    add_filter('template_include', function ($template) {
        if (function_exists('is_product_taxonomy') && is_product_taxonomy() && ( (function_exists('is_product_category') && is_product_category()) || (function_exists('is_product_tag') && is_product_tag()) )) {
            $woo_template = get_template_directory() . '/woocommerce.php';
            if (file_exists($woo_template)) {
                return $woo_template;
            }
        }
        return $template;
    }, 999);

    // Site Icon (Favicon) Support
    add_theme_support('site-icon');
}

/**
 * Fix WooCommerce category/tag base when set to full URL (e.g. https://product-category) ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â breaks category pages.
 */
function xepmarket2_fix_woocommerce_permalink_bases() {
    if (!function_exists('wc_get_permalink_structure') || !class_exists('WooCommerce')) {
        return;
    }
    $permalinks = get_option('woocommerce_permalinks', array());
    if (!is_array($permalinks)) {
        return;
    }
    $fixed = false;
    if (!empty($permalinks['category_base']) && (strpos($permalinks['category_base'], 'http') === 0 || strpos($permalinks['category_base'], '//') !== false)) {
        $permalinks['category_base'] = 'product-category';
        $fixed = true;
    }
    if (!empty($permalinks['tag_base']) && (strpos($permalinks['tag_base'], 'http') === 0 || strpos($permalinks['tag_base'], '//') !== false)) {
        $permalinks['tag_base'] = 'product-tag';
        $fixed = true;
    }
    if ($fixed) {
        update_option('woocommerce_permalinks', $permalinks);
        flush_rewrite_rules(false);
    }
}
add_action('init', 'xepmarket2_fix_woocommerce_permalink_bases', 5);

add_action('after_setup_theme', 'xepmarket2_setup');

/**
 * Fallback when no menu is assigned to Primary location. Shows Home, Shop, Swap.
 */
function xepmarket2_menu_fallback($args)
{
    $args = (object) $args;
    $menu_class = !empty($args->menu_class) ? $args->menu_class : 'menu';
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
    $swap_url = home_url('/swap/');
    $items = array(
        array('title' => __('Home', 'xepmarket2'), 'url' => home_url('/')),
        array('title' => __('Shop', 'xepmarket2'), 'url' => $shop_url),
        array('title' => __('Swap', 'xepmarket2'), 'url' => $swap_url),
    );
    echo '<ul id="primary-menu" class="' . esc_attr($menu_class) . '">';
    foreach ($items as $item) {
        echo '<li><a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a></li>';
    }
    echo '</ul>';
}
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
 * AUTO-REGISTRATION: Force WooCommerce registration options and pre-check "Create an account" box
 */
function xepmarket2_setup_woocommerce_checkout_registration() {
    if (!class_exists('WooCommerce')) return;

    // 1. Force Enable Registration on Checkout
    if (get_option('woocommerce_enable_signup_and_login_from_checkout') !== 'yes') {
        update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');
    }

    // 2. Ensure Guest Checkout is enabled so both options (Guest/Register) are visible
    if (get_option('woocommerce_enable_guest_checkout') !== 'yes') {
        update_option('woocommerce_enable_guest_checkout', 'yes');
    }

    // 3. Enable Registration on My Account page
    if (get_option('woocommerce_enable_myaccount_registration') !== 'yes') {
        update_option('woocommerce_enable_myaccount_registration', 'yes');
    }
}
add_action('init', 'xepmarket2_setup_woocommerce_checkout_registration');

// 3. Pre-check the "Create an account?" checkbox by default
add_filter('woocommerce_create_account_default_checked', '__return_true');

// 4. Disable default WooCommerce Terms and Conditions checkbox (as requested)
add_filter('woocommerce_checkout_show_terms', '__return_false');

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
        $ppp = intval($_GET['ppp']);
        if ($ppp === -1) {
            return -1; // Show all products (no pagination)
        }
        return min(max($ppp, 1), 200);
    }
    return 25; // Default for Alpha theme
}, 9999);

/**
 * Shop page: Category filter bar for better customer experience
 */
function xepmarket2_shop_category_bar() {
    if (!function_exists('wc_get_page_id') || !taxonomy_exists('product_cat')) {
        return;
    }
    $terms = get_terms(array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));
    if (is_wp_error($terms) || empty($terms)) {
        return;
    }
    $current_id = (function_exists('is_product_category') && is_product_category()) ? get_queried_object_id() : 0;
    $shop_url   = get_permalink(wc_get_page_id('shop'));
    echo '<div class="xep-shop-category-bar" role="navigation" aria-label="' . esc_attr__('Product categories', 'woocommerce') . '">';
    echo '<a href="' . esc_url($shop_url) . '" class="xep-cat-pill' . ($current_id === 0 ? ' active' : '') . '">' . esc_html__('All', 'woocommerce') . '</a>';
    foreach ($terms as $term) {
        $url  = get_term_link($term);
        $active = ($current_id === (int) $term->term_id) ? ' active' : '';
        if (is_wp_error($url)) {
            continue;
        }
        echo '<a href="' . esc_url($url) . '" class="xep-cat-pill' . $active . '">' . esc_html($term->name) . '</a>';
    }
    echo '</div>';
}

/**
 * Display Products Per Page Selector
 */
add_action('woocommerce_before_shop_loop', 'xepmarket2_shop_category_bar', 12);
add_action('woocommerce_before_shop_loop', function () {
    $ppp = isset($_GET['ppp']) ? intval($_GET['ppp']) : 25;
    $options = array(25, 50, 75, 100, 200);
    $all_value = -1; // "All" = show all

    echo '<div class="xep-ppp-selector">';
    echo '<span class="ppp-label">SHOW:</span>';
    foreach ($options as $opt) {
        $active = ($ppp === $opt) ? 'active' : '';
        $current_url = preg_replace('/\/page\/[0-9]+\//', '/', $_SERVER['REQUEST_URI']);
        $url = add_query_arg(array('ppp' => $opt, 'paged' => 1), $current_url);
        echo '<a href="' . esc_url($url) . '" class="ppp-opt ' . $active . '">' . $opt . '</a>';
    }
    $active_all = ($ppp === $all_value) ? 'active' : '';
    $url_all = add_query_arg(array('ppp' => $all_value, 'paged' => 1), preg_replace('/\/page\/[0-9]+\//', '/', $_SERVER['REQUEST_URI']));
    echo '<a href="' . esc_url($url_all) . '" class="ppp-opt ' . $active_all . '">' . esc_html__('All', 'woocommerce') . '</a>';
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
            $all_fields_ordered[$key] = ['order' => $order_num, 'base' => $base_key];
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
        $all_fields_ordered['billing_telegram'] = ['order' => $order_num, 'base' => 'telegram'];
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
            $all_fields_ordered[$field_key] = ['order' => $cf_order, 'base' => $cf['id'], 'width' => isset($cf['width']) ? $cf['width'] : 'full'];
        }
    }

    // Build rows from order + width: 2/2 = full row, 2/1 = half (pair with next 2/1 or leave empty)
    uasort($all_fields_ordered, function ($a, $b) {
        return ($a['order'] - $b['order']);
    });
    $sorted_keys = array_keys($all_fields_ordered);

    $rows = [];
    $i = 0;
    while ($i < count($sorted_keys)) {
        $fkey = $sorted_keys[$i];
        $info = $all_fields_ordered[$fkey];
        $base = $info['base'];
        $width = isset($info['width']) ? $info['width'] : get_option('xepmarket2_chk_width_' . $base, 'half');
        if ($width === 'full') {
            $rows[] = [['key' => $fkey, 'class' => 'form-row-wide']];
            $i++;
        } else {
            if ($i + 1 < count($sorted_keys)) {
                $next_key = $sorted_keys[$i + 1];
                $next_info = $all_fields_ordered[$next_key];
                $next_base = $next_info['base'];
                $next_width = isset($next_info['width']) ? $next_info['width'] : get_option('xepmarket2_chk_width_' . $next_base, 'half');
                if ($next_width === 'half') {
                    $rows[] = [['key' => $fkey, 'class' => 'form-row-first'], ['key' => $next_key, 'class' => 'form-row-last']];
                    $i += 2;
                    continue;
                }
            }
            $rows[] = [['key' => $fkey, 'class' => 'form-row-first']];
            $i++;
        }
    }

    $priority = 10;
    $css_order_styles = '';
    foreach ($rows as $row) {
        foreach ($row as $cell) {
            $fkey = $cell['key'];
            $cls = $cell['class'];
            if (!isset($fields['billing'][$fkey])) continue;
            $fields['billing'][$fkey]['priority'] = $priority;
            $fields['billing'][$fkey]['class'] = array($cls);
            $span = ($cls === 'form-row-wide') ? 2 : 1;
            $css_order_styles .= '#' . $fkey . '_field{order:' . $priority . ' !important;grid-column:span ' . $span . ' !important;}';
            $priority++;
        }
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
 * ÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚Â
 * Localized "No Shipping Available" Warning
 * Shows warning in the selected country's native language
 * ÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚Â
 */
function xepmarket2_no_shipping_messages()
{
    return [
        'default' => 'We do not currently ship to this country.',
        'TR' => 'Ãƒâ€¦Ã‚Âu anda bu ÃƒÆ’Ã‚Â¼lkeye gÃƒÆ’Ã‚Â¶nderim yapÃƒâ€Ã‚Â±lmamaktadÃƒâ€Ã‚Â±r.',
        'CN' => 'ÃƒÂ¦Ã‹â€ Ã¢â‚¬ËœÃƒÂ¤Ã‚Â»Ã‚Â¬ÃƒÂ§Ã¢â‚¬ÂºÃ‚Â®ÃƒÂ¥Ã¢â‚¬Â°Ã‚ÂÃƒÂ¤Ã‚Â¸Ã‚ÂÃƒÂ¥Ã‚ÂÃ¢â‚¬ËœÃƒÂ¨Ã‚Â¯Ã‚Â¥ÃƒÂ¥Ã¢â‚¬ÂºÃ‚Â½ÃƒÂ¥Ã‚Â®Ã‚Â¶/ÃƒÂ¥Ã…â€œÃ‚Â°ÃƒÂ¥Ã…â€™Ã‚ÂºÃƒÂ¥Ã‚ÂÃ¢â‚¬ËœÃƒÂ¨Ã‚Â´Ã‚Â§ÃƒÂ£Ã¢â€šÂ¬Ã¢â‚¬Å¡',
        'TW' => 'ÃƒÂ¦Ã‹â€ Ã¢â‚¬ËœÃƒÂ¥Ã¢â€šÂ¬Ã¢â‚¬ËœÃƒÂ§Ã¢â‚¬ÂºÃ‚Â®ÃƒÂ¥Ã¢â‚¬Â°Ã‚ÂÃƒÂ¤Ã‚Â¸Ã‚ÂÃƒÂ¥Ã‚ÂÃ¢â‚¬ËœÃƒÂ¨Ã‚Â©Ã‚Â²ÃƒÂ¥Ã…â€œÃ¢â‚¬Â¹ÃƒÂ¥Ã‚Â®Ã‚Â¶/ÃƒÂ¥Ã…â€œÃ‚Â°ÃƒÂ¥Ã‚ÂÃ¢â€šÂ¬ÃƒÂ§Ã¢â€Â¢Ã‚Â¼ÃƒÂ¨Ã‚Â²Ã‚Â¨ÃƒÂ£Ã¢â€šÂ¬Ã¢â‚¬Å¡',
        'HK' => 'ÃƒÂ¦Ã‹â€ Ã¢â‚¬ËœÃƒÂ¥Ã¢â€šÂ¬Ã¢â‚¬ËœÃƒÂ§Ã¢â‚¬ÂºÃ‚Â®ÃƒÂ¥Ã¢â‚¬Â°Ã‚ÂÃƒÂ¤Ã‚Â¸Ã‚ÂÃƒÂ¥Ã‚ÂÃ¢â‚¬ËœÃƒÂ¨Ã‚Â©Ã‚Â²ÃƒÂ¥Ã…â€œÃ¢â‚¬Â¹ÃƒÂ¥Ã‚Â®Ã‚Â¶/ÃƒÂ¥Ã…â€œÃ‚Â°ÃƒÂ¥Ã‚ÂÃ¢â€šÂ¬ÃƒÂ§Ã¢â€Â¢Ã‚Â¼ÃƒÂ¨Ã‚Â²Ã‚Â¨ÃƒÂ£Ã¢â€šÂ¬Ã¢â‚¬Å¡',
        'JP' => 'ÃƒÂ§Ã‚ÂÃ‚Â¾ÃƒÂ¥Ã…â€œÃ‚Â¨ÃƒÂ£Ã¢â€šÂ¬Ã‚ÂÃƒÂ£Ã‚ÂÃ¢â‚¬Å“ÃƒÂ£Ã‚ÂÃ‚Â®ÃƒÂ¥Ã¢â‚¬ÂºÃ‚Â½ÃƒÂ£Ã‚ÂÃ‚Â¸ÃƒÂ£Ã‚ÂÃ‚Â®ÃƒÂ©Ã¢â‚¬Â¦Ã‚ÂÃƒÂ©Ã¢â€šÂ¬Ã‚ÂÃƒÂ£Ã‚ÂÃ‚Â¯ÃƒÂ¨Ã‚Â¡Ã…â€™ÃƒÂ£Ã‚ÂÃ‚Â£ÃƒÂ£Ã‚ÂÃ‚Â¦ÃƒÂ£Ã‚ÂÃ…Â ÃƒÂ£Ã¢â‚¬Å¡Ã…Â ÃƒÂ£Ã‚ÂÃ‚Â¾ÃƒÂ£Ã‚ÂÃ¢â‚¬ÂºÃƒÂ£Ã¢â‚¬Å¡Ã¢â‚¬Å“ÃƒÂ£Ã¢â€šÂ¬Ã¢â‚¬Å¡',
        'KR' => 'ÃƒÂ­Ã‹Å“Ã¢â‚¬ÂÃƒÂ¬Ã‚ÂÃ‚Â¬ ÃƒÂ¬Ã‚ÂÃ‚Â´ ÃƒÂªÃ‚ÂµÃ‚Â­ÃƒÂªÃ‚Â°Ã¢â€šÂ¬ÃƒÂ«Ã‚Â¡Ã…â€œÃƒÂ«Ã…Â Ã¢â‚¬Â ÃƒÂ«Ã‚Â°Ã‚Â°ÃƒÂ¬Ã¢â‚¬Â Ã‚Â¡ÃƒÂ¬Ã‚ÂÃ‚Â´ ÃƒÂ«Ã‚Â¶Ã‹â€ ÃƒÂªÃ‚Â°Ã¢â€šÂ¬ÃƒÂ­Ã¢â‚¬Â¢Ã‚Â©ÃƒÂ«Ã¢â‚¬Â¹Ã‹â€ ÃƒÂ«Ã¢â‚¬Â¹Ã‚Â¤.',
        'DE' => 'Wir liefern derzeit nicht in dieses Land.',
        'AT' => 'Wir liefern derzeit nicht in dieses Land.',
        'CH' => 'Wir liefern derzeit nicht in dieses Land.',
        'FR' => 'Nous ne livrons pas actuellement dans ce pays.',
        'ES' => 'Actualmente no realizamos envÃƒÆ’Ã‚Â­os a este paÃƒÆ’Ã‚Â­s.',
        'MX' => 'Actualmente no realizamos envÃƒÆ’Ã‚Â­os a este paÃƒÆ’Ã‚Â­s.',
        'AR' => 'Actualmente no realizamos envÃƒÆ’Ã‚Â­os a este paÃƒÆ’Ã‚Â­s.',
        'CO' => 'Actualmente no realizamos envÃƒÆ’Ã‚Â­os a este paÃƒÆ’Ã‚Â­s.',
        'IT' => 'Al momento non effettuiamo spedizioni in questo paese.',
        'PT' => 'Atualmente nÃƒÆ’Ã‚Â£o fazemos envios para este paÃƒÆ’Ã‚Â­s.',
        'BR' => 'No momento, nÃƒÆ’Ã‚Â£o fazemos envios para este paÃƒÆ’Ã‚Â­s.',
        'NL' => 'Wij leveren momenteel niet in dit land.',
        'BE' => 'Wij leveren momenteel niet in dit land.',
        'PL' => 'Obecnie nie wysyÃƒâ€¦Ã¢â‚¬Å¡amy do tego kraju.',
        'RU' => 'Ã„ÂÃ¢â‚¬â„¢ Ã„ÂÃ‚Â½Ã„ÂÃ‚Â°Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â¾Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Â°Ã„ÂÃ‚ÂµÃ„ÂÃ‚Âµ Ã„ÂÃ‚Â²Ãƒâ€˜Ã¢â€šÂ¬Ã„ÂÃ‚ÂµÃ„ÂÃ‚Â¼Ãƒâ€˜Ã‚Â Ã„ÂÃ‚Â¼Ãƒâ€˜Ã¢â‚¬Â¹ Ã„ÂÃ‚Â½Ã„ÂÃ‚Âµ Ã„ÂÃ‚Â¾Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã†â€™Ãƒâ€˜Ã¢â‚¬Â°Ã„ÂÃ‚ÂµÃƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â²Ã„ÂÃ‚Â»Ãƒâ€˜Ã‚ÂÃ„ÂÃ‚ÂµÃ„ÂÃ‚Â¼ Ã„ÂÃ‚Â´Ã„ÂÃ‚Â¾Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â°Ã„ÂÃ‚Â²Ã„ÂÃ‚ÂºÃƒâ€˜Ã†â€™ Ã„ÂÃ‚Â² Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Å¡Ãƒâ€˜Ã†â€™ Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Å¡Ãƒâ€˜Ã¢â€šÂ¬Ã„ÂÃ‚Â°Ã„ÂÃ‚Â½Ãƒâ€˜Ã†â€™.',
        'UA' => 'Ã„ÂÃ‚ÂÃ„ÂÃ‚Â°Ãƒâ€˜Ã¢â€šÂ¬Ã„ÂÃ‚Â°Ã„ÂÃ‚Â·Ãƒâ€˜Ã¢â‚¬â€œ Ã„ÂÃ‚Â¼Ã„ÂÃ‚Â¸ Ã„ÂÃ‚Â½Ã„ÂÃ‚Âµ Ã„ÂÃ‚Â·Ã„ÂÃ‚Â´Ãƒâ€˜Ã¢â‚¬â€œÃ„ÂÃ‚Â¹Ãƒâ€˜Ã‚ÂÃ„ÂÃ‚Â½Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬ÂÃ„ÂÃ‚Â¼Ã„ÂÃ‚Â¾ Ã„ÂÃ‚Â´Ã„ÂÃ‚Â¾Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â°Ã„ÂÃ‚Â²Ã„ÂÃ‚ÂºÃƒâ€˜Ã†â€™ Ã„ÂÃ‚Â´Ã„ÂÃ‚Â¾ Ãƒâ€˜Ã¢â‚¬Â Ãƒâ€˜Ã¢â‚¬â€œÃƒâ€˜Ã¢â‚¬ÂÃƒâ€˜Ã¢â‚¬â€ Ã„ÂÃ‚ÂºÃƒâ€˜Ã¢â€šÂ¬Ã„ÂÃ‚Â°Ãƒâ€˜Ã¢â‚¬â€Ã„ÂÃ‚Â½Ã„ÂÃ‚Â¸.',
        'CZ' => 'V souÃƒâ€Ã‚ÂasnÃƒÆ’Ã‚Â© dobÃƒâ€Ã¢â‚¬Âº do tÃƒÆ’Ã‚Â©to zemÃƒâ€Ã¢â‚¬Âº nedoruÃƒâ€Ã‚Âujeme.',
        'SK' => 'V sÃƒÆ’Ã‚ÂºÃƒâ€Ã‚Âasnosti do tejto krajiny nedoruÃƒâ€Ã‚Âujeme.',
        'RO' => 'Momentan nu livrÃƒâ€Ã†â€™m ÃƒÆ’Ã‚Â®n aceastÃƒâ€Ã†â€™ ÃƒË†Ã¢â‚¬ÂºarÃƒâ€Ã†â€™.',
        'HU' => 'Jelenleg nem szÃƒÆ’Ã‚Â¡llÃƒÆ’Ã‚Â­tunk ebbe az orszÃƒÆ’Ã‚Â¡gba.',
        'BG' => 'Ã„ÂÃ¢â‚¬â„¢ Ã„ÂÃ‚Â¼Ã„ÂÃ‚Â¾Ã„ÂÃ‚Â¼Ã„ÂÃ‚ÂµÃ„ÂÃ‚Â½Ãƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â° Ã„ÂÃ‚Â½Ã„ÂÃ‚Âµ Ã„ÂÃ‚Â´Ã„ÂÃ‚Â¾Ãƒâ€˜Ã‚ÂÃƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â°Ã„ÂÃ‚Â²Ãƒâ€˜Ã‚ÂÃ„ÂÃ‚Â¼Ã„ÂÃ‚Âµ Ã„ÂÃ‚Â´Ã„ÂÃ‚Â¾ Ãƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â°Ã„ÂÃ‚Â·Ã„ÂÃ‚Â¸ Ã„ÂÃ‚Â´Ãƒâ€˜Ã…Â Ãƒâ€˜Ã¢â€šÂ¬Ã„ÂÃ‚Â¶Ã„ÂÃ‚Â°Ã„ÂÃ‚Â²Ã„ÂÃ‚Â°.',
        'HR' => 'Trenutno ne isporuÃƒâ€Ã‚Âujemo u ovu zemlju.',
        'RS' => 'Ã„ÂÃ‚Â¢Ãƒâ€˜Ã¢â€šÂ¬Ã„ÂÃ‚ÂµÃ„ÂÃ‚Â½Ãƒâ€˜Ã†â€™Ãƒâ€˜Ã¢â‚¬Å¡Ã„ÂÃ‚Â½Ã„ÂÃ‚Â¾ Ã„ÂÃ‚Â½Ã„ÂÃ‚Âµ Ã„ÂÃ‚Â¸Ãƒâ€˜Ã‚ÂÃ„ÂÃ‚Â¿Ã„ÂÃ‚Â¾Ãƒâ€˜Ã¢â€šÂ¬Ãƒâ€˜Ã†â€™Ãƒâ€˜Ã¢â‚¬Â¡Ãƒâ€˜Ã†â€™Ãƒâ€˜Ã‹Å“Ã„ÂÃ‚ÂµÃ„ÂÃ‚Â¼Ã„ÂÃ‚Â¾ Ãƒâ€˜Ã†â€™ Ã„ÂÃ‚Â¾Ã„ÂÃ‚Â²Ãƒâ€˜Ã†â€™ Ã„ÂÃ‚Â·Ã„ÂÃ‚ÂµÃ„ÂÃ‚Â¼Ãƒâ€˜Ã¢â€Â¢Ãƒâ€˜Ã†â€™.',
        'GR' => 'ÃƒÂÃ‚Â ÃƒÂÃ‚ÂÃƒÂÃ‚Â¿ÃƒÂÃ¢â‚¬Å¡ ÃƒÂÃ¢â‚¬ÂÃƒÂÃ‚Â¿ ÃƒÂÃ¢â€šÂ¬ÃƒÂÃ‚Â±ÃƒÂÃ‚ÂÃƒÂÃ…â€™ÃƒÂÃ‚Â½ ÃƒÂÃ‚Â´ÃƒÂÃ‚ÂµÃƒÂÃ‚Â½ ÃƒÂÃ‚Â±ÃƒÂÃ¢â€šÂ¬ÃƒÂÃ‚Â¿ÃƒÂÃ†â€™ÃƒÂÃ¢â‚¬ÂÃƒÂÃ‚Â­ÃƒÂÃ‚Â»ÃƒÂÃ‚Â»ÃƒÂÃ‚Â¿ÃƒÂÃ¢â‚¬Â¦ÃƒÂÃ‚Â¼ÃƒÂÃ‚Âµ ÃƒÂÃ†â€™ÃƒÂÃ‚Âµ ÃƒÂÃ‚Â±ÃƒÂÃ¢â‚¬Â¦ÃƒÂÃ¢â‚¬ÂÃƒÂÃ‚Â® ÃƒÂÃ¢â‚¬ÂÃƒÂÃ‚Â· ÃƒÂÃ¢â‚¬Â¡ÃƒÂÃ‚ÂÃƒÂÃ‚ÂÃƒÂÃ‚Â±.',
        'SE' => 'Vi levererar fÃƒÆ’Ã‚Â¶r nÃƒÆ’Ã‚Â¤rvarande inte till detta land.',
        'NO' => 'Vi leverer for ÃƒÆ’Ã‚Â¸yeblikket ikke til dette landet.',
        'DK' => 'Vi leverer i ÃƒÆ’Ã‚Â¸jeblikket ikke til dette land.',
        'FI' => 'Emme tÃƒÆ’Ã‚Â¤llÃƒÆ’Ã‚Â¤ hetkellÃƒÆ’Ã‚Â¤ toimita tÃƒÆ’Ã‚Â¤hÃƒÆ’Ã‚Â¤n maahan.',
        'SA' => 'Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â§ Ãƒâ„¢Ã¢â‚¬Â Ãƒâ„¢Ã¢â‚¬Å¡Ãƒâ„¢Ã‹â€ Ãƒâ„¢Ã¢â‚¬Â¦ ÃƒËœÃ‚Â­ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒâ„¢Ã…Â ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬Â¹ ÃƒËœÃ‚Â¨ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â´ÃƒËœÃ‚Â­Ãƒâ„¢Ã¢â‚¬Â  ÃƒËœÃ‚Â¥Ãƒâ„¢Ã¢â‚¬ÂÃƒâ„¢Ã¢â‚¬Â° Ãƒâ„¢Ã¢â‚¬Â¡ÃƒËœÃ‚Â°ÃƒËœÃ‚Â§ ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â¨Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â¯.',
        'AE' => 'Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â§ Ãƒâ„¢Ã¢â‚¬Â Ãƒâ„¢Ã¢â‚¬Å¡Ãƒâ„¢Ã‹â€ Ãƒâ„¢Ã¢â‚¬Â¦ ÃƒËœÃ‚Â­ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒâ„¢Ã…Â ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬Â¹ ÃƒËœÃ‚Â¨ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â´ÃƒËœÃ‚Â­Ãƒâ„¢Ã¢â‚¬Â  ÃƒËœÃ‚Â¥Ãƒâ„¢Ã¢â‚¬ÂÃƒâ„¢Ã¢â‚¬Â° Ãƒâ„¢Ã¢â‚¬Â¡ÃƒËœÃ‚Â°ÃƒËœÃ‚Â§ ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â¨Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â¯.',
        'EG' => 'Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â§ Ãƒâ„¢Ã¢â‚¬Â Ãƒâ„¢Ã¢â‚¬Å¡Ãƒâ„¢Ã‹â€ Ãƒâ„¢Ã¢â‚¬Â¦ ÃƒËœÃ‚Â­ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒâ„¢Ã…Â ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬Â¹ ÃƒËœÃ‚Â¨ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â´ÃƒËœÃ‚Â­Ãƒâ„¢Ã¢â‚¬Â  ÃƒËœÃ‚Â¥Ãƒâ„¢Ã¢â‚¬ÂÃƒâ„¢Ã¢â‚¬Â° Ãƒâ„¢Ã¢â‚¬Â¡ÃƒËœÃ‚Â°ÃƒËœÃ‚Â§ ÃƒËœÃ‚Â§Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â¨Ãƒâ„¢Ã¢â‚¬ÂÃƒËœÃ‚Â¯.',
        'IL' => 'Ãƒâ€”Ã‚ÂÃƒâ€”Ã‚Â Ãƒâ€”Ã¢â‚¬â€Ãƒâ€”Ã‚Â Ãƒâ€”Ã¢â‚¬Â¢ Ãƒâ€”Ã…â€œÃƒâ€”Ã‚Â Ãƒâ€”Ã‚ÂÃƒâ€”Ã‚Â©Ãƒâ€”Ã…â€œÃƒâ€”Ã¢â‚¬â€Ãƒâ€”Ã¢â€Â¢Ãƒâ€”Ã‚Â Ãƒâ€”Ã¢â‚¬ÂºÃƒâ€”Ã‚Â¨Ãƒâ€”Ã¢â‚¬â„¢Ãƒâ€”Ã‚Â¢ Ãƒâ€”Ã…â€œÃƒâ€”Ã‚ÂÃƒâ€”Ã¢â‚¬Å“Ãƒâ€”Ã¢â€Â¢Ãƒâ€”Ã‚Â Ãƒâ€”Ã¢â‚¬Â Ãƒâ€”Ã¢â‚¬â€œÃƒâ€”Ã¢â‚¬Â¢.',
        'IN' => 'ÃƒÂ Ã‚Â¤Ã‚ÂµÃƒÂ Ã‚Â¤Ã‚Â°ÃƒÂ Ã‚Â¥Ã‚ÂÃƒÂ Ã‚Â¤Ã‚Â¤ÃƒÂ Ã‚Â¤Ã‚Â®ÃƒÂ Ã‚Â¤Ã‚Â¾ÃƒÂ Ã‚Â¤Ã‚Â¨ ÃƒÂ Ã‚Â¤Ã‚Â®ÃƒÂ Ã‚Â¥Ã¢â‚¬Â¡ÃƒÂ Ã‚Â¤Ã¢â‚¬Å¡ ÃƒÂ Ã‚Â¤Ã‚Â¹ÃƒÂ Ã‚Â¤Ã‚Â® ÃƒÂ Ã‚Â¤Ã¢â‚¬Â¡ÃƒÂ Ã‚Â¤Ã‚Â¸ ÃƒÂ Ã‚Â¤Ã‚Â¦ÃƒÂ Ã‚Â¥Ã¢â‚¬Â¡ÃƒÂ Ã‚Â¤Ã‚Â¶ ÃƒÂ Ã‚Â¤Ã‚Â®ÃƒÂ Ã‚Â¥Ã¢â‚¬Â¡ÃƒÂ Ã‚Â¤Ã¢â‚¬Å¡ ÃƒÂ Ã‚Â¤Ã‚Â¶ÃƒÂ Ã‚Â¤Ã‚Â¿ÃƒÂ Ã‚Â¤Ã‚ÂªÃƒÂ Ã‚Â¤Ã‚Â¿ÃƒÂ Ã‚Â¤Ã¢â‚¬Å¡ÃƒÂ Ã‚Â¤Ã¢â‚¬â€ ÃƒÂ Ã‚Â¤Ã‚Â¨ÃƒÂ Ã‚Â¤Ã‚Â¹ÃƒÂ Ã‚Â¥Ã¢â€šÂ¬ÃƒÂ Ã‚Â¤Ã¢â‚¬Å¡ ÃƒÂ Ã‚Â¤Ã¢â‚¬Â¢ÃƒÂ Ã‚Â¤Ã‚Â°ÃƒÂ Ã‚Â¤Ã‚Â¤ÃƒÂ Ã‚Â¥Ã¢â‚¬Â¡ ÃƒÂ Ã‚Â¤Ã‚Â¹ÃƒÂ Ã‚Â¥Ã‹â€ ÃƒÂ Ã‚Â¤Ã¢â‚¬Å¡ÃƒÂ Ã‚Â¥Ã‚Â¤',
        'TH' => 'ÃƒÂ Ã‚Â¸Ã¢â‚¬Å¡ÃƒÂ Ã‚Â¸Ã¢â‚¬Å“ÃƒÂ Ã‚Â¸Ã‚Â°ÃƒÂ Ã‚Â¸Ã¢â€Â¢ÃƒÂ Ã‚Â¸Ã‚ÂµÃƒÂ Ã‚Â¹Ã¢â‚¬Â°ÃƒÂ Ã‚Â¹Ã¢â€šÂ¬ÃƒÂ Ã‚Â¸Ã‚Â£ÃƒÂ Ã‚Â¸Ã‚Â²ÃƒÂ Ã‚Â¹Ã¢â‚¬ÂÃƒÂ Ã‚Â¸Ã‚Â¡ÃƒÂ Ã‚Â¹Ã‹â€ ÃƒÂ Ã‚Â¸Ã‹â€ ÃƒÂ Ã‚Â¸Ã‚Â±ÃƒÂ Ã‚Â¸Ã¢â‚¬ÂÃƒÂ Ã‚Â¸Ã‚ÂªÃƒÂ Ã‚Â¹Ã‹â€ ÃƒÂ Ã‚Â¸Ã¢â‚¬Â¡ÃƒÂ Ã‚Â¹Ã¢â‚¬ÂÃƒÂ Ã‚Â¸Ã¢â‚¬ÂºÃƒÂ Ã‚Â¸Ã‚Â¢ÃƒÂ Ã‚Â¸Ã‚Â±ÃƒÂ Ã‚Â¸Ã¢â‚¬Â¡ÃƒÂ Ã‚Â¸Ã¢â‚¬ÂºÃƒÂ Ã‚Â¸Ã‚Â£ÃƒÂ Ã‚Â¸Ã‚Â°ÃƒÂ Ã‚Â¹Ã¢â€šÂ¬ÃƒÂ Ã‚Â¸Ã¢â‚¬â€ÃƒÂ Ã‚Â¸Ã‚Â¨ÃƒÂ Ã‚Â¸Ã¢â€Â¢ÃƒÂ Ã‚Â¸Ã‚ÂµÃƒÂ Ã‚Â¹Ã¢â‚¬Â°',
        'VN' => 'HiÃƒÂ¡Ã‚Â»Ã¢â‚¬Â¡n tÃƒÂ¡Ã‚ÂºÃ‚Â¡i chÃƒÆ’Ã‚Âºng tÃƒÆ’Ã‚Â´i khÃƒÆ’Ã‚Â´ng giao hÃƒÆ’Ã‚Â ng Ãƒâ€Ã¢â‚¬ËœÃƒÂ¡Ã‚ÂºÃ‚Â¿n quÃƒÂ¡Ã‚Â»Ã¢â‚¬Ëœc gia nÃƒÆ’Ã‚Â y.',
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
        <div style="font-size: 28px; margin-bottom: 8px;">Ã„Å¸Ã…Â¸Ã…Â¡Ã‚Â«</div>
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
                            '<div style="font-size:28px;margin-bottom:8px;">Ã„Å¸Ã…Â¸Ã…Â¡Ã‚Â«</div>' +
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
 * ÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚Â
 * PLACE ORDER BUTTON ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Disable until all required fields are filled
 * and Privacy Policy is accepted
 * ÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚ÂÃƒÂ¢Ã¢â‚¬Â¢Ã‚Â
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

    // Comment & Review Toggle
    register_setting('xepmarket2_settings_group', 'xepmarket2_enable_comments');

    // Payment Tokens Registration
    for ($i = 1; $i <= 4; $i++) {
        register_setting('xepmarket2_settings_group', 'xepmarket2_token_name_' . $i);
        register_setting('xepmarket2_settings_group', 'xepmarket2_token_status_' . $i);
    }

    // Admin panel banner removed (no longer used in settings UI)
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

    // Menu Settings (web / mobile menu selection)
    register_setting('xepmarket2_settings_group', 'xepmarket2_menu_web', array(
        'sanitize_callback' => function ($v) {
            if ($v === '' || $v === null) return '';
            $id = absint($v);
            return $id && is_nav_menu($id) ? (string) $id : '';
        },
    ));
    register_setting('xepmarket2_settings_group', 'xepmarket2_menu_mobile', array(
        'sanitize_callback' => function ($v) {
            if ($v === '' || $v === null) return '';
            $id = absint($v);
            return $id && is_nav_menu($id) ? (string) $id : '';
        },
    ));

    // Mobile bottom bar (icon nav) visibility
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_show_home');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_show_shop');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_show_cart');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_show_account');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_icon_home');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_icon_shop');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_icon_cart');
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_icon_account');

    // Mobile bottom bar: additional custom menu items (up to 5; count = 5 minus active defaults)
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_custom_items', array(
        'type' => 'array',
        'sanitize_callback' => 'xepmarket2_sanitize_mobile_nav_custom_items',
    ));

    // Mobile bottom bar: order of items (array of 5: home, shop, cart, account, custom_0..custom_4)
    register_setting('xepmarket2_settings_group', 'xepmarket2_mobile_nav_order', array(
        'type' => 'array',
        'sanitize_callback' => 'xepmarket2_sanitize_mobile_nav_order',
    ));

    // Checkout Customization Toggles
    $checkout_fields = ['first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email', 'telegram'];
    foreach ($checkout_fields as $field) {
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_' . $field);
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_name_' . $field);
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_req_' . $field);
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_order_' . $field);
        register_setting('xepmarket2_settings_group', 'xepmarket2_chk_width_' . $field);
    }

    // Register custom dynamic fields array (JSON string)
    register_setting('xepmarket2_settings_group', 'xepmarket2_chk_custom_fields', array(
        'type' => 'string',
        'sanitize_callback' => 'xepmarket2_sanitize_custom_fields_json',
    ));

    // Telegram Bot (theme-integrated; same options as standalone plugin)
    register_setting('xepmarket2_settings_group', 'xep_tg_bot_enabled');
    register_setting('xepmarket2_settings_group', 'xep_tg_bot_token', array(
        'sanitize_callback' => function ($v) { return is_string($v) ? trim($v) : ''; }
    ));
    register_setting('xepmarket2_settings_group', 'xep_tg_bot_chat_id', array(
        'sanitize_callback' => function ($v) { return is_string($v) ? trim($v) : ''; }
    ));
    register_setting('xepmarket2_settings_group', 'xep_tg_bot_msg_new_order');
    register_setting('xepmarket2_settings_group', 'xep_tg_bot_msg_status_changed');

    // Shipping Exclusion Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_shipping_excluded_countries', array(
        'type' => 'array',
        'sanitize_callback' => function($input) {
            return is_array($input) ? array_map('sanitize_text_field', $input) : array();
        }
    ));

    // Shipping Rates Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_shipping_base_cost');
    register_setting('xepmarket2_settings_group', 'xepmarket2_shipping_zones', array(
        'sanitize_callback' => 'xepmarket2_sanitize_shipping_zones_json'
    ));

    // Affiliate (theme-integrated; same options as standalone plugin)
    register_setting('xepmarket2_settings_group', 'omnixep_affiliate_rate');
    register_setting('xepmarket2_settings_group', 'omnixep_affiliate_cookie_days');

    // Coupons Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_coupons_enabled');
    register_setting('xepmarket2_settings_group', 'xepmarket2_coupons_json', array(
        'type' => 'string',
        'sanitize_callback' => 'xepmarket2_sanitize_coupons_json',
    ));

    // Legal Contracts Settings
    register_setting('xepmarket2_settings_group', 'xepmarket2_legal_contracts_json', array(
        'type' => 'string',
        'sanitize_callback' => 'xepmarket2_sanitize_contracts_json',
    ));
    register_setting('xepmarket2_settings_group', 'xepmarket2_privacy_policy_label');
    register_setting('xepmarket2_settings_group', 'xepmarket2_privacy_policy_required');

    // Mail Settings (SMTP)
    register_setting('xepmarket2_settings_group', 'xep_smtp_enable');
    register_setting('xepmarket2_settings_group', 'xep_smtp_host');
    register_setting('xepmarket2_settings_group', 'xep_smtp_port');
    register_setting('xepmarket2_settings_group', 'xep_smtp_auth');
    register_setting('xepmarket2_settings_group', 'xep_smtp_encryption');
    register_setting('xepmarket2_settings_group', 'xep_smtp_username');
    register_setting('xepmarket2_settings_group', 'xep_smtp_password');
    register_setting('xepmarket2_settings_group', 'xep_smtp_from_email');
    register_setting('xepmarket2_settings_group', 'xep_smtp_from_name');
    register_setting('xepmarket2_settings_group', 'xep_smtp_insecure');
}

/**
 * Sanitize contracts JSON
 */
function xepmarket2_sanitize_contracts_json($input) {
    if (empty($input)) return '[]';
    $decoded = json_decode(wp_unslash($input), true);
    if (!is_array($decoded)) return '[]';
    
    $clean = array();
    foreach ($decoded as $contract) {
        if (!isset($contract['name'])) continue;
        $clean[] = array(
            'name'     => sanitize_text_field($contract['name']),
            'page_id'  => is_numeric($contract['page_id']) ? intval($contract['page_id']) : 0,
            'required' => (isset($contract['required']) && $contract['required'] == '1') ? '1' : '0',
        );
    }
    return wp_json_encode($clean);
}

/**
 * Sanitize coupons JSON
 */
function xepmarket2_sanitize_coupons_json($input) {
    if (empty($input)) return '[]';
    $decoded = json_decode(wp_unslash($input), true);
    if (!is_array($decoded)) return '[]';
    
    $clean = array();
    foreach ($decoded as $coupon) {
        if (!isset($coupon['code'])) continue;
        $clean[] = array(
            'code'  => sanitize_text_field($coupon['code']),
            'rate'  => is_numeric($coupon['rate']) ? floatval($coupon['rate']) : 0,
        );
    }
    return wp_json_encode($clean);
}

/**
 * WooCommerce Integration: Intercept coupon code validation to support theme-defined coupons
 */
add_filter('woocommerce_get_shop_coupon_data', 'xepmarket2_apply_theme_coupons', 10, 2);
function xepmarket2_apply_theme_coupons($data, $code) {
    if (get_option('xepmarket2_coupons_enabled', '0') !== '1') {
        return $data;
    }

    $coupons_json = get_option('xepmarket2_coupons_json', '[]');
    $coupons = json_decode($coupons_json, true);
    if (!is_array($coupons)) {
        return $data;
    }

    foreach ($coupons as $coupon) {
        if (strcasecmp($coupon['code'], $code) === 0) {
            // Virtual WooCommerce coupon data
            return array(
                'id'                         => 12345678, // Virtual ID
                'type'                       => 'percent',
                'amount'                     => floatval($coupon['rate']),
                'coupon_amount'              => floatval($coupon['rate']),
                'discount_type'              => 'percent',
                'individual_use'             => false,
                'product_ids'                => array(),
                'exclude_product_ids'        => array(),
                'usage_limit'                => 0,
                'usage_limit_per_user'       => 0,
                'limit_usage_to_x_items'     => 0,
                'expiry_date'                => '',
                'free_shipping'              => false,
                'product_categories'         => array(),
                'exclude_product_categories' => array(),
                'exclude_sale_items'         => false,
                'minimum_amount'             => '',
                'maximum_amount'             => '',
                'customer_email'             => array(),
                'virtual'                    => true // Identification for custom logic
            );
        }
    }

    return $data;
}

/**
 * WooCommerce Integration: Force enable coupons on front-end if theme coupons are active
 */
add_filter('woocommerce_coupons_enabled', function($enabled) {
    return get_option('xepmarket2_coupons_enabled', '0') === '1';
});

/**
 * WooCommerce Integration: Display custom legal contracts at checkout
 */
add_action('woocommerce_review_order_before_submit', 'xepmarket2_display_checkout_contracts', 10);
function xepmarket2_display_checkout_contracts() {
    $contracts_json = get_option('xepmarket2_legal_contracts_json', '[]');
    $contracts = json_decode($contracts_json, true);
    if (empty($contracts) || !is_array($contracts)) return;

    foreach ($contracts as $index => $contract) {
        $id = "xep_contract_{$index}";
        $modal_id = "xep_contract_modal_{$index}";
        $label_text = esc_html($contract['name']);
        
        if ($contract['page_id'] > 0) {
            $label_text = sprintf('<a href="#" class="xep-custom-contract-trigger" data-modal="%s" style="color: #00f2ff !important; text-decoration: underline !important; font-weight: 700 !important; margin-left: 5px;">%s</a>', esc_attr($modal_id), esc_html($contract['name']));
        }

        echo '<div class="xep-privacy-policy-wrap" style="width: 100% !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; border-radius: 20px !important; background: rgba(255, 255, 255, 0.02) !important; margin: 15px 0 !important;">';
        echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" style="display: flex !important; align-items: center !important; padding: 25px !important; margin: 0 !important; gap: 15px !important; width: 100% !important; box-sizing: border-box !important; cursor: pointer;">';
        echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox xep-custom-contract-checkbox" name="'.esc_attr($id).'" id="'.esc_attr($id).'" value="1" '.(($contract['required'] == '1') ? 'required' : '').' data-name="'.esc_attr($contract['name']).'" style="width: 18px !important; height: 18px !important; margin: 0 !important; flex-shrink: 0;" />';
        echo '<span style="display: inline-block !important; flex-grow: 1 !important; margin: 0 !important; color: rgba(255, 255, 255, 0.85) !important; font-size: 15px !important; text-align: left !important;">';
        echo sprintf(__('By completing your order, you agree to: %s', 'xepmarket2'), $label_text);
        if ($contract['required'] == '1') {
            echo ' <abbr class="required" title="required" style="color: #ff453a !important; text-decoration: none !important; margin-left: 5px !important;">*</abbr>';
        }
        echo '</span>';
        echo '</label></div>';
    }
}

/**
 * WooCommerce Integration: Render modals for custom legal contracts
 */
add_action('wp_footer', 'xepmarket2_render_custom_contract_modals', 60);
function xepmarket2_render_custom_contract_modals() {
    if (!is_checkout() || is_wc_endpoint_url('order-received')) return;

    $contracts_json = get_option('xepmarket2_legal_contracts_json', '[]');
    $contracts = json_decode($contracts_json, true);
    if (empty($contracts) || !is_array($contracts)) return;

    foreach ($contracts as $index => $contract) {
        $modal_id = "xep_contract_modal_{$index}";
        $page_id = $contract['page_id'];
        if ($page_id <= 0) continue;
        ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="xep-privacy-overlay xep-custom-contract-modal" style="display:none;">
            <div class="xep-modal-container">
                <div class="xep-modal-header">
                    <h2><?php echo esc_html($contract['name']); ?></h2>
                    <button type="button" class="xep-modal-close-btn">&times;</button>
                </div>
                <div class="xep-modal-body">
                    <?php
                    $post = get_post($page_id);
                    if ($post) {
                        echo apply_filters('the_content', $post->post_content);
                    } else {
                        echo '<p style="text-align:center; padding: 50px 0;">' . __('Contract content not found.', 'xepmarket2') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
    <script>
    (function($){
        $(document).on('click', '.xep-custom-contract-trigger', function(e){
            e.preventDefault();
            var modalId = $(this).data('modal');
            $('#' + modalId).css('display', 'flex').hide().fadeIn(200);
            $('body').css('overflow', 'hidden');
        });
        $(document).on('click', '.xep-custom-contract-modal .xep-modal-close-btn, .xep-custom-contract-modal', function(e){
            if($(e.target).closest('.xep-modal-container').length && !$(e.target).hasClass('xep-modal-close-btn')) return;
            $(this).closest('.xep-custom-contract-modal').fadeOut(200);
            $('body').css('overflow', 'auto');
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * WooCommerce Integration: Validate custom legal contracts at checkout
 */
add_action('woocommerce_checkout_process', 'xepmarket2_validate_checkout_contracts');
function xepmarket2_validate_checkout_contracts() {
    $contracts_json = get_option('xepmarket2_legal_contracts_json', '[]');
    $contracts = json_decode($contracts_json, true);
    if (empty($contracts) || !is_array($contracts)) return;

    foreach ($contracts as $index => $contract) {
        if ($contract['required'] == '1') {
            $id = "xep_contract_{$index}";
            if (!isset($_POST[$id]) || empty($_POST[$id])) {
                wc_add_notice(sprintf(__('Please accept the %s to proceed.', 'woocommerce'), '<strong>' . esc_html($contract['name']) . '</strong>'), 'error');
            }
        }
    }
}

function xepmarket2_sanitize_shipping_zones_json($input) {
    if (empty($input)) return '[]';
    $decoded = json_decode(wp_unslash($input), true);
    if (!is_array($decoded)) return '[]';
    
    $clean = array();
    foreach ($decoded as $zone) {
        if (!isset($zone['id'])) continue;
        $countries = isset($zone['countries']) && is_array($zone['countries']) ? array_map('sanitize_text_field', $zone['countries']) : array();
        $clean[] = array(
            'id' => sanitize_key($zone['id']),
            'name' => sanitize_text_field($zone['name'] ?? ''),
            'cost' => is_numeric($zone['cost']) ? floatval($zone['cost']) : 0,
            'countries' => $countries
        );
    }
    return wp_json_encode($clean);
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
            'width' => (isset($field['width']) && $field['width'] === 'full') ? 'full' : 'half',
        );
    }
    return wp_json_encode($clean);
}

/**
 * Sanitize mobile bottom bar custom items. Max 5 items (bar total max 5; slots = 5 - active defaults).
 */
function xepmarket2_sanitize_mobile_nav_custom_items($input) {
    if (!is_array($input)) return get_option('xepmarket2_mobile_nav_custom_items', array());
    $out = array();
    foreach ($input as $row) {
        if (count($out) >= 5) break;
        if (!is_array($row)) continue;
        $url = isset($row['url']) ? esc_url_raw($row['url']) : '';
        $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
        $icon = isset($row['icon']) ? sanitize_text_field($row['icon']) : 'dashicons dashicons-admin-links';
        $show = !empty($row['show']);
        $out[] = array('url' => $url, 'label' => $label, 'icon' => $icon, 'show' => $show);
    }
    return $out;
}

/**
 * Sanitize mobile bottom bar order. Exactly 5 slots; each value: home, shop, cart, account, custom_0..custom_4.
 */
function xepmarket2_sanitize_mobile_nav_order($input) {
    $allowed = array('home', 'shop', 'cart', 'account', 'custom_0', 'custom_1', 'custom_2', 'custom_3', 'custom_4');
    $default = array('home', 'shop', 'cart', 'account', 'custom_0');
    if (!is_array($input)) return $default;
    $out = array();
    for ($i = 0; $i < 5; $i++) {
        $v = isset($input[$i]) ? sanitize_text_field($input[$i]) : '';
        $out[] = in_array($v, $allowed, true) ? $v : (isset($default[$i]) ? $default[$i] : 'custom_0');
    }
    return array_slice($out, 0, 5);
}

add_action('admin_init', 'xepmarket2_settings_init');

/**
 * AJAX: Save checkout field order (when dropping into empty slot in grid)
 */
function xepmarket2_ajax_save_checkout_order() {
    check_ajax_referer('xep_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $orders = isset($_POST['orders']) && is_array($_POST['orders']) ? array_map('absint', $_POST['orders']) : array();
    $allowed = array('first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email', 'telegram');
    foreach ($orders as $field_id => $order) {
        if (in_array($field_id, $allowed, true) && $order >= 1) update_option('xepmarket2_chk_order_' . $field_id, $order);
    }
    wp_send_json_success();
}
add_action('wp_ajax_xep_save_checkout_order', 'xepmarket2_ajax_save_checkout_order');

function xepmarket2_get_option($key, $default = '')
{
    $option = get_option($key);
    return !empty($option) ? $option : $default;
}

function xepmarket2_settings_page()
{
    ?>
    <div class="xep-admin-wrap">
        <?php /* Admin panel header banner removed */ ?>

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
                    <div class="xep-nav-item" data-tab="tab-logos">
                        <i class="fas fa-id-badge"></i> Brand &amp; Logos
                    </div>
                    <div class="xep-nav-item" data-tab="tab-social">
                        <i class="fas fa-share-nodes"></i> Social Media
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
                    <div class="xep-nav-item" data-tab="tab-flash-deals">
                        <i class="fas fa-bolt"></i> Flash Deals
                    </div>
                    <div class="xep-nav-item" data-tab="tab-modules">
                        <i class="fas fa-puzzle-piece"></i> Theme Modules
                    </div>
                    <div class="xep-nav-item" data-tab="tab-menus">
                        <i class="fas fa-bars"></i> Menu Settings
                    </div>
                    <div class="xep-nav-item" data-tab="tab-alisync">
                        <i class="fas fa-sync-alt"></i> AliSync Helper
                    </div>
                    <div class="xep-nav-item" data-tab="tab-support">
                        <i class="fas fa-headset"></i> Support & Contact
                    </div>
                    <div class="xep-nav-item" data-tab="tab-telegram">
                        <i class="dashicons dashicons-format-chat"></i> Telegram Bot
                    </div>
                    <div class="xep-nav-item" data-tab="tab-affiliate">
                        <i class="fas fa-handshake"></i> Affiliate
                    </div>
                    <div class="xep-nav-item" data-tab="tab-checkout">
                        <i class="fas fa-shopping-cart"></i> Checkout Customization
                    </div>
                    <div class="xep-nav-item" data-tab="tab-shipping">
                        <i class="fas fa-truck"></i> Shipping Rates & Limits
                    </div>
                    <div class="xep-nav-item" data-tab="tab-coupons">
                        <i class="fas fa-ticket-alt"></i> Coupons
                    </div>
                    <div class="xep-nav-item" data-tab="tab-legal">
                        <i class="fas fa-file-contract"></i> Legal Contracts
                    </div>
                    <div class="xep-nav-item" data-tab="tab-seo">
                        <i class="fas fa-search"></i> SEO & AI Settings
                    </div>
                    <div class="xep-nav-item" data-tab="tab-demo"
                        style="border-top: 1px solid var(--admin-border); padding-top: 15px; margin-top: 10px; color: var(--admin-primary);">
                        <i class="fas fa-magic"></i> Demo Setup
                    </div>
                    <div class="xep-nav-item" data-tab="tab-mail">
                        <i class="fas fa-envelope"></i> Mail Settings
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

                    <!-- Tab: Legal Contracts -->
                    <div id="tab-legal" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Checkout Agreements & Contracts</h3>
                            <p class="description" style="margin-bottom: 25px;">Manage legal documents (Terms, Privacy Policy, etc.) that customers must accept before placing an order.</p>

                            <!-- Standard Privacy Policy Section -->
                            <div class="xep-section-card" style="background: rgba(0, 242, 255, 0.03); border: 1px solid rgba(0, 242, 255, 0.1); margin-bottom: 30px; padding: 20px;">
                                <h4 style="margin-top: 0; color: #00f2ff; display: flex; align-items: center; gap: 8px;"><i class="fas fa-shield-alt"></i> Standard Privacy Policy</h4>
                                <div class="xep-grid-2">
                                    <div class="xep-form-group">
                                        <label>Checkbox Label Text</label>
                                        <input type="text" name="xepmarket2_privacy_policy_label" value="<?php echo esc_attr(get_option('xepmarket2_privacy_policy_label', 'Privacy Policy')); ?>" placeholder="Privacy Policy" />
                                        <p class="description">How the link appears in the agreement text.</p>
                                    </div>
                                    <div class="xep-form-group" style="display: flex; align-items: center; gap: 15px; padding-top: 25px;">
                                        <label class="xep-switch">
                                            <input type="checkbox" name="xepmarket2_privacy_policy_required" value="1" <?php checked(1, get_option('xepmarket2_privacy_policy_required', '1')); ?> />
                                            <span class="xep-slider"></span>
                                        </label>
                                        <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">Required for Checkout</span>
                                    </div>
                                </div>
                                <p class="description" style="margin-top: 10px;">Note: This uses the official WordPress Privacy Policy page. Link: <a href="<?php echo admin_url('options-privacy.php'); ?>" target="_blank" style="color: #00f2ff;">Change Policy Page</a></p>
                            </div>

                            <div class="xep-form-group">
                                <label>Contracts List</label>
                                <div id="xep-contracts-container" style="display: flex; flex-direction: column; gap: 12px;">
                                    <?php
                                    $contracts_json = get_option('xepmarket2_legal_contracts_json', '[]');
                                    $contracts = json_decode($contracts_json, true);
                                    if (!is_array($contracts)) $contracts = array();
                                    $all_pages = get_pages();
                                    ?>
                                    
                                    <!-- Hidden Template for JS Cloning -->
                                    <div class="xep-contract-row template" style="display: none; gap: 10px; align-items: center; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 12px; border: 1px solid var(--admin-border);">
                                        <div style="flex: 2;">
                                            <input type="text" class="contract-name" value="" placeholder="Name (e.g. Terms of Service)" />
                                        </div>
                                        <div style="flex: 2;">
                                            <select class="contract-page-id">
                                                <option value="0">Select Page...</option>
                                                <?php foreach ($all_pages as $page): ?>
                                                    <option value="<?php echo $page->ID; ?>"><?php echo esc_html($page->post_title); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                                <span style="font-size: 10px; color: var(--admin-text-muted); text-transform: uppercase;">Required</span>
                                                <label class="xep-switch" style="transform: scale(0.8);">
                                                    <input type="checkbox" class="contract-required" value="1" />
                                                    <span class="xep-slider"></span>
                                                </label>
                                            </div>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="#" class="xep-edit-contract" target="_blank" style="display: none; background: var(--admin-primary); color: #000; border: none; border-radius: 8px; width: 35px; height: 35px; align-items: center; justify-content: center; transition: 0.3s; text-decoration: none;">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <button type="button" class="xep-remove-contract" style="background: #ff453a; color: #fff; border: none; border-radius: 8px; width: 35px; height: 35px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s;">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    
                                    <?php
                                    foreach ($contracts as $contract): ?>
                                        <div class="xep-contract-row" style="display: flex; gap: 10px; align-items: center; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 12px; border: 1px solid var(--admin-border);">
                                            <div style="flex: 2;">
                                                <input type="text" class="contract-name" value="<?php echo esc_attr($contract['name']); ?>" placeholder="Name (e.g. Terms of Service)" />
                                            </div>
                                            <div style="flex: 2;">
                                                <select class="contract-page-id">
                                                    <option value="0">Select Page...</option>
                                                    <?php foreach ($all_pages as $page): ?>
                                                        <option value="<?php echo $page->ID; ?>" <?php selected($contract['page_id'], $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                                <span style="font-size: 10px; color: var(--admin-text-muted); text-transform: uppercase;">Required</span>
                                                <label class="xep-switch" style="transform: scale(0.8);">
                                                    <input type="checkbox" class="contract-required" value="1" <?php checked('1', $contract['required']); ?> />
                                                    <span class="xep-slider"></span>
                                                </label>
                                            </div>
                                            <div style="display: flex; gap: 5px;">
                                                <?php 
                                                $edit_url = ($contract['page_id'] > 0) ? get_edit_post_link($contract['page_id']) : '#';
                                                $display = ($contract['page_id'] > 0) ? 'flex' : 'none';
                                                ?>
                                                <a href="<?php echo esc_url($edit_url); ?>" class="xep-edit-contract" target="_blank" style="display: <?php echo $display; ?>; background: var(--admin-primary); color: #000; border: none; border-radius: 8px; width: 35px; height: 35px; align-items: center; justify-content: center; transition: 0.3s; text-decoration: none;">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <button type="button" class="xep-remove-contract" style="background: #ff453a; color: #fff; border: none; border-radius: 8px; width: 35px; height: 35px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s;">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" id="xep-add-contract" style="margin-top: 20px; background: rgba(0, 242, 255, 0.1); color: var(--admin-primary); border: 1px dashed var(--admin-primary); border-radius: 12px; padding: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; width: 100%;">
                                    <i class="fas fa-plus"></i> Add New Contract
                                </button>
                                <input type="hidden" name="xepmarket2_legal_contracts_json" id="xepmarket2_legal_contracts_json" value="<?php echo esc_attr($contracts_json); ?>" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Coupons -->
                    <div id="tab-coupons" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Coupon Management</h3>
                            <p class="description" style="margin-bottom: 25px;">Enable or disable coupon functionality and define custom discount codes for your customers.</p>
                            
                            <div class="xep-form-group"
                                style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--admin-border); padding-bottom: 20px; margin-bottom: 20px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">Enable Coupons</div>
                                    <div class="description">Activate custom discount codes at checkout.</div>
                                </div>
                                <label class="xep-switch">
                                    <input type="hidden" name="xepmarket2_coupons_enabled" value="0" />
                                    <input type="checkbox" name="xepmarket2_coupons_enabled" value="1" <?php checked(1, get_option('xepmarket2_coupons_enabled', '0'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>

                            <div class="xep-form-group">
                                <label>Define Coupons</label>
                                <div id="xep-coupons-container" style="display: flex; flex-direction: column; gap: 12px;">
                                    <?php
                                    $coupons_json = get_option('xepmarket2_coupons_json', '[]');
                                    $coupons = json_decode($coupons_json, true);
                                    if (!is_array($coupons)) $coupons = array();
                                    
                                    foreach ($coupons as $coupon): ?>
                                        <div class="xep-coupon-row" style="display: flex; gap: 10px; align-items: center; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 10px; border: 1px solid var(--admin-border);">
                                            <div style="flex: 2;">
                                                <input type="text" class="coupon-code" value="<?php echo esc_attr($coupon['code']); ?>" placeholder="CODE (e.g. ALPHA20)" />
                                            </div>
                                            <div style="flex: 1; position: relative;">
                                                <input type="number" class="coupon-rate" value="<?php echo esc_attr($coupon['rate']); ?>" placeholder="Rate" step="0.01" />
                                                <span style="position: absolute; right: 10px; top: 15px; color: var(--admin-text-muted);">%</span>
                                            </div>
                                            <button type="button" class="xep-remove-coupon" style="background: #ff453a; color: #fff; border: none; border-radius: 8px; width: 35px; height: 35px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s;">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="xep-add-coupon" style="margin-top: 20px; background: rgba(0, 242, 255, 0.1); color: var(--admin-primary); border: 1px dashed var(--admin-primary); border-radius: 12px; padding: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; width: 100%;">
                                    <i class="fas fa-plus"></i> Add New Coupon
                                </button>
                                <input type="hidden" name="xepmarket2_coupons_json" id="xepmarket2_coupons_json" value="<?php echo esc_attr($coupons_json); ?>" />
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Brand & Logos -->
                    <div id="tab-logos" class="xep-tab-content">
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
                                <?php if ($favicon = get_option('xepmarket2_favicon')): ?>
                                    <div class="xep-image-preview"
                                        style="margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid var(--admin-border); background: #000; max-width: 150px;">
                                        <img src="<?php echo esc_url($favicon); ?>" style="width: 100%; display: block;"
                                            id="preview_xep_favicon_url" />
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
                                            <option value="text_image" <?php selected('text_image', get_option('xepmarket2_header_logo_type')); ?>>Text &amp; Image</option>
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
                                        <?php if ($h_logo = get_option('xepmarket2_header_logo_img')): ?>
                                            <div class="xep-image-preview"
                                                style="margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid var(--admin-border); background: #000; max-width: 300px;">
                                                <img src="<?php echo esc_url($h_logo); ?>" style="width: 100%; display: block;"
                                                    id="preview_xep_header_logo_img" />
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Footer Logo -->
                                <div style="border-left: 1px solid var(--admin-border); padding-left: 20px;">
                                    <h4 style="color: var(--admin-primary); margin-bottom: 20px;">Footer Logo</h4>
                                    <div class="xep-form-group">
                                        <label>Logo Type</label>
                                        <select name="xepmarket2_footer_logo_type">
                                            <option value="text" <?php selected('text', get_option('xepmarket2_footer_logo_type')); ?>>Text Only</option>
                                            <option value="text_image" <?php selected('text_image', get_option('xepmarket2_footer_logo_type')); ?>>Text &amp; Image</option>
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
                                        <?php if ($f_logo = get_option('xepmarket2_footer_logo_img')): ?>
                                            <div class="xep-image-preview"
                                                style="margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid var(--admin-border); background: #000; max-width: 300px;">
                                                <img src="<?php echo esc_url($f_logo); ?>" style="width: 100%; display: block;"
                                                    id="preview_xep_footer_logo_img" />
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Social Media -->
                    <div id="tab-social" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Social Media</h3>
                            <p class="description" style="margin-bottom: 25px;">Configure your social links shown across the site (footer icons, contact areas, etc.).</p>
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
                        </div>
                    </div>

                    <!-- Tab: General -->
                    <div id="tab-general" class="xep-tab-content active">
                        <div class="xep-section-card">
                            <h3>General Settings</h3>
                            <p class="description" style="margin-bottom: 25px;">Footer badges, announcement banner, and footer text.</p>

                            <div class="xep-section-card" style="background: rgba(255, 69, 58, 0.05); border: 1px solid rgba(255, 69, 58, 0.2); margin-bottom: 30px;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <h4 style="margin: 0; color: #ff453a;"><i class="fas fa-comments"></i> Enable Comments & Reviews</h4>
                                        <p class="description" style="margin: 5px 0 0 0;">Toggle global WordPress comments and WooCommerce product reviews.</p>
                                    </div>
                                    <label class="xep-switch">
                                        <input type="checkbox" name="xepmarket2_enable_comments" value="1" <?php checked('1', get_option('xepmarket2_enable_comments', '0')); ?>>
                                        <span class="xep-slider"></span>
                                    </label>
                                </div>
                            </div>

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
                                    value="<?php echo esc_attr(get_option('xepmarket2_banner_discount', 'Ã„Å¸Ã…Â¸Ã…Â¡Ã¢â€šÂ¬ Limited Time Offer: Up to 50% OFF! Ã„Å¸Ã…Â¸Ã…Â¡Ã¢â€šÂ¬')); ?>" />
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
                                    <input type="hidden" name="xepmarket2_slider_enable" value="0" />
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
                    
                    <!-- Tab: Flash Deals -->
                    <div id="tab-flash-deals" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Flash Deals Settings</h3>
                            <div class="xep-form-group"
                                style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--admin-border); padding-bottom: 20px; margin-bottom: 20px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">Enable Flash Deals</div>
                                    <div class="description">Show the on-sale products slider above Trending Gear.</div>
                                </div>
                                <label class="xep-switch">
                                    <input type="hidden" name="xepmarket2_flash_deals_enable" value="0" />
                                    <input type="checkbox" name="xepmarket2_flash_deals_enable" value="1" <?php checked(1, get_option('xepmarket2_flash_deals_enable', '1'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>
                            <div class="xep-form-group">
                                <label>Flash Deals Title (HTML Allowed)</label>
                                <input type="text" name="xepmarket2_flash_deals_title"
                                    value="<?php echo esc_attr(get_option('xepmarket2_flash_deals_title', 'Flash <span class="logo-accent">Deals</span>')); ?>" />
                            </div>
                            <div class="xep-form-group">
                                <label>Flash Deals Subtitle</label>
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
                                    <input type="hidden" name="xepmarket2_mod_omnixep_restrict" value="0" />
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
                                    <input type="hidden" name="xepmarket2_mod_custom_checkout" value="0" />
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
                                    <input type="hidden" name="xepmarket2_mod_sale_badges" value="0" />
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
                                    <input type="hidden" name="xepmarket2_mod_breadcrumb_modern" value="0" />
                                    <input type="checkbox" name="xepmarket2_mod_breadcrumb_modern" value="1" <?php checked(1, get_option('xepmarket2_mod_breadcrumb_modern', '1'), true); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="xep-section-card" style="margin-top: 30px;">
                            <h3 style="color: var(--admin-primary);"><i class="fas fa-cubes"></i> System Ecosystem & Plugins
                            </h3>
                            <p class="description" style="margin-bottom: 15px;">Live status of all modules integrated with
                                the XEPMARKET-ALFA premium ecosystem.</p>

                            <div class="xep-form-group" id="xep-install-all-modules-row"
                                style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; background: linear-gradient(135deg, rgba(0, 242, 255, 0.08), rgba(112, 0, 255, 0.08)); padding: 18px 20px; border-radius: 12px; border: 1px solid rgba(0, 242, 255, 0.25); margin-bottom: 25px;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-download" style="color: var(--admin-primary);"></i>
                                        Install all required modules
                                    </div>
                                    <div class="description" style="margin-top: 4px;">Download all plugins from <code>github.com/PlanC90/plugins</code> and install them into <code>wp-content/plugins</code>. Existing plugins will be updated.</div>
                                    
                                    <!-- Progress Bar Container -->
                                    <div id="xep-sync-progress-container" style="display: none; margin-top: 15px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <span id="xep-sync-status-text" style="font-size: 12px; font-weight: 600; color: var(--admin-primary);">Starting installation...</span>
                                            <span id="xep-sync-percentage" style="font-size: 12px; font-weight: 700; color: #fff;">0%</span>
                                        </div>
                                        <div style="height: 10px; background: rgba(255,255,255,0.05); border-radius: 5px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);">
                                            <div id="xep-sync-progress-bar" style="height: 100%; width: 0%; background: linear-gradient(90deg, var(--admin-primary), #7000ff); border-radius: 5px; transition: width 0.4s ease, box-shadow 0.3s ease; box-shadow: 0 0 10px rgba(0, 242, 255, 0.3);"></div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="xep-install-all-modules-btn" class="xep-save-btn"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce(XEPMARKET2_GITHUB_SYNC_NONCE_ACTION)); ?>"
                                    style="padding: 10px 22px !important; font-size: 13px !important; background: linear-gradient(135deg, #238636, #2ea043) !important; border: none !important; color: #fff !important; border-radius: 10px !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; gap: 8px !important;">
                                    <i class="fas fa-download"></i> <span class="btn-text">Install all required modules</span>
                                </button>
                            </div>

                            <div class="xep-form-group" id="xep-github-sync-row"
                                style="display: none; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; background: linear-gradient(135deg, rgba(0, 242, 255, 0.06), rgba(112, 0, 255, 0.06)); padding: 18px 20px; border-radius: 12px; border: 1px solid rgba(0, 242, 255, 0.2); margin-bottom: 25px;">
                                <div>
                                    <div style="font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fab fa-github" style="color: var(--admin-primary);"></i>
                                        Install / Update from GitHub
                                    </div>
                                    <div class="description" style="margin-top: 4px;">Download all plugins from the official repo and copy them into <code>wp-content/plugins</code>. Existing folders are updated.</div>
                                </div>
                                <button type="button" id="xep-github-sync-btn" class="xep-save-btn"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce(XEPMARKET2_GITHUB_SYNC_NONCE_ACTION)); ?>"
                                    style="padding: 10px 22px !important; font-size: 13px !important; background: linear-gradient(135deg, #238636, #2ea043) !important; border: none !important; color: #fff !important; border-radius: 10px !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; gap: 8px !important;">
                                    <i class="fab fa-github"></i> <span class="btn-text">Sync from GitHub</span>
                                </button>
                            </div>

                            <?php
                            $required_plugins = [
                                ['name' => 'WooCommerce', 'slug' => 'woocommerce', 'path' => 'woocommerce/woocommerce.php', 'required' => true, 'icon' => 'fa-shopping-cart'],
                                ['name' => 'OmniXEP Gateway', 'slug' => 'omnixep-woocommerce', 'path' => 'omnixep-woocommerce/omnixep-woocommerce.php', 'required' => true, 'icon' => 'fa-wallet'],
                                ['name' => 'AliSync Helper', 'slug' => 'ali-sync-helper', 'required' => false, 'icon' => 'fa-sync-alt', 'type' => 'core'],
                                ['name' => 'AliDropship', 'slug' => 'woo-alidropship', 'path' => 'woo-alidropship/woo-alidropship.php', 'required' => false, 'icon' => 'fa-ship'],
                                ['name' => 'Product Variations Swatches', 'slug' => 'product-variations-swatches-for-woocommerce', 'path' => 'product-variations-swatches-for-woocommerce/product-variations-swatches-for-woocommerce.php', 'required' => false, 'icon' => 'fa-palette'],
                                ['name' => 'Additional Variation Gallery', 'slug' => 'vargal-additional-variation-gallery-for-woo', 'path' => 'vargal-additional-variation-gallery-for-woo/vargal-additional-variation-gallery-for-woo.php', 'required' => false, 'icon' => 'fa-images'],
                                ['name' => 'Orders Tracking', 'slug' => 'woo-orders-tracking', 'path' => 'woo-orders-tracking/woo-orders-tracking.php', 'required' => false, 'icon' => 'fa-truck-fast'],
                            ];

                            if (!function_exists('is_plugin_active')) {
                                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
                            }

                            $all_plugins = get_plugins();

                            foreach ($required_plugins as $plug):
                                $update_available = false;
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

                                    // GÃƒÆ’Ã‚Â¼ncelleme kontrolÃƒÆ’Ã‚Â¼: kurulu sÃƒÆ’Ã‚Â¼rÃƒÆ’Ã‚Â¼m < temadaki beklenen sÃƒÆ’Ã‚Â¼rÃƒÆ’Ã‚Â¼m
                                    $update_available = false;
                                    $expected_version = function_exists('xepmarket2_get_plugin_expected_version') ? xepmarket2_get_plugin_expected_version($plug['slug']) : null;
                                    if ($is_installed && $expected_version && !empty($plugin_path) && isset($all_plugins[$plugin_path]['Version'])) {
                                        $update_available = version_compare($all_plugins[$plugin_path]['Version'], $expected_version, '<');
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
                                                <?php if ($update_available): ?>
                                                    <span style="color: #ff9f0a;"><i class="fas fa-arrow-circle-up"></i> Update available (<?php echo esc_html($expected_version); ?>)</span>
                                                <?php elseif ($is_active): ?>
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
                                        <?php if ($update_available): ?>
                                            <a href="<?php echo esc_url(admin_url('themes.php?page=tgmpa-install-plugins')); ?>"
                                                class="xep-save-btn"
                                                style="padding: 6px 15px !important; font-size: 11px !important; width: auto !important; background: linear-gradient(135deg, #238636, #2ea043) !important; box-shadow: 0 4px 10px rgba(35, 134, 54, 0.3) !important;">UPDATE</a>
                                        <?php elseif (isset($plug['type']) && $plug['type'] === 'core'): ?>
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

                    <!-- Tab: Menu Settings -->
                    <div id="tab-menus" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Web &amp; Mobile Menus</h3>
                            <p class="description" style="margin-bottom: 25px;">Choose which menu appears in the desktop header and in the mobile drawer. Create and edit menus under <a href="<?php echo esc_url(admin_url('nav-menus.php')); ?>">Appearance &rarr; Menus</a>. Leave "Primary (default)" to use the same menu everywhere.</p>
                            <?php
                            $menus = wp_get_nav_menus();
                            $current_web = get_option('xepmarket2_menu_web', '');
                            $current_mobile = get_option('xepmarket2_menu_mobile', '');
                            ?>
                            <div class="xep-form-group" style="margin-bottom: 25px;">
                                <label for="xepmarket2_menu_web" style="display: block; font-weight: 700; margin-bottom: 8px;">Web (desktop) menu</label>
                                <select name="xepmarket2_menu_web" id="xepmarket2_menu_web" style="min-width: 280px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--admin-border); background: var(--admin-bg); color: var(--admin-text);">
                                    <option value="">Primary (default)</option>
                                    <?php foreach ($menus as $menu): ?>
                                        <option value="<?php echo esc_attr($menu->term_id); ?>" <?php selected($current_web, (string) $menu->term_id); ?>><?php echo esc_html($menu->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description" style="margin-top: 6px;">This menu appears in the desktop header.</p>
                            </div>
                            <div class="xep-form-group">
                                <label for="xepmarket2_menu_mobile" style="display: block; font-weight: 700; margin-bottom: 8px;">Mobile menu</label>
                                <select name="xepmarket2_menu_mobile" id="xepmarket2_menu_mobile" style="min-width: 280px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--admin-border); background: var(--admin-bg); color: var(--admin-text);">
                                    <option value="">Primary (default)</option>
                                    <?php foreach ($menus as $menu): ?>
                                        <option value="<?php echo esc_attr($menu->term_id); ?>" <?php selected($current_mobile, (string) $menu->term_id); ?>><?php echo esc_html($menu->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description" style="margin-top: 6px;">This menu appears in the panel that opens when the hamburger icon is tapped on mobile.</p>
                            </div>
                            <p style="margin-top: 20px;"><a href="<?php echo esc_url(admin_url('nav-menus.php')); ?>" class="xep-save-btn" style="display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-edit"></i> Edit menus</a></p>
                        </div>

                        <div class="xep-section-card" style="margin-top: 30px;">
                            <h3>Mobile bottom bar (icon nav)</h3>
                            <p class="description" style="margin-bottom: 25px;">Show or hide each icon in the fixed bottom navigation bar on mobile. You can choose which icon to display for each item. Maximum 5 items; turn off default items to free slots for additional custom links.</p>
                            <?php
                            $icon_opts_home   = array('' => 'Default (home)', 'dashicons dashicons-admin-home' => 'Dashicons: Home', 'fa-solid fa-house' => 'FA: House', 'fa-solid fa-home' => 'FA: Home');
                            $icon_opts_shop  = array('' => 'Default (store)', 'dashicons dashicons-store' => 'Dashicons: Store', 'fa-solid fa-store' => 'FA: Store', 'fa-solid fa-bag-shopping' => 'FA: Bag');
                            $icon_opts_cart  = array('' => 'Default (cart)', 'dashicons dashicons-cart' => 'Dashicons: Cart', 'fa-solid fa-cart-shopping' => 'FA: Cart', 'fa-solid fa-basket-shopping' => 'FA: Basket');
                            $icon_opts_account = array('' => 'Default (user)', 'dashicons dashicons-admin-users' => 'Dashicons: User', 'fa-solid fa-user' => 'FA: User', 'fa-solid fa-user-circle' => 'FA: User circle');
                            $icon_defaults = array('home' => 'dashicons dashicons-admin-home', 'shop' => 'dashicons dashicons-store', 'cart' => 'dashicons dashicons-cart', 'account' => 'dashicons dashicons-admin-users');
                            $icon_opts_custom = array('dashicons dashicons-admin-links' => 'Link', 'fa-solid fa-right-left' => 'Swap', 'fa-solid fa-arrows-rotate' => 'Sync', 'fa-solid fa-link' => 'FA: Link', 'fa-solid fa-star' => 'FA: Star', 'fa-solid fa-heart' => 'FA: Heart', 'fa-solid fa-phone' => 'FA: Phone', 'fa-solid fa-envelope' => 'FA: Envelope');
                            $active_defaults = (get_option('xepmarket2_mobile_nav_show_home', '1') ? 1 : 0) + (get_option('xepmarket2_mobile_nav_show_shop', '1') ? 1 : 0) + (get_option('xepmarket2_mobile_nav_show_cart', '1') ? 1 : 0) + (get_option('xepmarket2_mobile_nav_show_account', '1') ? 1 : 0);
                            $custom_slots = max(0, 5 - $active_defaults);
                            $custom_items = get_option('xepmarket2_mobile_nav_custom_items', array());
                            $custom_items = is_array($custom_items) ? array_slice($custom_items, 0, 5) : array();
                            while (count($custom_items) < 5) { $custom_items[] = array('url' => '', 'label' => '', 'icon' => 'dashicons dashicons-admin-links', 'show' => false); }
                            $nav_order = get_option('xepmarket2_mobile_nav_order', array('home', 'shop', 'cart', 'account', 'custom_0'));
                            if (!is_array($nav_order) || count($nav_order) !== 5) { $nav_order = array('home', 'shop', 'cart', 'account', 'custom_0'); }
                            $order_options = array('home' => 'Home', 'shop' => 'Shop', 'cart' => 'Cart', 'account' => 'Account', 'custom_0' => 'Custom 1', 'custom_1' => 'Custom 2', 'custom_2' => 'Custom 3', 'custom_3' => 'Custom 4', 'custom_4' => 'Custom 5');
                            ?>
                            <style>
                            .xep-icon-picker { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
                            .xep-icon-opt { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border: 2px solid var(--admin-border); background: var(--admin-bg); border-radius: 10px; cursor: pointer; font-size: 18px; color: var(--admin-text); transition: all 0.2s; }
                            .xep-icon-opt:hover { border-color: var(--admin-primary); background: rgba(0,242,255,0.1); color: var(--admin-primary); }
                            .xep-icon-opt.selected { border-color: var(--admin-primary); border-width: 3px; background: rgba(0,242,255,0.25); color: var(--admin-primary); box-shadow: 0 0 14px rgba(0,242,255,0.4); outline: 2px solid rgba(0,242,255,0.3); outline-offset: 2px; }
                            .xep-icon-opt i { font-size: 20px; }
                            </style>
                            <div class="xep-form-group" style="border: 1px solid var(--admin-primary); border-radius: 12px; padding: 16px; margin-bottom: 20px; background: rgba(0,242,255,0.06);">
                                <h4 style="margin: 0 0 12px 0; font-size: 14px;">Order of items in the bar (left to right)</h4>
                                <p class="description" style="margin-bottom: 14px;">Set the order of the 5 slots. Only active items (toggles On) will appear.</p>
                                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                                    <?php for ($pos = 0; $pos < 5; $pos++): $cur = isset($nav_order[$pos]) ? $nav_order[$pos] : ($pos < 4 ? array('home','shop','cart','account')[$pos] : 'custom_0'); ?>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-size: 12px; opacity: 0.8;"><?php echo $pos + 1; ?>.</span>
                                        <select name="xepmarket2_mobile_nav_order[<?php echo $pos; ?>]" style="min-width: 110px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--admin-border); background: var(--admin-bg); color: var(--admin-text);">
                                            <?php foreach ($order_options as $val => $lbl): ?><option value="<?php echo esc_attr($val); ?>" <?php selected($cur, $val); ?>><?php echo esc_html($lbl); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="xep-form-group" style="border-bottom: 1px solid var(--admin-border); padding-bottom: 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                    <div><span style="font-weight: 700;">Home</span><p class="description" style="margin: 4px 0 0 0;">Link to the homepage.</p></div>
                                    <label class="xep-switch"><input type="checkbox" name="xepmarket2_mobile_nav_show_home" value="1" <?php checked(1, get_option('xepmarket2_mobile_nav_show_home', '1'), true); ?> /><span class="xep-slider"></span></label>
                                </div>
                                <div style="margin-top: 12px;"><span style="font-size: 12px; opacity: 0.9;">Icon</span>
                                <select name="xepmarket2_mobile_nav_icon_home" id="xepmarket2_mobile_nav_icon_home" style="display:none;">
                                    <?php foreach ($icon_opts_home as $val => $label): ?><option value="<?php echo esc_attr($val); ?>" <?php selected(get_option('xepmarket2_mobile_nav_icon_home', ''), $val); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                                </select>
                                <div class="xep-icon-picker" data-for="xepmarket2_mobile_nav_icon_home">
                                    <?php foreach ($icon_opts_home as $val => $label): $ic = $val !== '' ? $val : $icon_defaults['home']; ?>
                                    <button type="button" class="xep-icon-opt" data-value="<?php echo esc_attr($val); ?>" title="<?php echo esc_attr($label); ?>"><i class="<?php echo esc_attr($ic); ?>"></i></button>
                                    <?php endforeach; ?>
                                </div></div>
                            </div>
                            <div class="xep-form-group" style="border-bottom: 1px solid var(--admin-border); padding-bottom: 16px; margin-top: 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                    <div><span style="font-weight: 700;">Shop</span><p class="description" style="margin: 4px 0 0 0;">Link to the shop page.</p></div>
                                    <label class="xep-switch"><input type="checkbox" name="xepmarket2_mobile_nav_show_shop" value="1" <?php checked(1, get_option('xepmarket2_mobile_nav_show_shop', '1'), true); ?> /><span class="xep-slider"></span></label>
                                </div>
                                <div style="margin-top: 12px;"><span style="font-size: 12px; opacity: 0.9;">Icon</span>
                                <select name="xepmarket2_mobile_nav_icon_shop" id="xepmarket2_mobile_nav_icon_shop" style="display:none;">
                                    <?php foreach ($icon_opts_shop as $val => $label): ?><option value="<?php echo esc_attr($val); ?>" <?php selected(get_option('xepmarket2_mobile_nav_icon_shop', ''), $val); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                                </select>
                                <div class="xep-icon-picker" data-for="xepmarket2_mobile_nav_icon_shop">
                                    <?php foreach ($icon_opts_shop as $val => $label): $ic = $val !== '' ? $val : $icon_defaults['shop']; ?>
                                    <button type="button" class="xep-icon-opt" data-value="<?php echo esc_attr($val); ?>" title="<?php echo esc_attr($label); ?>"><i class="<?php echo esc_attr($ic); ?>"></i></button>
                                    <?php endforeach; ?>
                                </div></div>
                            </div>
                            <div class="xep-form-group" style="border-bottom: 1px solid var(--admin-border); padding-bottom: 16px; margin-top: 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                    <div><span style="font-weight: 700;">Cart</span><p class="description" style="margin: 4px 0 0 0;">Link to the cart (with count badge).</p></div>
                                    <label class="xep-switch"><input type="checkbox" name="xepmarket2_mobile_nav_show_cart" value="1" <?php checked(1, get_option('xepmarket2_mobile_nav_show_cart', '1'), true); ?> /><span class="xep-slider"></span></label>
                                </div>
                                <div style="margin-top: 12px;"><span style="font-size: 12px; opacity: 0.9;">Icon</span>
                                <select name="xepmarket2_mobile_nav_icon_cart" id="xepmarket2_mobile_nav_icon_cart" style="display:none;">
                                    <?php foreach ($icon_opts_cart as $val => $label): ?><option value="<?php echo esc_attr($val); ?>" <?php selected(get_option('xepmarket2_mobile_nav_icon_cart', ''), $val); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                                </select>
                                <div class="xep-icon-picker" data-for="xepmarket2_mobile_nav_icon_cart">
                                    <?php foreach ($icon_opts_cart as $val => $label): $ic = $val !== '' ? $val : $icon_defaults['cart']; ?>
                                    <button type="button" class="xep-icon-opt" data-value="<?php echo esc_attr($val); ?>" title="<?php echo esc_attr($label); ?>"><i class="<?php echo esc_attr($ic); ?>"></i></button>
                                    <?php endforeach; ?>
                                </div></div>
                            </div>
                            <div class="xep-form-group" style="padding-bottom: 0; margin-top: 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                    <div><span style="font-weight: 700;">Account</span><p class="description" style="margin: 4px 0 0 0;">Link to My Account.</p></div>
                                    <label class="xep-switch"><input type="checkbox" name="xepmarket2_mobile_nav_show_account" value="1" <?php checked(1, get_option('xepmarket2_mobile_nav_show_account', '1'), true); ?> /><span class="xep-slider"></span></label>
                                </div>
                                <div style="margin-top: 12px;"><span style="font-size: 12px; opacity: 0.9;">Icon</span>
                                <select name="xepmarket2_mobile_nav_icon_account" id="xepmarket2_mobile_nav_icon_account" style="display:none;">
                                    <?php foreach ($icon_opts_account as $val => $label): ?><option value="<?php echo esc_attr($val); ?>" <?php selected(get_option('xepmarket2_mobile_nav_icon_account', ''), $val); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                                </select>
                                <div class="xep-icon-picker" data-for="xepmarket2_mobile_nav_icon_account">
                                    <?php foreach ($icon_opts_account as $val => $label): $ic = $val !== '' ? $val : $icon_defaults['account']; ?>
                                    <button type="button" class="xep-icon-opt" data-value="<?php echo esc_attr($val); ?>" title="<?php echo esc_attr($label); ?>"><i class="<?php echo esc_attr($ic); ?>"></i></button>
                                    <?php endforeach; ?>
                                </div></div>
                            </div>

                            <p class="description" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--admin-border);"><?php if ($custom_slots > 0): ?><strong>Additional menu items (<?php echo $custom_slots; ?> slot<?php echo $custom_slots !== 1 ? 's' : ''; ?>)</strong> ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Turn off default icons above to free slots. For each: switch <strong>On</strong>, set <strong>URL</strong> or select a page, choose icon, then <strong>Save Changes</strong>. Assign order above.<?php else: ?>Turn off one or more default icons (Home, Shop, Cart, Account) above and save to free slots for custom links.<?php endif; ?></p>
                            <?php for ($ci = 0; $ci < $custom_slots; $ci++): $c = isset($custom_items[$ci]) ? $custom_items[$ci] : array('url'=>'','label'=>'','icon'=>'dashicons dashicons-admin-links','show'=>false); ?>
                            <div class="xep-form-group xep-custom-nav-item" style="border: 1px solid var(--admin-border); border-radius: 12px; padding: 14px; margin-top: 14px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 10px;">
                                    <span style="font-weight: 700;">Custom item <?php echo $ci + 1; ?></span>
                                    <label class="xep-switch"><input type="checkbox" name="xepmarket2_mobile_nav_custom_items[<?php echo $ci; ?>][show]" value="1" <?php checked(!empty($c['show'])); ?> /><span class="xep-slider"></span></label>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div><label style="font-size: 12px; opacity: 0.9;">Label</label><input type="text" id="xep_custom_nav_label_<?php echo $ci; ?>" name="xepmarket2_mobile_nav_custom_items[<?php echo $ci; ?>][label]" value="<?php echo esc_attr(isset($c['label']) ? $c['label'] : ''); ?>" placeholder="e.g. Contact" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--admin-border); background: var(--admin-bg); color: var(--admin-text); margin-top: 4px;" /></div>
                                    <div><label style="font-size: 12px; opacity: 0.9;">URL</label><input type="url" id="xep_custom_nav_url_<?php echo $ci; ?>" name="xepmarket2_mobile_nav_custom_items[<?php echo $ci; ?>][url]" value="<?php echo esc_attr(isset($c['url']) ? $c['url'] : ''); ?>" placeholder="https://..." style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--admin-border); background: var(--admin-bg); color: var(--admin-text); margin-top: 4px;" /></div>
                                </div>
                                <div style="margin-top: 12px;">
                                    <label style="font-size: 12px; opacity: 0.9;">Or select a page</label>
                                    <select id="xep_custom_nav_page_<?php echo $ci; ?>" class="xep-page-select" data-url-id="xep_custom_nav_url_<?php echo $ci; ?>" data-label-id="xep_custom_nav_label_<?php echo $ci; ?>" data-icon-picker-for="xep_nav_custom_icon_<?php echo $ci; ?>" style="width: 100%; max-width: 320px; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--admin-border); background: var(--admin-bg); color: var(--admin-text); margin-top: 4px;">
                                        <option value="" data-label="" <?php selected( empty($c['url']), true ); ?>>ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Select a page ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â</option>
                                        <?php
                                        $nav_pages = get_pages(array('number' => 300, 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title'));
                                        $saved_url = isset($c['url']) ? trim($c['url']) : '';
                                        foreach ($nav_pages as $p):
                                            $plink = get_permalink($p->ID);
                                            $url_match = $saved_url !== '' && rtrim($saved_url, '/') === rtrim($plink, '/');
                                        ?><option value="<?php echo esc_attr($plink); ?>" data-label="<?php echo esc_attr($p->post_title); ?>" <?php selected( $url_match, true ); ?>><?php echo esc_html($p->post_title); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="margin-top: 12px;"><span style="font-size: 12px; opacity: 0.9;">Icon</span>
                                <select name="xepmarket2_mobile_nav_custom_items[<?php echo $ci; ?>][icon]" id="xep_nav_custom_icon_<?php echo $ci; ?>" style="display:none;">
                                    <?php foreach ($icon_opts_custom as $val => $label): ?><option value="<?php echo esc_attr($val); ?>" <?php selected(isset($c['icon']) ? $c['icon'] : 'dashicons dashicons-admin-links', $val); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                                </select>
                                <div class="xep-icon-picker" data-for="xep_nav_custom_icon_<?php echo $ci; ?>">
                                    <?php foreach ($icon_opts_custom as $val => $label): ?>
                                    <button type="button" class="xep-icon-opt" data-value="<?php echo esc_attr($val); ?>" title="<?php echo esc_attr($label); ?>"><i class="<?php echo esc_attr($val); ?>"></i></button>
                                    <?php endforeach; ?>
                                </div></div>
                            </div>
                            <?php endfor; ?>

                            <script>
                            (function(){
                                function initIconPickers() {
                                    document.querySelectorAll('.xep-icon-picker').forEach(function(picker){
                                        var sel = document.getElementById(picker.getAttribute('data-for'));
                                        if (!sel) return;
                                        var opts = picker.querySelectorAll('.xep-icon-opt');
                                        opts.forEach(function(btn){
                                            btn.addEventListener('click', function(){
                                                sel.value = btn.getAttribute('data-value');
                                                opts.forEach(function(o){ o.classList.remove('selected'); });
                                                btn.classList.add('selected');
                                            });
                                        });
                                        var selVal = (sel.value || '').trim();
                                        opts.forEach(function(btn){
                                            var v = (btn.getAttribute('data-value') || '').trim();
                                            if (v === selVal) btn.classList.add('selected');
                                            else btn.classList.remove('selected');
                                        });
                                    });
                                }
                                function initPageSelects() {
                                    document.querySelectorAll('.xep-page-select').forEach(function(sel){
                                        sel.addEventListener('change', function(){
                                            var urlId = this.getAttribute('data-url-id');
                                            var labelId = this.getAttribute('data-label-id');
                                            var opt = this.options[this.selectedIndex];
                                            if (opt && urlId && labelId) {
                                                var u = document.getElementById(urlId);
                                                var l = document.getElementById(labelId);
                                                if (u) u.value = opt.value || '';
                                                if (l && opt.getAttribute('data-label')) l.value = opt.getAttribute('data-label');
                                                var label = (opt.getAttribute('data-label') || '').toLowerCase();
                                                if (label.indexOf('swap') !== -1) {
                                                    var iconSelectId = this.getAttribute('data-icon-picker-for');
                                                    if (iconSelectId) {
                                                        var iconSel = document.getElementById(iconSelectId);
                                                        var swapVal = 'fa-solid fa-right-left';
                                                        if (iconSel) {
                                                            iconSel.value = swapVal;
                                                            var picker = document.querySelector('.xep-icon-picker[data-for="' + iconSelectId + '"]');
                                                            if (picker) {
                                                                picker.querySelectorAll('.xep-icon-opt').forEach(function(btn){ btn.classList.remove('selected'); });
                                                                var swapBtn = picker.querySelector('.xep-icon-opt[data-value="' + swapVal + '"]');
                                                                if (swapBtn) swapBtn.classList.add('selected');
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    });
                                }
                                if (document.readyState === 'loading') {
                                    document.addEventListener('DOMContentLoaded', function(){ initIconPickers(); initPageSelects(); });
                                } else {
                                    initIconPickers();
                                    initPageSelects();
                                }
                            })();
                            </script>
                        </div>
                    </div>

                    <!-- Tab: Shipping Limits -->
                    <div id="tab-shipping" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Shipping Exclusion Limits</h3>
                            <p class="description" style="margin-bottom: 25px;">Select the countries you do not want to ship to. Selected countries will be removed from the checkout process, preventing users from receiving products there.</p>

                            <div class="xep-shipping-exclusion-ui">
                                <?php
                                $excluded_countries = get_option('xepmarket2_shipping_excluded_countries');
                                if (!is_array($excluded_countries)) $excluded_countries = array();
                                
                                $all_countries = array();
                                if (class_exists('WooCommerce')) {
                                    $all_countries = WC()->countries->get_countries();
                                }
                                ?>
                                
                                <select name="xepmarket2_shipping_excluded_countries[]" id="xep_hidden_excluded_countries" multiple style="display:none;">
                                    <?php
                                    // Populate hidden select with saved options so forms sumbit correctly
                                    foreach ($excluded_countries as $code) {
                                        echo '<option value="' . esc_attr($code) . '" selected="selected">' . esc_html($code) . '</option>';
                                    }
                                    ?>
                                </select>

                                <div style="display: flex; gap: 20px; align-items: stretch;">
                                    <!-- Available Countries List -->
                                    <div style="flex: 1; display:flex; flex-direction: column;">
                                        <label style="font-weight: 600; margin-bottom: 10px; display: block; color: var(--admin-text);">Available Countries</label>
                                        <div style="margin-bottom: 10px;">
                                            <input type="text" id="xep_search_available" placeholder="Search countries..." style="width: 100%; border: 1px solid var(--admin-border); background: var(--admin-surface); color: var(--admin-text); padding: 8px 12px; border-radius: 6px;" />
                                        </div>
                                        <select id="xep_available_list" multiple style="width: 100%; height: 350px; padding: 10px; border: 1px solid var(--admin-border); border-radius: 8px; background: rgba(0,0,0,0.1); color: var(--admin-text); font-family: inherit; font-size: 14px;">
                                            <?php
                                            if (!empty($all_countries)) {
                                                foreach ($all_countries as $code => $name) {
                                                    if (!in_array($code, $excluded_countries)) {
                                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . ' (' . esc_html($code) . ')</option>';
                                                    }
                                                }
                                            } else {
                                                echo '<option disabled>WooCommerce is not active.</option>';
                                            }
                                            ?>
                                        </select>
                                        <p class="description" style="margin-top: 10px; font-size: 12px;">Select countries to ban and click 'Add >'</p>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div style="display: flex; flex-direction: column; justify-content: center; gap: 15px;">
                                        <button type="button" id="xep_btn_add_country" class="xep-save-btn" style="padding: 10px; min-width: 40px;" title="Add to Excluded">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                        <button type="button" id="xep_btn_remove_country" style="padding: 10px; min-width: 40px; background: var(--admin-border); border: none; border-radius: 6px; color: #fff; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'" title="Remove from Excluded">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                    </div>

                                    <!-- Excluded Countries List -->
                                    <div style="flex: 1; display:flex; flex-direction: column;">
                                        <label style="font-weight: 600; margin-bottom: 10px; display: block; color: var(--admin-primary);">Excluded Countries (Banned)</label>
                                        <div style="margin-bottom: 10px;">
                                            <input type="text" id="xep_search_excluded" placeholder="Search excluded..." style="width: 100%; border: 1px solid var(--admin-border); background: var(--admin-surface); color: var(--admin-text); padding: 8px 12px; border-radius: 6px;" />
                                        </div>
                                        <select id="xep_excluded_list" multiple style="width: 100%; height: 350px; padding: 10px; border: 1px solid var(--admin-primary); box-shadow: 0 0 10px rgba(0, 242, 255, 0.1); border-radius: 8px; background: rgba(0,0,0,0.1); color: var(--admin-text); font-family: inherit; font-size: 14px;">
                                            <?php
                                            if (!empty($all_countries)) {
                                                foreach ($all_countries as $code => $name) {
                                                    if (in_array($code, $excluded_countries)) {
                                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . ' (' . esc_html($code) . ')</option>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </select>
                                        <p class="description" style="margin-top: 10px; font-size: 12px;">Shipping is DISABELD for these countries.</p>
                                    </div>
                                </div>
                                
                                <script>
                                (function() {
                                    const availList = document.getElementById('xep_available_list');
                                    const exclList = document.getElementById('xep_excluded_list');
                                    const hiddenSelect = document.getElementById('xep_hidden_excluded_countries');
                                    const btnAdd = document.getElementById('xep_btn_add_country');
                                    const btnRemove = document.getElementById('xep_btn_remove_country');
                                    
                                    function updateHiddenSelect() {
                                        hiddenSelect.innerHTML = '';
                                        Array.from(exclList.options).forEach(opt => {
                                            if(!opt.disabled) {
                                                const newOpt = document.createElement('option');
                                                newOpt.value = opt.value;
                                                newOpt.selected = true;
                                                hiddenSelect.appendChild(newOpt);
                                            }
                                        });
                                    }

                                    function moveOptions(source, target) {
                                        const selectedOptions = Array.from(source.selectedOptions);
                                        if (selectedOptions.length === 0) return;
                                        
                                        selectedOptions.forEach(opt => {
                                            if(!opt.disabled) {
                                                const newOpt = opt.cloneNode(true);
                                                newOpt.selected = false;
                                                target.appendChild(newOpt);
                                                opt.remove();
                                            }
                                        });
                                        
                                        // Sort alphabetically
                                        const options = Array.from(target.options);
                                        options.sort((a, b) => a.text.localeCompare(b.text));
                                        target.innerHTML = '';
                                        options.forEach(opt => target.appendChild(opt));

                                        updateHiddenSelect();
                                    }

                                    if(btnAdd) btnAdd.addEventListener('click', () => moveOptions(availList, exclList));
                                    if(btnRemove) btnRemove.addEventListener('click', () => moveOptions(exclList, availList));
                                    
                                    // Double click to move
                                    if(availList) availList.addEventListener('dblclick', (e) => {
                                        if(e.target.tagName === 'OPTION') moveOptions(availList, exclList);
                                    });
                                    if(exclList) exclList.addEventListener('dblclick', (e) => {
                                        if(e.target.tagName === 'OPTION') moveOptions(exclList, availList);
                                    });

                                    // Simple Search filter
                                    function filterList(searchInputId, listId) {
                                        const searchInput = document.getElementById(searchInputId);
                                        if(!searchInput) return;
                                        searchInput.addEventListener('input', function() {
                                            const term = this.value.toLowerCase();
                                            const list = document.getElementById(listId);
                                            Array.from(list.options).forEach(opt => {
                                                if (opt.text.toLowerCase().includes(term)) {
                                                    opt.style.display = '';
                                                } else {
                                                    opt.style.display = 'none';
                                                    opt.selected = false;
                                                }
                                            });
                                        });
                                    }
                                    
                                    filterList('xep_search_available', 'xep_available_list');
                                    filterList('xep_search_excluded', 'xep_excluded_list');
                                })();
                                </script>
                            </div>
                        </div>

                        <!-- NEW SHIPPING RATES UI -->
                        <div class="xep-section-card" style="margin-top: 30px;">
                            <h3>Shipping Rates (Country Based)</h3>
                            <p class="description" style="margin-bottom: 25px;">Define custom shipping costs using USD. Use the base cost for all other countries not specifically defined in any zone.</p>
                            
                            <!-- Base cost -->
                            <div class="xep-form-group" style="margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 30px;">
                                <label style="display:block; font-weight:600; color:var(--admin-primary); margin-bottom:10px;">Base Shipping Cost ($)</label>
                                <div style="display:flex; align-items:center;">
                                    <span style="background:rgba(0,0,0,0.3); padding:10px; border-radius:6px 0 0 6px; border:1px solid var(--admin-border); border-right:none; color:#fff;">$</span>
                                    <input type="number" step="0.01" min="0" name="xepmarket2_shipping_base_cost" value="<?php echo esc_attr(get_option('xepmarket2_shipping_base_cost', '0')); ?>" style="width: 150px; background: rgba(0,0,0,0.1) !important; color:#fff; border-radius:0 6px 6px 0; border:1px solid var(--admin-border);" />
                                </div>
                                <p class="description" style="margin-top:10px; color:#aaa;">Applies to 'All Countries' unless a specific zone matches below. Leave 0 for Free Shipping.</p>
                            </div>

                            <!-- Custom Zones -->
                            <div id="xep-shipping-zones-wrap">
                                <h4 style="color:#fff; margin-bottom:15px; font-size:16px;">Custom Shipping Zones</h4>
                                <input type="hidden" name="xepmarket2_shipping_zones" id="xepmarket2_shipping_zones" value="<?php echo esc_attr(get_option('xepmarket2_shipping_zones', '[]')); ?>" />
                                <div id="xep-shipping-zones-list" style="display:flex; flex-direction:column; gap:20px;">
                                    <!-- Populated by JS -->
                                </div>
                                <button type="button" class="xep-save-btn" onclick="xepAddShippingZone()" style="margin-top:20px; border-radius:6px; padding: 10px 16px; background:var(--admin-secondary); color:#fff; border:none; cursor:pointer;"><i class="fas fa-plus"></i> Add New Zone</button>
                            </div>
                        </div>

                        <script>
                        (function() {
                            const rawZones = document.getElementById('xepmarket2_shipping_zones').value;
                            let shippingZones = [];
                            try { shippingZones = JSON.parse(rawZones); } catch(e){}

                            // Get all countries defined earlier for the exclusion UI
                            const allCountriesJson = <?php echo !empty($all_countries) ? json_encode($all_countries) : '{}'; ?>;

                            function renderShippingZones() {
                                const list = document.getElementById('xep-shipping-zones-list');
                                list.innerHTML = '';

                                if(shippingZones.length === 0) {
                                    list.innerHTML = '<p style="color:#aaa; font-style:italic;">No custom zones defined. Base cost applies to all valid countries.</p>';
                                }

                                shippingZones.forEach((zone, index) => {
                                    const id = 'zone_' + Math.random().toString(36).substr(2, 9);
                                    zone.id = zone.id || id;
                                    
                                    let availOptions = '';
                                    let selOptions = '';
                                    let hiddenSelectOpts = '';
                                    
                                    for (const [code, name] of Object.entries(allCountriesJson)) {
                                        const escName = name.replace(/'/g, "&#39;"); // simple escape
                                        if (zone.countries && zone.countries.includes(code)) {
                                            selOptions += `<option value="${code}">${escName} (${code})</option>`;
                                            hiddenSelectOpts += `<option value="${code}" selected>${escName} (${code})</option>`;
                                        } else {
                                            availOptions += `<option value="${code}">${escName} (${code})</option>`;
                                        }
                                    }

                                    const hiddenHtml = `<select multiple class="xep-zone-countries" data-index="${index}" style="display:none;">${hiddenSelectOpts}</select>`;

                                    let dualListHtml = `
                                        <div style="display:flex; gap:20px; align-items:stretch;">
                                            <div style="flex:1; display:flex; flex-direction:column;">
                                                <label style="font-weight:600; margin-bottom:5px; font-size:12px; color:var(--admin-text);">Available Countries</label>
                                                <input type="text" class="xep-zone-search-avail" data-index="${index}" placeholder="Search..." style="margin-bottom:8px; width:100%; background:rgba(0,0,0,0.1) !important; color:#fff; padding:6px 10px; border-radius:4px; border:1px solid var(--admin-border);" />
                                                <select multiple class="xep-zone-avail-list" data-index="${index}" style="width:100%; height:160px; background:rgba(0,0,0,0.2); border:1px solid var(--admin-border); color:#fff; padding:10px; border-radius:8px; font-family:inherit; font-size:13px;">
                                                    ${availOptions}
                                                </select>
                                            </div>
                                            <div style="display:flex; flex-direction:column; justify-content:center; gap:10px;">
                                                <button type="button" class="xep-zone-add-btn" data-index="${index}" style="padding:8px 12px; background:var(--admin-secondary); border:none; border-radius:4px; color:#fff; cursor:pointer;" title="Add"><i class="fas fa-chevron-right"></i></button>
                                                <button type="button" class="xep-zone-remove-btn" data-index="${index}" style="padding:8px 12px; background:rgba(255,255,255,0.1); border:none; border-radius:4px; color:#fff; cursor:pointer;" title="Remove"><i class="fas fa-chevron-left"></i></button>
                                            </div>
                                            <div style="flex:1; display:flex; flex-direction:column;">
                                                <label style="font-weight:600; margin-bottom:5px; font-size:12px; color:var(--admin-primary);">Selected Countries</label>
                                                <input type="text" class="xep-zone-search-sel" data-index="${index}" placeholder="Search..." style="margin-bottom:8px; width:100%; background:rgba(0,0,0,0.1) !important; color:#fff; padding:6px 10px; border-radius:4px; border:1px solid var(--admin-border);" />
                                                <select multiple class="xep-zone-sel-list" data-index="${index}" style="width:100%; height:160px; background:rgba(0,0,0,0.2); border:1px solid var(--admin-primary); box-shadow:0 0 10px rgba(0,242,255,0.1); color:#fff; padding:10px; border-radius:8px; font-family:inherit; font-size:13px;">
                                                    ${selOptions}
                                                </select>
                                            </div>
                                        </div>
                                    `;

                                    const row = document.createElement('div');
                                    row.style.cssText = 'background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 12px; display: flex; flex-direction: column; gap: 20px; position:relative;';
                                    row.innerHTML = `
                                        <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                                            <div style="display:flex; gap: 20px; flex:1;">
                                                <div style="flex:1;">
                                                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--admin-text);">Label Name <span style="color:#ff6b6b">*</span></label>
                                                    <input type="text" class="xep-zone-name" data-index="${index}" value="${zone.name || ''}" placeholder="e.g. Europe Standard, USA Express..." style="width:100%; background: rgba(0,0,0,0.1) !important; color:#fff; padding:10px; border-radius:6px; border:1px solid var(--admin-border);" />
                                                </div>
                                                <div style="width: 150px;">
                                                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:var(--admin-text);">Cost ($)</label>
                                                    <div style="display:flex; align-items:center;">
                                                        <span style="background:rgba(0,0,0,0.3); padding:10px; border-radius:6px 0 0 6px; border:1px solid var(--admin-border); border-right:none; color:#fff;">$</span>
                                                        <input type="number" step="0.01" min="0" class="xep-zone-cost" data-index="${index}" value="${zone.cost !== undefined ? zone.cost : 0}" style="width:100%; background: rgba(0,0,0,0.1) !important; color:#fff; padding:10px; border-radius:0 6px 6px 0; border:1px solid var(--admin-border);" />
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" onclick="xepRemoveShippingZone(${index})" style="margin-left:20px; padding: 10px 15px; border-radius:6px; background:rgba(255,0,0,0.1); color:#ff4444; border:1px solid rgba(255,0,0,0.2); cursor:pointer; transition:all 0.3s;" onmouseover="this.style.background='rgba(255,0,0,0.2)'" onmouseout="this.style.background='rgba(255,0,0,0.1)'" title="Delete Zone"><i class="fas fa-trash"></i></button>
                                        </div>
                                        ${hiddenHtml}
                                        ${dualListHtml}
                                    `;
                                    list.appendChild(row);
                                });
                                
                                // Attach base listeners
                                list.querySelectorAll('.xep-zone-name').forEach(inp => inp.addEventListener('input', updateShippingZoneData));
                                list.querySelectorAll('.xep-zone-cost').forEach(inp => inp.addEventListener('input', updateShippingZoneData));

                                // Attach dual-list listeners
                                function updateZoneHiddenSelect(index) {
                                    const hiddenSel = document.querySelector(`.xep-zone-countries[data-index="${index}"]`);
                                    const selList = document.querySelector(`.xep-zone-sel-list[data-index="${index}"]`);
                                    if(hiddenSel && selList) {
                                        hiddenSel.innerHTML = '';
                                        Array.from(selList.options).forEach(opt => {
                                            const newOpt = document.createElement('option');
                                            newOpt.value = opt.value;
                                            newOpt.selected = true;
                                            hiddenSel.appendChild(newOpt);
                                        });
                                        updateShippingZoneData();
                                    }
                                }

                                function moveZoneOptions(sourceList, targetList, idx) {
                                    const selectedOpts = Array.from(sourceList.selectedOptions);
                                    if(selectedOpts.length === 0) return;
                                    selectedOpts.forEach(opt => {
                                        if(!opt.disabled) {
                                            const newO = opt.cloneNode(true);
                                            newO.selected = false;
                                            targetList.appendChild(newO);
                                            opt.remove();
                                        }
                                    });
                                    // sort
                                    const opts = Array.from(targetList.options);
                                    opts.sort((a,b) => a.text.localeCompare(b.text));
                                    targetList.innerHTML = '';
                                    opts.forEach(o => targetList.appendChild(o));
                                    updateZoneHiddenSelect(idx);
                                }

                                list.querySelectorAll('.xep-zone-add-btn').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const idx = this.getAttribute('data-index');
                                        const avail = document.querySelector(`.xep-zone-avail-list[data-index="${idx}"]`);
                                        const sel = document.querySelector(`.xep-zone-sel-list[data-index="${idx}"]`);
                                        moveZoneOptions(avail, sel, idx);
                                    });
                                });

                                list.querySelectorAll('.xep-zone-remove-btn').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const idx = this.getAttribute('data-index');
                                        const avail = document.querySelector(`.xep-zone-avail-list[data-index="${idx}"]`);
                                        const sel = document.querySelector(`.xep-zone-sel-list[data-index="${idx}"]`);
                                        moveZoneOptions(sel, avail, idx);
                                    });
                                });

                                list.querySelectorAll('.xep-zone-avail-list').forEach(l => {
                                    l.addEventListener('dblclick', function(e) {
                                        if(e.target.tagName === 'OPTION') {
                                            const idx = this.getAttribute('data-index');
                                            const sel = document.querySelector(`.xep-zone-sel-list[data-index="${idx}"]`);
                                            moveZoneOptions(this, sel, idx);
                                        }
                                    });
                                });

                                list.querySelectorAll('.xep-zone-sel-list').forEach(l => {
                                    l.addEventListener('dblclick', function(e) {
                                        if(e.target.tagName === 'OPTION') {
                                            const idx = this.getAttribute('data-index');
                                            const avail = document.querySelector(`.xep-zone-avail-list[data-index="${idx}"]`);
                                            moveZoneOptions(this, avail, idx);
                                        }
                                    });
                                });

                                // Search
                                list.querySelectorAll('.xep-zone-search-avail').forEach(inp => {
                                    inp.addEventListener('input', function() {
                                        const term = this.value.toLowerCase();
                                        const idx = this.getAttribute('data-index');
                                        const avail = document.querySelector(`.xep-zone-avail-list[data-index="${idx}"]`);
                                        Array.from(avail.options).forEach(opt => {
                                            opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                                        });
                                    });
                                });

                                list.querySelectorAll('.xep-zone-search-sel').forEach(inp => {
                                    inp.addEventListener('input', function() {
                                        const term = this.value.toLowerCase();
                                        const idx = this.getAttribute('data-index');
                                        const sel = document.querySelector(`.xep-zone-sel-list[data-index="${idx}"]`);
                                        Array.from(sel.options).forEach(opt => {
                                            opt.style.display = opt.text.toLowerCase().includes(term) ? '' : 'none';
                                        });
                                    });
                                });
                            }

                            function updateShippingZoneData() {
                                shippingZones.forEach((zone, index) => {
                                    const nameInput = document.querySelector(`.xep-zone-name[data-index="${index}"]`);
                                    const costInput = document.querySelector(`.xep-zone-cost[data-index="${index}"]`);
                                    const hiddenSelect = document.querySelector(`.xep-zone-countries[data-index="${index}"]`);
                                    
                                    if (nameInput) zone.name = nameInput.value;
                                    if (costInput) zone.cost = costInput.value;
                                    if (hiddenSelect) {
                                        zone.countries = Array.from(hiddenSelect.options).map(opt => opt.value);
                                    }
                                });
                                document.getElementById('xepmarket2_shipping_zones').value = JSON.stringify(shippingZones);
                            }

                            window.xepAddShippingZone = function() {
                                shippingZones.push({ id: 'zone_' + Math.random().toString(36).substr(2, 9), name: '', cost: '0', countries: [] });
                                renderShippingZones();
                                updateShippingZoneData();
                            };

                            window.xepRemoveShippingZone = function(index) {
                                if(confirm('Are you sure you want to remove this shipping zone?')) {
                                    shippingZones.splice(index, 1);
                                    renderShippingZones();
                                    updateShippingZoneData();
                                }
                            };

                            // Initialize
                            renderShippingZones();
                        })();
                        </script>
                        </div>
                    </div>

                    <!-- Tab: Checkout Customization -->
                    <div id="tab-checkout" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3>Checkout Field Settings</h3>
                            <p class="description" style="margin-bottom: 25px;">Enable or disable specific billing and shipping fields required from customers during checkout.</p>

                            <?php
                            $chk_fields_config = [
                                'first_name' => ['label' => 'First Name', 'default' => '1', 'order' => '1'],
                                'last_name'  => ['label' => 'Last Name',  'default' => '1', 'order' => '1'],
                                'company'    => ['label' => 'Company',    'default' => '0', 'order' => '2'],
                                'country'    => ['label' => 'Country',    'default' => '1', 'order' => '3'],
                                'address_1'  => ['label' => 'Address Line 1', 'default' => '1', 'order' => '4'],
                                'address_2'  => ['label' => 'Address Line 2', 'default' => '0', 'order' => '5'],
                                'city'       => ['label' => 'City',       'default' => '1', 'order' => '6'],
                                'state'      => ['label' => 'State / County', 'default' => '1', 'order' => '6'],
                                'postcode'   => ['label' => 'Postcode / ZIP', 'default' => '1', 'order' => '7'],
                                'phone'      => ['label' => 'Phone Number',   'default' => '1', 'order' => '8'],
                                'email'      => ['label' => 'Email Address',  'default' => '1', 'order' => '9'],
                                'telegram'   => ['label' => 'Telegram Username', 'default' => '1', 'order' => '8'],
                            ];

                            // Build sorted_fields for template access
                            $sorted_fields = [];
                            foreach ($chk_fields_config as $fid => $fdata) {
                                $saved_order = intval(get_option('xepmarket2_chk_order_' . $fid, $fdata['order']));
                                $sorted_fields[$fid] = array_merge($fdata, ['saved_order' => $saved_order]);
                            }

                            $custom_fields_json = get_option('xepmarket2_chk_custom_fields', '[]');
                            $custom_fields_list = json_decode($custom_fields_json, true);
                            if (!is_array($custom_fields_list)) $custom_fields_list = [];

                            // Build all_items list
                            $all_items = [];
                            foreach ($sorted_fields as $fid => $fdata) {
                                $all_items[] = [
                                    'type'  => 'standard',
                                    'id'    => $fid,
                                    'order' => $fdata['saved_order'],
                                    'width' => get_option('xepmarket2_chk_width_' . $fid, 'half'),
                                ];
                            }
                            foreach ($custom_fields_list as $cf) {
                                $all_items[] = [
                                    'type'     => 'custom',
                                    'id'       => isset($cf['id']) ? $cf['id'] : ('custom_' . uniqid()),
                                    'order'    => isset($cf['order']) ? intval($cf['order']) : 99,
                                    'width'    => (isset($cf['width']) && $cf['width'] === 'full') ? 'full' : 'half',
                                    'label'    => isset($cf['label']) ? $cf['label'] : '',
                                    'required' => !empty($cf['required']),
                                ];
                            }
                            usort($all_items, function ($a, $b) {
                                if ($a['order'] !== $b['order']) return $a['order'] - $b['order'];
                                return strcmp($a['type'], $b['type']);
                            });

                            // Group into rows: same order + both half = side by side
                            $rows = [];
                            $i = 0;
                            while ($i < count($all_items)) {
                                $item = $all_items[$i];
                                if ($item['width'] === 'full') {
                                    $rows[] = ['left' => $item, 'right' => null, 'full' => true];
                                    $i++;
                                } else {
                                    if (($i + 1) < count($all_items)) {
                                        $next = $all_items[$i + 1];
                                        if ($next['width'] === 'half' && $next['order'] === $item['order']) {
                                            $rows[] = ['left' => $item, 'right' => $next, 'full' => false];
                                            $i += 2;
                                            continue;
                                        }
                                    }
                                    $rows[] = ['left' => $item, 'right' => null, 'full' => false];
                                    $i++;
                                }
                            }
                            ?>

                            <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">
                                <i class="fas fa-info-circle"></i>
                                Sort fields by entering a <strong>Priority No</strong>. Fields with the same priority and <strong>2/1</strong> width will appear <strong>side-by-side</strong>. <strong>2/2 = Full Row</strong>. Changes apply after saving.
                            </p>

                            <style>
                                .xep-field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-width: 900px; }
                                .xep-field-grid .xep-field-full { grid-column: 1 / -1; }
                                .xep-field-card { display: flex; align-items: center; border: 1px solid var(--admin-border); padding: 12px 14px; border-radius: 10px; background: rgba(255,255,255,0.02); gap: 10px; box-sizing: border-box; min-height: 60px; }
                                .xep-field-card:hover { background: rgba(0,242,255,0.04); }
                                .xep-order-badge { display: flex; align-items: center; justify-content: center; background: rgba(0,242,255,0.08); border: 1px solid rgba(0,242,255,0.3); border-radius: 8px; padding: 3px 5px; flex-shrink: 0; }
                                .xep-order-badge input[type=number] { width: 38px; background: transparent; border: none; outline: none; color: var(--admin-primary); font-weight: 700; font-size: 14px; text-align: center; -moz-appearance: textfield; }
                                .xep-order-badge input[type=number]::-webkit-inner-spin-button, .xep-order-badge input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
                                .xep-field-card .xep-width-selector { display:flex; align-items:center; gap:3px; flex-shrink: 0; }
                                .xep-field-card .xep-width-selector label { cursor:pointer; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:700; }
                                .xep-field-card .xep-width-selector input[type=radio] { display:none; }
                            </style>

                            <div class="xep-field-grid">
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $left  = $row['left'];
                                    $right = $row['right'];
                                    $cls   = $row['full'] ? 'xep-field-full' : '';
                                    ?>
                                    <div class="<?php echo $cls; ?>">
                                        <?php if ($left['type'] === 'standard'): $fid = $left['id']; $fdata = $sorted_fields[$fid]; ?>
                                        <div class="xep-field-card">
                                            <div class="xep-order-badge" title="Priority No">
                                                <input type="number" name="xepmarket2_chk_order_<?php echo esc_attr($fid); ?>" value="<?php echo esc_attr($left['order']); ?>" min="1" max="99" />
                                            </div>
                                            <div style="flex:1;min-width:0;">
                                                <input type="text" name="xepmarket2_chk_name_<?php echo esc_attr($fid); ?>" value="<?php echo esc_attr(get_option('xepmarket2_chk_name_' . $fid, $fdata['label'])); ?>" style="border:none;border-bottom:1px dashed rgba(255,255,255,0.2);background:transparent;color:inherit;font-weight:700;font-size:14px;width:100%;padding:3px;" placeholder="<?php echo esc_attr($fdata['label']); ?>" />
                                            </div>
                                            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                                                <div class="xep-width-selector">
                                                    <label><input type="radio" name="xepmarket2_chk_width_<?php echo esc_attr($fid); ?>" value="half" <?php checked(get_option('xepmarket2_chk_width_' . $fid, 'half'), 'half'); ?>> 2/1</label>
                                                    <label><input type="radio" name="xepmarket2_chk_width_<?php echo esc_attr($fid); ?>" value="full" <?php checked(get_option('xepmarket2_chk_width_' . $fid, 'half'), 'full'); ?>> 2/2</label>
                                                </div>
                                                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--text-muted);font-size:12px;white-space:nowrap;"><input type="checkbox" name="xepmarket2_chk_req_<?php echo esc_attr($fid); ?>" value="1" <?php checked(1, get_option('xepmarket2_chk_req_' . $fid, '1'), true); ?> /> REQ</label>
                                                <label class="xep-switch"><input type="checkbox" name="xepmarket2_chk_<?php echo esc_attr($fid); ?>" value="1" <?php checked(1, get_option('xepmarket2_chk_' . $fid, $fdata['default']), true); ?> /><span class="xep-slider"></span></label>
                                            </div>
                                        </div>
                                        <?php else: $cf = $left; $cfid = esc_attr($cf['id']); ?>
                                        <div class="xep-field-card xep-cf-row" data-cf-id="<?php echo $cfid; ?>">
                                            <div class="xep-order-badge" title="Priority No">
                                                <input type="number" class="xep-cf-order" value="<?php echo esc_attr($cf['order']); ?>" min="1" max="99" />
                                            </div>
                                            <div style="flex:1;min-width:0;">
                                                <input type="text" class="xep-cf-label" value="<?php echo esc_attr($cf['label']); ?>" placeholder="Custom field label" style="border:none;border-bottom:1px dashed rgba(255,255,255,0.2);background:transparent;color:inherit;font-weight:700;font-size:14px;width:100%;padding:3px;" />
                                            </div>
                                            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                                                <div class="xep-width-selector">
                                                    <label><input type="radio" class="xep-cf-width" name="xep_cf_width_<?php echo $cfid; ?>" value="half" <?php checked($cf['width'], 'half'); ?>> 2/1</label>
                                                    <label><input type="radio" class="xep-cf-width" name="xep_cf_width_<?php echo $cfid; ?>" value="full" <?php checked($cf['width'], 'full'); ?>> 2/2</label>
                                                </div>
                                                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--text-muted);font-size:12px;white-space:nowrap;"><input type="checkbox" class="xep-cf-required" <?php checked($cf['required']); ?> /> REQ</label>
                                                <button type="button" class="xep-cf-remove" style="background:rgba(255,69,58,0.1);color:#ff453a;border:1px solid rgba(255,69,58,0.2);padding:5px 9px;border-radius:7px;cursor:pointer;font-size:11px;"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!$row['full'] && $right): ?>
                                    <div>
                                        <?php if ($right['type'] === 'standard'): $fid = $right['id']; $fdata = $sorted_fields[$fid]; ?>
                                        <div class="xep-field-card">
                                            <div class="xep-order-badge" title="Priority No">
                                                <input type="number" name="xepmarket2_chk_order_<?php echo esc_attr($fid); ?>" value="<?php echo esc_attr($right['order']); ?>" min="1" max="99" />
                                            </div>
                                            <div style="flex:1;min-width:0;">
                                                <input type="text" name="xepmarket2_chk_name_<?php echo esc_attr($fid); ?>" value="<?php echo esc_attr(get_option('xepmarket2_chk_name_' . $fid, $fdata['label'])); ?>" style="border:none;border-bottom:1px dashed rgba(255,255,255,0.2);background:transparent;color:inherit;font-weight:700;font-size:14px;width:100%;padding:3px;" placeholder="<?php echo esc_attr($fdata['label']); ?>" />
                                            </div>
                                            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                                                <div class="xep-width-selector">
                                                    <label><input type="radio" name="xepmarket2_chk_width_<?php echo esc_attr($fid); ?>" value="half" <?php checked(get_option('xepmarket2_chk_width_' . $fid, 'half'), 'half'); ?>> 2/1</label>
                                                    <label><input type="radio" name="xepmarket2_chk_width_<?php echo esc_attr($fid); ?>" value="full" <?php checked(get_option('xepmarket2_chk_width_' . $fid, 'half'), 'full'); ?>> 2/2</label>
                                                </div>
                                                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--text-muted);font-size:12px;white-space:nowrap;"><input type="checkbox" name="xepmarket2_chk_req_<?php echo esc_attr($fid); ?>" value="1" <?php checked(1, get_option('xepmarket2_chk_req_' . $fid, '1'), true); ?> /> REQ</label>
                                                <label class="xep-switch"><input type="checkbox" name="xepmarket2_chk_<?php echo esc_attr($fid); ?>" value="1" <?php checked(1, get_option('xepmarket2_chk_' . $fid, $fdata['default']), true); ?> /><span class="xep-slider"></span></label>
                                            </div>
                                        </div>
                                        <?php else: $cf = $right; $cfid = esc_attr($cf['id']); ?>
                                        <div class="xep-field-card xep-cf-row" data-cf-id="<?php echo $cfid; ?>">
                                            <div class="xep-order-badge" title="Priority No">
                                                <input type="number" class="xep-cf-order" value="<?php echo esc_attr($cf['order']); ?>" min="1" max="99" />
                                            </div>
                                            <div style="flex:1;min-width:0;">
                                                <input type="text" class="xep-cf-label" value="<?php echo esc_attr($cf['label']); ?>" placeholder="Custom field label" style="border:none;border-bottom:1px dashed rgba(255,255,255,0.2);background:transparent;color:inherit;font-weight:700;font-size:14px;width:100%;padding:3px;" />
                                            </div>
                                            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                                                <div class="xep-width-selector">
                                                    <label><input type="radio" class="xep-cf-width" name="xep_cf_width_<?php echo $cfid; ?>" value="half" <?php checked($cf['width'], 'half'); ?>> 2/1</label>
                                                    <label><input type="radio" class="xep-cf-width" name="xep_cf_width_<?php echo $cfid; ?>" value="full" <?php checked($cf['width'], 'full'); ?>> 2/2</label>
                                                </div>
                                                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--text-muted);font-size:12px;white-space:nowrap;"><input type="checkbox" class="xep-cf-required" <?php checked($cf['required']); ?> /> REQ</label>
                                                <button type="button" class="xep-cf-remove" style="background:rgba(255,69,58,0.1);color:#ff453a;border:1px solid rgba(255,69,58,0.2);padding:5px 9px;border-radius:7px;cursor:pointer;font-size:11px;"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif (!$row['full']): ?>
                                    <div></div>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                            </div>

                            <script>
                            (function(){
                                var G=document.querySelector('.xep-field-grid');
                                if(!G)return;
                                function styleBtn(box){
                                    box.querySelectorAll('label').forEach(function(l){
                                        var r=l.querySelector('input[type=radio]');if(!r)return;
                                        l.style.background=r.checked?'var(--admin-primary)':'rgba(255,255,255,0.06)';
                                        l.style.color=r.checked?'#000':'var(--text-muted)';
                                        l.style.transition='background 0.15s';
                                    });
                                }
                                G.querySelectorAll('.xep-width-selector').forEach(styleBtn);
                                function collect(){
                                    var list=[];
                                    G.querySelectorAll('.xep-field-card').forEach(function(card){
                                        var ni=card.querySelector('input[type=number]');
                                        var ri=card.querySelector('input[type=radio]:checked');
                                        list.push({el:card, order:ni?parseInt(ni.value,10)||1:99, width:ri?ri.value:'half'});
                                    });
                                    return list;
                                }
                                // Cache all cards on load to prevent loss
                                var ALL_CARDS = Array.from(G.querySelectorAll('.xep-field-card'));
                                
                                function reorder(){
                                    // Save focus
                                    var activeEl = document.activeElement;
                                    var focusPath = activeEl ? { name: activeEl.name, class: activeEl.className, value: activeEl.value } : null;
                                    var selStart = activeEl ? activeEl.selectionStart : null;

                                    // Sort based on current inputs
                                    var sorted = ALL_CARDS.map(function(el){
                                        var ni = el.querySelector('input[type=number]');
                                        var ri = el.querySelector('input[type=radio]:checked');
                                        return { el: el, order: parseInt(ni.value,10)||99, width: ri?ri.value:'half' };
                                    }).sort(function(a,b){ return a.order - b.order; });

                                    var frag = document.createDocumentFragment();
                                    var i = 0;
                                    while(i < sorted.length){
                                        var c = sorted[i];
                                        if(c.width === 'full'){
                                            var d = document.createElement('div'); d.className = 'xep-field-full';
                                            d.appendChild(c.el); frag.appendChild(d);
                                            i++;
                                        } else if(i+1 < sorted.length && sorted[i+1].width === 'half' && sorted[i+1].order === c.order){
                                            var dL = document.createElement('div'); dL.appendChild(c.el); frag.appendChild(dL);
                                            var dR = document.createElement('div'); dR.appendChild(sorted[i+1].el); frag.appendChild(dR);
                                            i += 2;
                                        } else {
                                            var dS = document.createElement('div'); dS.appendChild(c.el); frag.appendChild(dS);
                                            frag.appendChild(document.createElement('div')); // filler
                                            i++;
                                        }
                                    }

                                    // Efficient Clear and Append
                                    while(G.firstChild) G.removeChild(G.firstChild);
                                    G.appendChild(frag);

                                    // Restore focus
                                    if(focusPath){
                                        var refocused = null;
                                        if(focusPath.name) refocused = G.querySelector('[name="'+focusPath.name+'"]');
                                        if(!refocused && focusPath.class) refocused = G.querySelector('.' + focusPath.class.split(' ').join('.'));
                                        
                                        if(refocused){
                                            refocused.focus();
                                            if(selStart !== null) refocused.setSelectionRange(selStart, selStart);
                                        }
                                    }
                                    G.querySelectorAll('.xep-width-selector').forEach(styleBtn);
                                }

                                G.addEventListener('change', function(e){
                                    if(e.target.type === 'radio' || e.target.type === 'number'){
                                        if(e.target.type === 'radio'){ var b = e.target.closest('.xep-width-selector'); if(b) styleBtn(b); }
                                        reorder();
                                    }
                                });

                                G.addEventListener('input', function(e){
                                    if(e.target.type === 'number'){
                                        clearTimeout(e.target._t);
                                        e.target._t = setTimeout(reorder, 100);
                                    }
                                });
                            })();
                            </script>

                            <!-- Custom Fields Repeater -->
                            <div style="margin-top: 40px; border-top: 2px solid var(--admin-border); padding-top: 20px;">
                                <h3 style="color: var(--admin-primary);"><i class="fas fa-plus-circle"></i> Add Custom
                                    Fields</h3>
                                <p class="description" style="margin-bottom: 20px;">Custom fields appear <strong>in the same grid above</strong> with standard fields (sorted by order). Choose <strong>2/1</strong> (half) or <strong>2/2</strong> (full row). Use <strong>Remove</strong> on each card to delete.</p>

                                <input type="hidden" name="xepmarket2_chk_custom_fields" id="xep_custom_fields_data"
                                    value="<?php echo esc_attr(get_option('xepmarket2_chk_custom_fields', '[]')); ?>" />

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
                            (function(){
                                var DI=document.getElementById('xep_custom_fields_data');
                                var FRM=document.getElementById('xep-settings-form');
                                var ABTN=document.getElementById('xep_add_custom_field_btn');
                                var CF=[];
                                try{CF=JSON.parse(DI?DI.value:'[]');if(!Array.isArray(CF))CF=[];}catch(e){CF=[];}
                                function save(){if(DI)DI.value=JSON.stringify(CF);}
                                function nextOrder(){var m=0;CF.forEach(function(f){m=Math.max(m,parseInt(f.order,10)||0);});return m+1;}
                                if(ABTN&&FRM){ABTN.addEventListener('click',function(){CF.push({id:'custom_'+Date.now(),label:'',order:nextOrder(),width:'full',required:false});save();FRM.submit();});}
                                if(FRM){FRM.addEventListener('submit',function(){save();});}
                                /* Event delegation - Remove button */
                                document.addEventListener('click',function(e){
                                    var btn=e.target.closest('.xep-cf-remove');
                                    if(!btn)return;
                                    e.preventDefault();
                                    e.stopPropagation();
                                    var row=btn.closest('[data-cf-id]');
                                    if(!row)return;
                                    var id=row.getAttribute('data-cf-id');
                                    if(!confirm('Bu alanÃƒâ€Ã‚Â± silmek istediÃƒâ€Ã…Â¸inizden emin misiniz?'))return;
                                    CF=CF.filter(function(f){return String(f.id)!==String(id);});
                                    save();
                                    /* Submit form to reload */
                                    if(FRM){
                                        FRM.submit();
                                    } else {
                                        /* Fallback: remove from DOM */
                                        row.remove();
                                    }
                                });
                                /* Event delegation - Input changes */
                                document.addEventListener('input',function(e){
                                    var row=e.target.closest('.xep-cf-row');if(!row)return;
                                    var id=row.getAttribute('data-cf-id');
                                    var f=CF.find(function(x){return String(x.id)===String(id);});if(!f)return;
                                    if(e.target.classList.contains('xep-cf-label')){f.label=e.target.value;save();}
                                    if(e.target.classList.contains('xep-cf-order')){f.order=parseInt(e.target.value,10)||1;save();}
                                });
                                /* Event delegation - Checkbox/Radio changes */
                                document.addEventListener('change',function(e){
                                    var row=e.target.closest('.xep-cf-row');if(!row)return;
                                    var id=row.getAttribute('data-cf-id');
                                    var f=CF.find(function(x){return String(x.id)===String(id);});if(!f)return;
                                    if(e.target.classList.contains('xep-cf-required')){f.required=e.target.checked;save();}
                                    if(e.target.classList.contains('xep-cf-width')){f.width=e.target.value;save();}
                                });
                            })();
                            </script>
                        </div>
                    </div>

                    <!-- Tab: AliSync Helper -->
                    <div id="tab-alisync" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3 style="color: var(--admin-primary);"><i class="fas fa-sync-alt"></i> AliSync Helper <span style="font-size: 14px; font-weight: normal; opacity: 0.9;">ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Stock &amp; price update</span></h3>
                            <p class="description" style="margin-bottom: 25px;">Sync and manage products from AliExpress. Import listings, <strong>update prices and stock</strong>, and keep your catalog in sync.</p>
                            <p style="margin-bottom: 20px;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ali-sync-helper')); ?>" class="xep-save-btn" style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; font-size: 14px;">
                                    <i class="fas fa-external-link-alt"></i> Open AliSync Helper Dashboard
                                </a>
                            </p>
                            <p class="description" style="margin: 0;">Opens the AliSync Helper page in the same window. Install or activate the <strong>AliSync Helper</strong> module from Theme Modules if the link does not work.</p>
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

                    <!-- Tab: Telegram Bot -->
                    <div id="tab-telegram" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3 style="color: var(--admin-primary);"><i class="dashicons dashicons-format-chat"></i> Telegram Order Notifications</h3>
                            <p class="description" style="margin-bottom: 25px;">Send WooCommerce order and status updates to a Telegram chat. Get the bot token from <a href="https://t.me/BotFather" target="_blank" rel="noopener" style="color: var(--admin-primary);">@BotFather</a>.</p>

                            <div class="xep-form-group" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--admin-border);">
                                <div>
                                    <h4 style="margin: 0 0 5px 0;">Enable Telegram Bot</h4>
                                    <p class="description" style="margin: 0;">Turn notifications on or off.</p>
                                </div>
                                <label style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="hidden" name="xep_tg_bot_enabled" value="no">
                                    <input type="checkbox" name="xep_tg_bot_enabled" value="yes" <?php checked(get_option('xep_tg_bot_enabled'), 'yes'); ?>>
                                    <span>Active</span>
                                </label>
                            </div>

                            <div class="xep-form-group">
                                <label>Bot Token</label>
                                <input type="text" name="xep_tg_bot_token" class="xep-form-control"
                                    value="<?php echo esc_attr(get_option('xep_tg_bot_token')); ?>"
                                    placeholder="1234567890:ABCdefGhIJKlmNoPQRsTUVWxyz">
                            </div>
                            <div class="xep-form-group">
                                <label>Chat ID</label>
                                <input type="text" name="xep_tg_bot_chat_id" class="xep-form-control"
                                    value="<?php echo esc_attr(get_option('xep_tg_bot_chat_id')); ?>"
                                    placeholder="-1001234567890 or your user ID">
                                <p class="description" style="margin-top: 5px;">Group, channel, or user ID to receive messages.</p>
                            </div>

                            <h4 style="margin-top: 30px; margin-bottom: 15px;">Message Templates</h4>
                            <p class="description" style="margin-bottom: 15px;">Use: <code>{order_id}</code> <code>{status}</code> <code>{total}</code> <code>{customer_name}</code> <code>{telegram_username}</code> <code>{items}</code>. HTML like &lt;b&gt; is supported.</p>
                            <div class="xep-form-group">
                                <label>New order message</label>
                                <textarea name="xep_tg_bot_msg_new_order" class="xep-form-control" rows="5" style="resize: vertical;"><?php echo esc_textarea(get_option('xep_tg_bot_msg_new_order', "Ã„Å¸Ã…Â¸Ã¢â‚¬ÂºÃ¢â‚¬â„¢ <b>NEW ORDER</b>\n\n<b>Order:</b> #{order_id}\n<b>Customer:</b> {customer_name}\n<b>Total:</b> {total}\n<b>Items:</b>\n{items}")); ?></textarea>
                            </div>
                            <div class="xep-form-group">
                                <label>Status changed message</label>
                                <textarea name="xep_tg_bot_msg_status_changed" class="xep-form-control" rows="4" style="resize: vertical;"><?php echo esc_textarea(get_option('xep_tg_bot_msg_status_changed', "Ã„Å¸Ã…Â¸Ã¢â‚¬ÂÃ¢â‚¬Â <b>ORDER #{order_id}</b> ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ {status}\n<b>Customer:</b> {customer_name}\n<b>Total:</b> {total}")); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Affiliate -->
                    <div id="tab-affiliate" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3 style="color: var(--admin-primary);"><i class="fas fa-handshake"></i> Affiliate System</h3>
                            <p class="description" style="margin-bottom: 25px;">Referral commissions for completed orders. Users get a unique link from My Account ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Affiliate Dashboard. Full list and "Mark as paid": <a href="<?php echo esc_url(admin_url('admin.php?page=xepmarket2-affiliate')); ?>">WooCommerce ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Affiliates</a>.</p>
                            <div class="xep-form-group">
                                <label>Commission rate (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="omnixep_affiliate_rate"
                                    value="<?php echo esc_attr(get_option('omnixep_affiliate_rate', 10)); ?>">
                                <p class="description">Percentage of order subtotal granted to the affiliate when the order is completed.</p>
                            </div>
                            <div class="xep-form-group">
                                <label>Cookie duration (days)</label>
                                <input type="number" min="1" name="omnixep_affiliate_cookie_days"
                                    value="<?php echo esc_attr(get_option('omnixep_affiliate_cookie_days', 30)); ?>">
                                <p class="description">How long the referral link stays attributed to the affiliate.</p>
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
                                <p style="max-width: 500px; margin: 0 auto 35px; opacity: 0.8; line-height: 1.6;">Creates
                                    missing Home, Shop, Swap and WooCommerce pages and sets up the menu. <strong>Your current theme settings are not changed.</strong></p>

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
                                        <h4 style="color: #ff453a; margin-bottom: 5px;">Reset to current state</h4>
                                        <p style="font-size: 13px; opacity: 0.7; margin-bottom: 15px;">Restore theme settings and menus to the last <strong>saved state</strong>. Save your current setup first with the button below, then use this to revert after changes.</p>
                                        <p style="font-size: 12px; opacity: 0.6; margin-bottom: 12px;">
                                            <button type="button" id="xep-save-current-state" class="button" style="margin-right: 8px;">Save current state</button>
                                            <span id="xep-save-state-status"></span>
                                        </p>
                                        <button type="button" id="xep-factory-reset" class="xep-save-btn"
                                            style="background: linear-gradient(135deg, #ff453a, #d32f2f) !important; width: auto !important; padding: 10px 25px !important; font-size: 12px !important;">RESTORE
                                            TO SAVED STATE</button>
                                    </div>
                                </div>
                            </div>

                            <div
                                style="margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid var(--admin-border);">
                                <h4 style="margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Important Information
                                </h4>
                                <ul style="margin: 0; padding-left: 20px; font-size: 13px; opacity: 0.7; line-height: 1.8;">
                                    <li>Existing posts, products and pages will <strong>not</strong> be modified.</li>
                                    <li><strong>Install</strong>: Only creates missing pages and menu; theme settings stay as they are.</li>
                                    <li><strong>Reset</strong>: Restores theme settings to the last &quot;Save current state&quot; snapshot.</li>
                                    <li>Use &quot;Save current state&quot; before making big changes, then &quot;Restore to saved state&quot; to go back.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Mail Settings -->
                    <div id="tab-mail" class="xep-tab-content">
                        <div class="xep-section-card">
                            <h3 style="color: var(--admin-primary);"><i class="fas fa-envelope"></i> SMTP Mail Server Settings</h3>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; padding: 15px; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid var(--admin-border);">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: var(--admin-primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fas fa-power-off"></i>
                                    </div>
                                    <div>
                                        <h4 style="margin: 0; font-size: 15px;">Enable SMTP Module</h4>
                                        <p style="margin: 0; font-size: 12px; opacity: 0.6;">Turn off if you use a 3rd party SMTP plugin.</p>
                                    </div>
                                </div>
                                <label class="xep-switch">
                                    <input type="checkbox" name="xep_smtp_enable" value="1" <?php checked(1, get_option('xep_smtp_enable', '0')); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                            </div>

                            <p class="description" style="margin-bottom: 25px;">Configure your outgoing mail server (SMTP) to ensure transactional emails (orders, resets) are delivered reliably.</p>
                            
                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>SMTP Host</label>
                                    <input type="text" name="xep_smtp_host" value="<?php echo esc_attr(get_option('xep_smtp_host', 'localhost')); ?>" placeholder="localhost" />
                                </div>
                                <div class="xep-form-group">
                                    <label>SMTP Port</label>
                                    <input type="text" name="xep_smtp_port" value="<?php echo esc_attr(get_option('xep_smtp_port', '465')); ?>" placeholder="465" />
                                </div>
                            </div>

                            <div class="xep-grid-2" style="margin-top: 20px;">
                                <div class="xep-form-group">
                                    <label>Encryption</label>
                                    <select name="xep_smtp_encryption">
                                        <option value="ssl" <?php selected('ssl', get_option('xep_smtp_encryption', 'ssl')); ?>>SSL (Recommended for 465)</option>
                                        <option value="tls" <?php selected('tls', get_option('xep_smtp_encryption')); ?>>TLS (Recommended for 587)</option>
                                        <option value="none" <?php selected('none', get_option('xep_smtp_encryption')); ?>>None</option>
                                    </select>
                                </div>
                                <div class="xep-form-group" style="display: flex; align-items: center; gap: 15px; padding-top: 25px;">
                                    <label class="xep-switch">
                                        <input type="checkbox" name="xep_smtp_auth" value="1" <?php checked(1, get_option('xep_smtp_auth', '1')); ?> />
                                        <span class="xep-slider"></span>
                                    </label>
                                    <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">Enable SMTP Authentication</span>
                                </div>
                            </div>

                            <div class="xep-form-group" style="display: flex; align-items: center; gap: 15px; margin-top: 15px;">
                                <label class="xep-switch">
                                    <input type="checkbox" name="xep_smtp_insecure" value="1" <?php checked(1, get_option('xep_smtp_insecure', '1')); ?> />
                                    <span class="xep-slider"></span>
                                </label>
                                <span style="font-size: 13px; font-weight: 600; color: #ffbc00;"><i class="fas fa-exclamation-triangle"></i> Disable SSL Verification (Try this if connection fails)</span>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--admin-border); margin: 25px 0;">

                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>SMTP Username</label>
                                    <input type="text" name="xep_smtp_username" value="<?php echo esc_attr(get_option('xep_smtp_username')); ?>" placeholder="user@example.com" />
                                </div>
                                <div class="xep-form-group">
                                    <label>SMTP Password</label>
                                    <input type="password" name="xep_smtp_password" value="<?php echo esc_attr(get_option('xep_smtp_password')); ?>" placeholder="ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢" />
                                </div>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--admin-border); margin: 25px 0;">

                            <div class="xep-grid-2">
                                <div class="xep-form-group">
                                    <label>From Email Address</label>
                                    <input type="email" name="xep_smtp_from_email" value="<?php echo esc_attr(get_option('xep_smtp_from_email')); ?>" placeholder="no-reply@yourstore.com" />
                                    <p class="description">Deliver emails using this address.</p>
                                </div>
                                <div class="xep-form-group">
                                    <label>From Name</label>
                                    <input type="text" name="xep_smtp_from_name" value="<?php echo esc_attr(get_option('xep_smtp_from_name', get_bloginfo('name'))); ?>" />
                                    <p class="description">Displays as the sender name.</p>
                                </div>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--admin-border); margin: 25px 0;">

                            <div class="xep-section-card" style="background: rgba(50, 215, 75, 0.03); border: 1px solid rgba(50, 215, 75, 0.1); padding: 20px; border-radius: 12px;">
                                <h4 style="margin-top: 0; color: #32d74b; display: flex; align-items: center; gap: 8px;"><i class="fas fa-paper-plane"></i> Send Test Email</h4>
                                <div class="xep-form-group">
                                    <label>Recipient Email</label>
                                    <input type="email" id="xep_test_email_recipient" value="<?php echo esc_attr(get_option('admin_email')); ?>" placeholder="test@example.com" />
                                </div>
                                <button type="button" id="xep_send_test_email_btn" class="xep-save-btn" style="background: linear-gradient(135deg, #32d74b, #28a745) !important; height: 50px; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100% !important; border-radius: 12px !important; border: none !important; font-weight: 700 !important; font-size: 13px !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; box-shadow: 0 4px 15px rgba(50, 215, 75, 0.2) !important; margin-top: 15px;">
                                    <i class="fas fa-paper-plane"></i> <span class="btn-text">Send Test Email</span>
                                </button>
                                <div id="xep_test_email_msg" style="margin-top: 15px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px;"></div>
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


            // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Multi-Step Plugin Installation Logic ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
            function runSequentialPluginSync($btn, btnTextOriginal, btnIconOriginalClass) {
                var nonce = $btn.data('nonce');
                var $statusText = $('#xep-sync-status-text');
                var $percentage = $('#xep-sync-percentage');
                var $progressBar = $('#xep-sync-progress-bar');
                var $progressContainer = $('#xep-sync-progress-container');

                $btn.prop('disabled', true).find('.btn-text').text('Processing...');
                $btn.find('i').removeClass(btnIconOriginalClass).addClass('fa-spinner fa-spin');
                $progressContainer.fadeIn();

                function updateProgress(percent, status) {
                    $progressBar.css('width', percent + '%');
                    $percentage.text(Math.round(percent) + '%');
                    if (status) $statusText.text(status);
                }

                // Step 1: Prepare (Download & Extract)
                updateProgress(10, 'Downloading repository archive...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: { action: 'xep_prepare_plugins_sync', nonce: nonce },
                    success: function (res) {
                        try { if (typeof res === 'string') res = JSON.parse(res); } catch(e){}
                        if (res && res.success && res.data && res.data.slugs) {
                            var slugs = res.data.slugs;
                            var total = slugs.length;
                            var installed = 0;

                            if (total === 0) {
                                finalizeSync('No plugins found in repository.');
                                return;
                            }

                            // Step 2: Install plugins sequentially
                            function installNext() {
                                if (installed >= total) {
                                    finalizeSync();
                                    return;
                                }

                                var slug = slugs[installed];
                                var progress = 10 + ((installed / total) * 80);
                                updateProgress(progress, 'Installing ' + slug + '...');

                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    dataType: 'json',
                                    data: { action: 'xep_install_plugin_step', nonce: nonce, slug: slug },
                                    success: function (stepRes) {
                                        try { if (typeof stepRes === 'string') stepRes = JSON.parse(stepRes); } catch(e){}
                                        if (stepRes && stepRes.success) {
                                            installed++;
                                            installNext();
                                        } else {
                                            var errorMsg = stepRes && stepRes.data && stepRes.data.message ? stepRes.data.message : 'Failed to install ' + slug;
                                            handleError(errorMsg);
                                        }
                                    },
                                    error: function (xhr) {
                                        handleError('Request failed during installation of ' + slug + ' (Status: ' + xhr.status + ')');
                                    }
                                });
                            }

                            installNext();
                        } else {
                            var errorMsg = res && res.data && res.data.message ? res.data.message : 'Preparation failed.';
                            handleError(errorMsg);
                        }
                    },
                    error: function (xhr) {
                        handleError('Request failed during preparation (Status: ' + xhr.status + ').');
                    }
                });

                function finalizeSync(msg) {
                    updateProgress(95, 'Cleaning up temporary files...');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: { action: 'xep_finalize_plugins_sync', nonce: nonce },
                        success: function () {
                            updateProgress(100, 'Installation complete!');
                            setTimeout(function () {
                                alert(msg || 'All modules installed and updated successfully! Refreshing...');
                                window.location.reload();
                            }, 1000);
                        }
                    });
                }

                function handleError(errorMsg) {
                    alert('Sync Error: ' + errorMsg);
                    $btn.prop('disabled', false).find('.btn-text').text(btnTextOriginal);
                    $btn.find('i').removeClass('fa-spinner fa-spin').addClass(btnIconOriginalClass);
                    $statusText.text('Error: ' + errorMsg).css('color', '#ff453a');
                }
            }

            $('#xep-install-all-modules-btn').on('click', function () {
                if (!confirm('Download and install all required modules from GitHub?')) return;
                runSequentialPluginSync($(this), 'Install all required modules', 'fa-download');
            });

            $('#xep-github-sync-btn').on('click', function () {
                if (!confirm('Sync all plugins from GitHub repository?')) return;
                runSequentialPluginSync($(this), 'Sync from GitHub', 'fa-github');
            });

            // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ AJAX: Demo Import ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
            $('#xep-run-demo-import').on('click', function () {
                if (!confirm('Run setup? This will only add missing pages and menu; your theme settings will not change.')) return;

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

            // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ AJAX: Factory Reset ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
            $('#xep-factory-reset').on('click', function () {
                if (!confirm('Restore theme settings and menus to the last saved state?')) return;

                var $btn = $(this);
                var nonce = $('#xep_admin_ajax_nonce').val();

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> RESTORING...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'xep_factory_reset', nonce: nonce },
                    success: function (response) {
                        if (response.success) {
                            alert(response.data || 'Restored. Reloading...');
                            window.location.reload();
                        } else {
                            alert(response.data || 'Error: No saved state. Click "Save current state" first.');
                            $btn.prop('disabled', false).html('RESTORE TO SAVED STATE');
                        }
                    },
                    error: function () {
                        alert('Request failed.');
                        $btn.prop('disabled', false).html('RESTORE TO SAVED STATE');
                    }
                });
            });

            // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ AJAX: Save current state ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
            $('#xep-save-current-state').on('click', function () {
                var $btn = $(this);
                var $status = $('#xep-save-state-status');
                var nonce = $('#xep_admin_ajax_nonce').val();
                $btn.prop('disabled', true);
                $status.text('Saving...').css('color', '');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'xep_save_current_state', nonce: nonce },
                    success: function (response) {
                        if (response.success) {
                            $status.text('Saved.').css('color', '#32d74b');
                        } else {
                            $status.text('Error.').css('color', '#ff453a');
                        }
                        $btn.prop('disabled', false);
                    },
                    error: function () {
                        $status.text('Error.').css('color', '#ff453a');
                        $btn.prop('disabled', false);
                    }
                });
            });

            // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ AJAX: Send Test Email ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
            $('#xep_send_test_email_btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#xep_test_email_msg');
                var recipient = $('#xep_test_email_recipient').val();
                var nonce = $('#xep_admin_ajax_nonce').val();

                if (!recipient) {
                    alert('Please enter a recipient email.');
                    return;
                }

                $btn.prop('disabled', true).find('.btn-text').text('Sending...');
                $btn.find('i').removeClass('fa-paper-plane').addClass('fa-spinner fa-spin');
                $status.html('').css('color', '');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'xep_send_test_email',
                        nonce: nonce,
                        test_email: recipient
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<i class="fas fa-check-circle"></i> ' + response.data).css('color', '#32d74b');
                            $btn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-check-circle');
                            $btn.find('.btn-text').text('Sent!');
                            
                            // Refresh page after a brief delay so user can send to another address or see saved state
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            $status.html('<i class="fas fa-exclamation-circle"></i> ' + response.data).css('color', '#ff453a');
                            $btn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-paper-plane');
                            $btn.find('.btn-text').text('Retry Send');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        $status.html('<i class="fas fa-exclamation-circle"></i> Connection error.').css('color', '#ff453a');
                        $btn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-paper-plane');
                        $btn.prop('disabled', false);
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
 * Configure SMTP for outgoing WordPress emails
 */
add_action('phpmailer_init', 'xep_mail_smtp_init');
function xep_mail_smtp_init($phpmailer) {
    if (!$phpmailer) return;

    if (get_option('xep_smtp_enable', '0') != '1') return; // Exit if the module is disabled

    $host = get_option('xep_smtp_host');
    if (empty($host)) return; // Don't override if host is not set

    $phpmailer->isSMTP();
    $phpmailer->Host       = $host;
    $phpmailer->Port       = get_option('xep_smtp_port', '465');
    $phpmailer->SMTPAuth   = (get_option('xep_smtp_auth', '1') == '1');
    $phpmailer->SMTPSecure = get_option('xep_smtp_encryption', 'ssl');
    if ($phpmailer->SMTPSecure === 'none') {
        $phpmailer->SMTPSecure = '';
        $phpmailer->SMTPAutoTLS = false;
    }
    $phpmailer->Username   = get_option('xep_smtp_username');
    $phpmailer->Password   = get_option('xep_smtp_password');

    // Handle Certificate Verification Failure
    if (get_option('xep_smtp_insecure', '0') == '1') {
        $phpmailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
    }

    // From Header
    $from_email = get_option('xep_smtp_from_email');
    if ($from_email) {
        $phpmailer->From = $from_email;
    }
    
    $from_name = get_option('xep_smtp_from_name');
    if ($from_name) {
        $phpmailer->FromName = $from_name;
    }
}

/**
 * AJAX Handler: Send Test Email
 */
add_action('wp_ajax_xep_send_test_email', 'xep_handle_send_test_email');
function xep_handle_send_test_email() {
    check_ajax_referer('xep_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $to = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
    if (empty($to)) {
        wp_send_json_error('Please enter a valid email address.');
    }

    $subject = 'XEPMARKET SMTP Test Email';
    $message = "Hello!\n\nThis is a test email from your XEPMARKET-ALFA theme SMTP settings.\n\nIf you received this, your mail server configuration is working correctly!\n\nSent at: " . date('Y-m-d H:i:s');
    
    $sent = wp_mail($to, $subject, $message);

    if ($sent) {
        wp_send_json_success('Test email sent successfully!');
    } else {
        global $phpmailer;
        $error = '';
        if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $error = ': ' . $phpmailer->ErrorInfo;
        }
        wp_send_json_error('Failed to send test email' . $error);
    }
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
    if ($text === 'Related products' || $text === 'Related items') {
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
    if (strpos($message, 'Cart updated') !== false) {
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
    if (strpos($message, 'Cart updated') !== false) {
        return false;
    }
    return $message;
}, 10, 1);

/**
 * Filter WooCommerce Countries based on Shipping Limits set in Theme Panel
 */
add_filter('woocommerce_countries', 'xepmarket2_filter_excluded_shipping_countries', 99, 1);
add_filter('woocommerce_shipping_countries', 'xepmarket2_filter_excluded_shipping_countries', 99, 1);
function xepmarket2_filter_excluded_shipping_countries($countries) {
    if (is_admin() && !wp_doing_ajax()) return $countries; // Allow admin to see all countries

    $excluded_countries = get_option('xepmarket2_shipping_excluded_countries', array());
    if (!empty($excluded_countries) && is_array($excluded_countries)) {
        foreach ($excluded_countries as $code) {
            if (isset($countries[$code])) {
                unset($countries[$code]);
            }
        }
    }
    return $countries;
}

/**
 * Country-Based Shipping Rates defined in Theme Settings
 */
add_filter('woocommerce_package_rates', 'xepmarket2_apply_custom_shipping_rates', 100, 2);
function xepmarket2_apply_custom_shipping_rates($rates, $package) {
    if (is_admin() && !wp_doing_ajax()) {
        return $rates;
    }

    $customer_country = isset($package['destination']['country']) ? $package['destination']['country'] : '';
    if (empty($customer_country)) {
        return $rates; // Cannot determine country yet
    }

    // Check custom zones
    $zones_json = get_option('xepmarket2_shipping_zones', '[]');
    $zones = json_decode($zones_json, true);
    
    $shipping_cost = false;
    $shipping_label = 'Standard Shipping';
    
    if (is_array($zones)) {
        foreach ($zones as $zone) {
            if (!empty($zone['countries']) && is_array($zone['countries']) && in_array($customer_country, $zone['countries'])) {
                $shipping_cost = floatval($zone['cost']);
                $shipping_label = !empty($zone['name']) ? $zone['name'] : 'Zone Shipping';
                break;
            }
        }
    }
    
    // Fallback to base cost if no zone matches
    if ($shipping_cost === false) {
        $base_cost = get_option('xepmarket2_shipping_base_cost', '0');
        if ($base_cost !== '') {
            $shipping_cost = floatval($base_cost);
            $shipping_label = $shipping_cost == 0 ? __('Free Shipping', 'xepmarket2') : __('International Shipping', 'xepmarket2');
        } else {
            return $rates;
        }
    }

    // Create the custom shipping rate
    $rate_id = 'xepmarket2_custom_rate';
    $taxes = array();
    
    $new_rate = new WC_Shipping_Rate(
        $rate_id,
        $shipping_label,
        $shipping_cost,
        $taxes,
        'xepmarket2_shipping'
    );
    
    // Wipe existing rates and force our custom rule
    return array($rate_id => $new_rate);
}



/**
 * Enhanced Checkout UX - Create Account & Password Strength
 */
add_action('wp_footer', 'xepmarket2_enhanced_checkout_ux');
function xepmarket2_enhanced_checkout_ux() {
    if (!is_checkout() || is_wc_endpoint_url('order-received')) {
        return;
    }
    ?>
    <style>
        /* Make "Create an account?" more prominent */
        .woocommerce-form__label-for-checkbox.create-account {
            background: rgba(0, 242, 255, 0.05) !important;
            border: 1px solid rgba(0, 242, 255, 0.2) !important;
            border-radius: 15px !important;
            padding: 20px !important;
            margin: 20px 0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }
        
        .woocommerce-form__label-for-checkbox.create-account:hover {
            background: rgba(0, 242, 255, 0.08) !important;
            border-color: rgba(0, 242, 255, 0.4) !important;
        }
        
        .woocommerce-form__label-for-checkbox.create-account input[type="checkbox"] {
            width: 20px !important;
            height: 20px !important;
            margin: 0 !important;
            cursor: pointer !important;
            flex-shrink: 0 !important;
        }
        
        .woocommerce-form__label-for-checkbox.create-account span {
            font-size: 16px !important;
            font-weight: 600 !important;
            color: #00f2ff !important;
            flex: 1 !important;
        }
        
        /* Password strength indicator */
        .xep-password-strength-wrapper {
            margin-top: 10px;
            display: none;
        }
        
        .xep-password-strength-wrapper.active {
            display: block;
        }
        
        .xep-password-strength-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .xep-password-strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }
        
        .xep-password-strength-fill.weak {
            width: 33%;
            background: #ff453a;
        }
        
        .xep-password-strength-fill.medium {
            width: 66%;
            background: #ff9f0a;
        }
        
        .xep-password-strength-fill.strong {
            width: 100%;
            background: #30d158;
        }
        
        .xep-password-strength-text {
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .xep-password-strength-text.weak {
            color: #ff453a;
        }
        
        .xep-password-strength-text.medium {
            color: #ff9f0a;
        }
        
        .xep-password-strength-text.strong {
            color: #30d158;
        }
        
        .xep-password-requirements {
            margin-top: 10px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.6;
        }
        
        .xep-password-req {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.5);
            transition: color 0.3s ease;
        }
        
        .xep-password-req.met {
            color: #30d158;
        }
        
        .xep-password-req i {
            width: 16px;
            font-size: 12px;
        }
    </style>
    
    <script>
    (function($) {
        $(document).ready(function() {
            // Add password strength indicator
            var $passwordField = $('#account_password');
            if ($passwordField.length) {
                var strengthHTML = '<div class="xep-password-strength-wrapper">' +
                    '<div class="xep-password-strength-bar">' +
                        '<div class="xep-password-strength-fill"></div>' +
                    '</div>' +
                    '<div class="xep-password-strength-text"></div>' +
                    '<div class="xep-password-requirements">' +
                        '<div class="xep-password-req" data-req="length"><i class="fas fa-circle"></i> At least 8 characters</div>' +
                        '<div class="xep-password-req" data-req="uppercase"><i class="fas fa-circle"></i> At least 1 uppercase letter</div>' +
                        '<div class="xep-password-req" data-req="lowercase"><i class="fas fa-circle"></i> At least 1 lowercase letter</div>' +
                        '<div class="xep-password-req" data-req="number"><i class="fas fa-circle"></i> At least 1 number</div>' +
                    '</div>' +
                '</div>';
                
                $passwordField.after(strengthHTML);
                
                var $wrapper = $passwordField.next('.xep-password-strength-wrapper');
                var $fill = $wrapper.find('.xep-password-strength-fill');
                var $text = $wrapper.find('.xep-password-strength-text');
                
                $passwordField.on('input', function() {
                    var password = $(this).val();
                    
                    if (password.length === 0) {
                        $wrapper.removeClass('active');
                        return;
                    }
                    
                    $wrapper.addClass('active');
                    
                    // Check requirements
                    var hasLength = password.length >= 8;
                    var hasUppercase = /[A-Z]/.test(password);
                    var hasLowercase = /[a-z]/.test(password);
                    var hasNumber = /[0-9]/.test(password);
                    
                    // Update requirement indicators
                    $wrapper.find('[data-req="length"]').toggleClass('met', hasLength);
                    $wrapper.find('[data-req="uppercase"]').toggleClass('met', hasUppercase);
                    $wrapper.find('[data-req="lowercase"]').toggleClass('met', hasLowercase);
                    $wrapper.find('[data-req="number"]').toggleClass('met', hasNumber);
                    
                    // Calculate strength
                    var metCount = [hasLength, hasUppercase, hasLowercase, hasNumber].filter(Boolean).length;
                    
                    $fill.removeClass('weak medium strong');
                    $text.removeClass('weak medium strong');
                    
                    if (metCount <= 2) {
                        $fill.addClass('weak');
                        $text.addClass('weak').html('<i class="fas fa-exclamation-triangle"></i> Weak password');
                    } else if (metCount === 3) {
                        $fill.addClass('medium');
                        $text.addClass('medium').html('<i class="fas fa-check-circle"></i> Medium password');
                    } else {
                        $fill.addClass('strong');
                        $text.addClass('strong').html('<i class="fas fa-shield-alt"></i> Strong password');
                    }
                });
            }
        });
    })(jQuery);
    </script>
    <?php
}
