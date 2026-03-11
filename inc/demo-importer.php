<?php
/**
 * XEPMARKET-ALFA Demo Data Importer
 * Handles the automatic setup of pages, menus, and woocommerce settings.
 * Last snapshot: 2026-02-21
 */

if (!defined('ABSPATH'))
    exit;

/**
 * Main function to import demo data
 * @param bool $apply_theme_defaults If false, only create pages/menus; theme options stay as-is (current state).
 */
function xepmarket2_setup_demo_data($apply_theme_defaults = true)
{
    // ════════════════════════════════════════════════════
    // 1. CREATE ESSENTIAL PAGES
    // ════════════════════════════════════════════════════
    $pages = array(
        'home' => array(
            'title' => 'Home',
            'content' => '',
            'template' => 'front-page.php',
            'option' => 'page_on_front'
        ),
        'swap' => array(
            'title' => 'Swap',
            'content' => '<div style="display: flex; justify-content: center; width: 100%;"><iframe id="stealthex-widget" style="border: none; border-radius: 10px; overflow: hidden; width: 100%; max-width: 960px; height: 330px; box-shadow: 0px 0px 32px 0px rgba(0, 0, 0, 0.06);" src="https://stealthex.io/widget/442292eb-3cbc-4276-91fc-cbcfe9a45d54"></iframe></div>',
            'template' => '',
        ),
        'shop' => array(
            'title' => 'Shop',
            'content' => '',
            'template' => '',
        ),
        'cart' => array(
            'title' => 'Cart',
            'content' => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->',
            'template' => '',
        ),
        'checkout' => array(
            'title' => 'Checkout',
            'content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
            'template' => '',
        ),
        'my-account' => array(
            'title' => 'My Account',
            'content' => '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->',
            'template' => '',
        ),
        'privacy-policy' => array(
            'title' => 'Privacy Policy',
            'content' => xepmarket2_get_privacy_policy_content(),
            'template' => '',
            'option' => 'wp_page_for_privacy_policy'
        ),
    );

    foreach ($pages as $slug => $data) {
        $exists = get_page_by_path($slug);

        if (!$exists) {
            $page_id = wp_insert_post(array(
                'post_title' => $data['title'],
                'post_name' => $slug,
                'post_content' => $data['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
            ));

            if (!empty($data['template'])) {
                update_post_meta($page_id, '_wp_page_template', $data['template']);
            }

            if (isset($data['option']) && $data['option'] === 'page_on_front') {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $page_id);
            }

            if (isset($data['option']) && $data['option'] === 'wp_page_for_privacy_policy') {
                update_option('wp_page_for_privacy_policy', $page_id);
            }
        } else {
            // Always update content if demo data is provided (Force refresh)
            if (!empty($data['content'])) {
                wp_update_post(array(
                    'ID' => $exists->ID,
                    'post_content' => $data['content'],
                ));
            }

            // Always set options even if page exists
            if (isset($data['option']) && $data['option'] === 'page_on_front') {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $exists->ID);
            }
            if (isset($data['option']) && $data['option'] === 'wp_page_for_privacy_policy') {
                update_option('wp_page_for_privacy_policy', $exists->ID);
            }
        }
    }

    // ════════════════════════════════════════════════════
    // 2. SETUP WOOCOMMERCE PAGES
    // ════════════════════════════════════════════════════
    if (class_exists('WooCommerce')) {
        // Assign WooCommerce page IDs
        $wc_page_map = array(
            'shop' => 'woocommerce_shop_page_id',
            'cart' => 'woocommerce_cart_page_id',
            'checkout' => 'woocommerce_checkout_page_id',
            'my-account' => 'woocommerce_myaccount_page_id',
            'privacy-policy' => 'woocommerce_terms_page_id',
        );

        foreach ($wc_page_map as $slug => $option_name) {
            $page = get_page_by_path($slug);
            if ($page) {
                update_option($option_name, $page->ID);
            }
        }
    }

    // ════════════════════════════════════════════════════
    // 3. SETUP PRIMARY MENU
    // ════════════════════════════════════════════════════
    $menu_name = 'Main Menu';
    $menu_exists = wp_get_nav_menu_object($menu_name);

    if (!$menu_exists) {
        $menu_id = wp_create_nav_menu($menu_name);

        $items = array(
            'Home' => home_url('/'),
            'Shop' => home_url('/shop/'),
            'Swap' => home_url('/swap/'),
        );

        foreach ($items as $title => $url) {
            wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title' => $title,
                'menu-item-url' => $url,
                'menu-item-status' => 'publish',
            ));
        }

        // Set location
        $locations = get_theme_mod('nav_menu_locations');
        $locations['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }

    $primary_menu_id = $menu_exists ? $menu_exists->term_id : (isset($menu_id) ? $menu_id : 0);

    // ════════════════════════════════════════════════════
    // 4. DEFAULT THEME OPTIONS (skip if preserving current state)
    // ════════════════════════════════════════════════════
    if (!$apply_theme_defaults) {
        return true;
    }

    if ($primary_menu_id > 0) {
        update_option('xepmarket2_menu_web', $primary_menu_id);
        update_option('xepmarket2_menu_mobile', $primary_menu_id);
    }

    $template_uri = get_template_directory_uri();

    $defaults = array(
        'xep_tg_bot_chat_id' => '7578957997',
        'xep_tg_bot_enabled' => 'yes',
        'xep_tg_bot_msg_new_order' => '🛒 <b>NEW ORDER RECEIVED!</b>

<b>Order ID:</b> #{order_id}
<b>Customer:</b> {customer_name}
<b>Total:</b> {total}
<b>Status:</b> {status}

<b>Items:</b>
{items}',
        'xep_tg_bot_msg_status_changed' => '🔄 <b>ORDER STATUS UPDATED</b>

<b>Order ID:</b> #{order_id}
<b>New Status:</b> {status}
<b>Customer:</b> {customer_name}
<b>Total:</b> {total}',
        'xep_tg_bot_token' => '8144363788:AAGGQnV8hj7pZ7wT8F0lmE3FsGy1COmMI0E',
        'xepmarket2_admin_banner_img' => '',
        'xepmarket2_banner_bg' => '#00f2ff',
        'xepmarket2_banner_discount' => '🚀 Limited Time Offer: Up to 50% OFF! 🚀',
        'xepmarket2_banner_main' => 'Welcome to the future of retail! Secure your Crypto Gear today. Pay exclusively with XEP.',
        'xepmarket2_banner_text_color' => '#000000',
        'xepmarket2_banner_visibility' => 'web',
        'xepmarket2_chk_address_1' => '1',
        'xepmarket2_chk_address_2' => '',
        'xepmarket2_chk_city' => '1',
        'xepmarket2_chk_company' => '',
        'xepmarket2_chk_country' => '1',
        'xepmarket2_chk_custom_fields' => '[]',
        'xepmarket2_chk_email' => '1',
        'xepmarket2_chk_first_name' => '1',
        'xepmarket2_chk_last_name' => '1',
        'xepmarket2_chk_name_address_1' => 'Address Line 1',
        'xepmarket2_chk_name_address_2' => 'Address Line 2',
        'xepmarket2_chk_name_city' => 'City',
        'xepmarket2_chk_name_company' => 'Company',
        'xepmarket2_chk_name_country' => 'Country',
        'xepmarket2_chk_name_email' => 'Email Address',
        'xepmarket2_chk_name_first_name' => 'First Name',
        'xepmarket2_chk_name_last_name' => 'Last Name',
        'xepmarket2_chk_name_phone' => 'Phone Number',
        'xepmarket2_chk_name_postcode' => 'Postcode / ZIP',
        'xepmarket2_chk_name_state' => 'State / County',
        'xepmarket2_chk_name_telegram' => 'Telegram Username',
        'xepmarket2_chk_order_address_1' => '5',
        'xepmarket2_chk_order_address_2' => '72',
        'xepmarket2_chk_order_city' => '4',
        'xepmarket2_chk_order_company' => '60',
        'xepmarket2_chk_order_country' => '3',
        'xepmarket2_chk_order_email' => '2',
        'xepmarket2_chk_order_first_name' => '1',
        'xepmarket2_chk_order_last_name' => '1',
        'xepmarket2_chk_order_phone' => '2',
        'xepmarket2_chk_order_postcode' => '4',
        'xepmarket2_chk_order_state' => '3',
        'xepmarket2_chk_order_telegram' => '60',
        'xepmarket2_chk_phone' => '1',
        'xepmarket2_chk_postcode' => '1',
        'xepmarket2_chk_req_address_1' => '1',
        'xepmarket2_chk_req_address_2' => '1',
        'xepmarket2_chk_req_city' => '1',
        'xepmarket2_chk_req_company' => '1',
        'xepmarket2_chk_req_country' => '1',
        'xepmarket2_chk_req_email' => '1',
        'xepmarket2_chk_req_first_name' => '1',
        'xepmarket2_chk_req_last_name' => '1',
        'xepmarket2_chk_req_phone' => '1',
        'xepmarket2_chk_req_postcode' => '1',
        'xepmarket2_chk_req_state' => '1',
        'xepmarket2_chk_req_telegram' => '',
        'xepmarket2_chk_state' => '1',
        'xepmarket2_chk_telegram' => '',
        'xepmarket2_chk_width_address_1' => 'full',
        'xepmarket2_chk_width_address_2' => 'half',
        'xepmarket2_chk_width_city' => 'half',
        'xepmarket2_chk_width_company' => 'full',
        'xepmarket2_chk_width_country' => 'half',
        'xepmarket2_chk_width_email' => 'half',
        'xepmarket2_chk_width_first_name' => 'half',
        'xepmarket2_chk_width_last_name' => 'half',
        'xepmarket2_chk_width_phone' => 'half',
        'xepmarket2_chk_width_postcode' => 'half',
        'xepmarket2_chk_width_state' => 'half',
        'xepmarket2_chk_width_telegram' => 'half',
        'xepmarket2_color_bg' => '#0f0a0a',
        'xepmarket2_color_preset' => '',
        'xepmarket2_color_primary' => '#ff3333',
        'xepmarket2_color_secondary' => '#ffaa00',
        'xepmarket2_color_text' => '#fff0f0',
        'xepmarket2_color_text_muted' => '#b8a0a0',
        'xepmarket2_favicon' => '',
        'xepmarket2_featured_columns' => '4',
        'xepmarket2_featured_limit' => '45',
        'xepmarket2_featured_subtitle' => 'Must-have items for every Web3 enthusiast',
        'xepmarket2_featured_title' => 'Trending Gear',
        'xepmarket2_flash_deals_columns' => '4',
        'xepmarket2_flash_deals_enable' => '1',
        'xepmarket2_flash_deals_limit' => '12',
        'xepmarket2_flash_deals_subtitle' => 'Exclusive discounts on premium gear.',
        'xepmarket2_flash_deals_title' => 'Flash <span class="logo-accent">Deals</span>',
        'xepmarket2_footer_desc' => 'Your premium destination for Web3 lifestyle gear. From hardware security to exclusive crypto apparel, we bring the blockchain to your doorstep.',
        'xepmarket2_footer_logo_img' => 'https://xepmarket.local/wp-content/uploads/2026/02/cropped-xepmarket_logo.png',
        'xepmarket2_footer_logo_text_1' => 'XEP',
        'xepmarket2_footer_logo_text_2' => 'MARKET',
        'xepmarket2_footer_logo_type' => 'text_image',
        'xepmarket2_header_logo_img' => 'https://xepmarket.local/wp-content/uploads/2026/02/cropped-xepmarket_logo.png',
        'xepmarket2_header_logo_text_1' => 'XEP',
        'xepmarket2_header_logo_text_2' => 'MARKET',
        'xepmarket2_header_logo_type' => 'text',
        'xepmarket2_hero_btn1_link' => '/shop',
        'xepmarket2_hero_btn1_text' => 'Shop Crypto Gear',
        'xepmarket2_hero_btn2_link' => 'https://t.me/xepmarket_official',
        'xepmarket2_hero_btn2_text' => 'Join Community',
        'xepmarket2_hero_subtitle' => 'Premium hardware wallets, exclusive crypto apparel, and unique digital collectibles. Secure your assets and show your passion. Pay exclusively with XEP for secure and instant delivery.',
        'xepmarket2_hero_title' => 'Elevate Your <br><span class="logo-accent">Crypto Lifestyle</span>',
        'xepmarket2_highlight_desc_1' => 'On all orders across the store',
        'xepmarket2_highlight_desc_2' => 'Shipping to every corner of the world',
        'xepmarket2_highlight_desc_3' => 'Guaranteed customer happiness',
        'xepmarket2_highlight_desc_4' => 'Massive sale on innovative tech',
        'xepmarket2_highlight_icon_1' => 'dashicons-location-alt',
        'xepmarket2_highlight_icon_2' => 'dashicons-admin-site-alt3',
        'xepmarket2_highlight_icon_3' => 'dashicons-thumbs-up',
        'xepmarket2_highlight_icon_4' => 'dashicons-tag',
        'xepmarket2_highlight_title_1' => 'FREE SHIPPING',
        'xepmarket2_highlight_title_2' => 'WORLDWIDE DELIVERY',
        'xepmarket2_highlight_title_3' => '100% SATISFACTION',
        'xepmarket2_highlight_title_4' => 'UP TO 90% OFF',
        'xepmarket2_menu_mobile' => '',
        'xepmarket2_menu_web' => '',
        'xepmarket2_mobile_nav_custom_items' => array(),
        'xepmarket2_mobile_nav_icon_account' => '',
        'xepmarket2_mobile_nav_icon_cart' => '',
        'xepmarket2_mobile_nav_icon_home' => '',
        'xepmarket2_mobile_nav_icon_shop' => '',
        'xepmarket2_mobile_nav_order' => array('home', 'shop', 'custom_0', 'cart', 'account'),
        'xepmarket2_mobile_nav_show_account' => '1',
        'xepmarket2_mobile_nav_show_cart' => '1',
        'xepmarket2_mobile_nav_show_home' => '1',
        'xepmarket2_mobile_nav_show_shop' => '1',
        'xepmarket2_mod_breadcrumb_modern' => '1',
        'xepmarket2_mod_custom_checkout' => '1',
        'xepmarket2_mod_omnixep_restrict' => '1',
        'xepmarket2_mod_sale_badges' => '1',
        'xepmarket2_seo_about_text' => 'XEPMarket is the world\'s first blockchain e-commerce store, accepting XEP and MMX (MemexAI) tokens. Open-source theme and payment module built by the MemexAI team to showcase the real potential of blockchain technology.
XepMarket is your premium destination for Web3 gear, crypto apparel, and hardware security. Official store for the Electra Protocol (XEP) ecosystem.',
        'xepmarket2_seo_ai_business_name' => 'XepMarket',
        'xepmarket2_seo_ai_crawler_allow' => 'allow',
        'xepmarket2_seo_ai_logo_url' => 'https://www.xepmarket.com/wp-content/uploads/2026/02/xepmarket_logo.png',
        'xepmarket2_seo_ai_topics' => 'Blockchain E-Commerce, Cryptocurrency Payments, Decentralized Shopping, XEP Token, MMX Token, MemexAI, Open Source E-Commerce, Web3 Retail',
        'xepmarket2_seo_analytics_id' => '',
        'xepmarket2_seo_description' => 'XEPMarket is the world\'s first blockchain e-commerce store, accepting XEP and MMX (MemexAI) tokens. Open-source theme and payment module built by the MemexAI team to showcase the real potential of blockchain technology.
XepMarket is your premium destination for Web3 gear, crypto apparel, and hardware security. Official store for the Electra Protocol (XEP) ecosystem.',
        'xepmarket2_seo_founder_name' => 'MemexAI',
        'xepmarket2_seo_founder_url' => 'https://memexai.com',
        'xepmarket2_seo_google_verify' => '',
        'xepmarket2_seo_keywords' => 'XEP, Electra Protocol, Web3 Gear, Crypto Apparel, Hardware Wallet, XepMarket, Blockchain Lifestyle',
        'xepmarket2_seo_og_image' => 'https://www.xepmarket.com/wp-content/uploads/2026/02/xepmarket_logo.png',
        'xepmarket2_seo_payment_methods' => 'XEP Token (Electra Protocol), MMX Token (MemexAI)',
        'xepmarket2_seo_slogan' => 'Be your own boss — receive payments directly without intermediaries',
        'xepmarket2_seo_title_suffix' => '| XepMarket Premium Web3 Gear',
        'xepmarket2_shipping_excluded_countries' => array('TR'),
        'xepmarket2_slider_btn_link_1' => '/shop',
        'xepmarket2_slider_btn_link_2' => '/shop',
        'xepmarket2_slider_btn_link_3' => '/shop',
        'xepmarket2_slider_btn_text_1' => 'Learn More',
        'xepmarket2_slider_btn_text_2' => 'Discover More',
        'xepmarket2_slider_btn_text_3' => 'Claim Discount',
        'xepmarket2_slider_desc_1' => 'We deliver the future of tech to your doorstep, anywhere in the world. Zero shipping costs, 100% secure.',
        'xepmarket2_slider_desc_2' => 'Explore the latest in cutting-edge technology. From smart devices to premium gadgets, we bring you the future.',
        'xepmarket2_slider_desc_3' => 'Limited time offer on select premium items. Enhance your lifestyle with massive discounts.',
        'xepmarket2_slider_enable' => '1',
        'xepmarket2_slider_img_1' => 'https://www.xepmarket.com/wp-content/themes/XEPMARKET-ALFA/assets/images/slide2.png?v=1.5',
        'xepmarket2_slider_img_2' => 'https://www.xepmarket.com/wp-content/themes/XEPMARKET-ALFA/assets/images/slide_tech.png?v=1.6',
        'xepmarket2_slider_img_3' => 'https://www.xepmarket.com/wp-content/themes/XEPMARKET-ALFA/assets/images/slide3.png?v=1.5',
        'xepmarket2_slider_title_1' => 'Free <span class="logo-accent">Worldwide</span> Shipping',
        'xepmarket2_slider_title_2' => 'Innovative <span class="logo-accent">Tech Products</span>',
        'xepmarket2_slider_title_3' => 'Flash Sale: <span class="logo-accent">80% OFF</span>',
        'xepmarket2_social_discord' => '#',
        'xepmarket2_social_instagram' => '#',
        'xepmarket2_social_pinterest' => '#',
        'xepmarket2_social_telegram' => 'https://t.me/xepmarket_official',
        'xepmarket2_social_tiktok' => '#',
        'xepmarket2_social_twitter' => '#',
        'xepmarket2_social_youtube' => '#',
        'xepmarket2_support_desc' => 'Need help with your order or crypto payment? Our decentralized support team is ready to help you navigate the future of commerce.',
        'xepmarket2_support_email' => 'support@xepmarket.com',
        'xepmarket2_support_telegram' => '@xepmarket',
        'xepmarket2_support_title' => 'Web3 Native <span class="logo-accent">Support</span>',
        'xepmarket2_token_name_1' => 'XEP',
        'xepmarket2_token_name_2' => 'MMX',
        'xepmarket2_token_name_3' => '',
        'xepmarket2_token_name_4' => '',
        'xepmarket2_token_status_1' => 'live',
        'xepmarket2_token_status_2' => 'soon',
        'xepmarket2_token_status_3' => 'hidden',
        'xepmarket2_token_status_4' => 'hidden',
    );

    foreach ($defaults as $key => $val) {
        update_option($key, $val);
    }

    // ════════════════════════════════════════════════════
    // 5. WORDPRESS CORE SETTINGS
    // ════════════════════════════════════════════════════
    update_option('blogname', 'XepMarket');
    update_option('blogdescription', 'Premium Web3 Gear & Crypto Lifestyle');
    update_option('permalink_structure', '/%postname%/');

    // Mark defaults as applied
    update_option('xepmarket2_defaults_set_v2_0', 'yes');

    return true;
}

/**
 * Factory Reset Function — restores to last saved "current state" (no wipe to demo).
 */
function xepmarket2_factory_reset()
{
    return xepmarket2_restore_saved_state();
}

/**
 * Save current theme state (options + theme mods) for "Reset to current state".
 */
function xepmarket2_save_current_state()
{
    global $wpdb;
    $options = $wpdb->get_results($wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
        'xepmarket2_%'
    ), ARRAY_A);
    $state_options = array();
    foreach ($options as $row) {
        $state_options[$row['option_name']] = maybe_unserialize($row['option_value']);
    }
    $theme_mods = get_theme_mods();
    if (false === $theme_mods) {
        $theme_mods = array();
    }
    $state = array(
        'options' => $state_options,
        'theme_mods' => $theme_mods,
        'saved_at' => current_time('mysql'),
    );
    update_option('xepmarket2_saved_state', $state, false);
    return true;
}

/**
 * Restore theme to last saved state. Returns false if no saved state.
 */
function xepmarket2_restore_saved_state()
{
    global $wpdb;
    $state = get_option('xepmarket2_saved_state');
    if (empty($state) || !is_array($state) || empty($state['options'])) {
        return false;
    }
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", 'xepmarket2_%'));
    foreach ($state['options'] as $name => $value) {
        update_option($name, $value, false);
    }
    if (!empty($state['theme_mods']) && is_array($state['theme_mods'])) {
        remove_theme_mods();
        foreach ($state['theme_mods'] as $key => $val) {
            set_theme_mod($key, $val);
        }
    }
    return true;
}

/**
 * Ajax Handler for Factory Reset
 */
function xepmarket2_ajax_factory_reset()
{
    check_ajax_referer('xep_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $result = xepmarket2_factory_reset();

    if ($result) {
        wp_send_json_success(__('Theme restored to saved state.', 'xepmarket2'));
    } else {
        wp_send_json_error(__('No saved state found. Click "Save current state" first.', 'xepmarket2'));
    }
}
add_action('wp_ajax_xep_factory_reset', 'xepmarket2_ajax_factory_reset');

/**
 * Ajax: Save current theme state for later restore
 */
function xepmarket2_ajax_save_current_state()
{
    check_ajax_referer('xep_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    xepmarket2_save_current_state();
    wp_send_json_success(__('Current state saved. Use "Reset" to restore to this state.', 'xepmarket2'));
}
add_action('wp_ajax_xep_save_current_state', 'xepmarket2_ajax_save_current_state');

/**
 * Ajax Handler for Demo Import
 * On first install (no Main Menu / no home page): full demo setup like live preview.
 * On later runs: only add missing pages and menu, do not change theme settings.
 */
function xepmarket2_ajax_import_demo()
{
    check_ajax_referer('xep_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $is_first_install = false;
    $menu_exists = wp_get_nav_menu_object('Main Menu');
    $home_page = get_page_by_path('home');
    if (!$menu_exists && !$home_page) {
        $is_first_install = true;
    }

    $apply_theme_defaults = $is_first_install;
    $result = xepmarket2_setup_demo_data($apply_theme_defaults);

    if ($result) {
        if ($is_first_install) {
            wp_send_json_success(__('Full demo setup complete. Site configured like the live preview.', 'xepmarket2'));
        } else {
            wp_send_json_success(__('Pages and menu updated. Theme settings unchanged.', 'xepmarket2'));
        }
    } else {
        wp_send_json_error('Import failed.');
    }
}
add_action('wp_ajax_xep_import_demo', 'xepmarket2_ajax_import_demo');

/**
 * Returns the default Privacy Policy content
 */
function xepmarket2_get_privacy_policy_content()
{
    return '<!-- wp:heading -->
<p data-start="206" data-end="317"><strong data-start="206" data-end="223">Last Updated:</strong> February 15, 2026<br data-start="241" data-end="244" /><strong data-start="244" data-end="256">Website:</strong> <a class="decorated-link" href="https://xepmarket.com" target="_new" rel="noopener" data-start="257" data-end="278">https://xepmarket.com</a><br data-start="278" data-end="281" /><strong data-start="281" data-end="293">Contact:</strong> <a class="decorated-link cursor-pointer" rel="noopener" data-start="294" data-end="315">support@xepmarket.com</a></p>
<hr data-start="319" data-end="322" />
<h2 data-start="324" data-end="340">1. Who We Are</h2>
<p data-start="342" data-end="421">xepmarket.com is an e-commerce platform offering digital and physical products.</p>
<p data-start="423" data-end="533">All payments on this website are processed exclusively through blockchain-based cryptocurrency infrastructure.</p>
<p data-start="535" data-end="594">For privacy-related inquiries:<br data-start="565" data-end="568" />📧 <a class="decorated-link cursor-pointer" rel="noopener" data-start="571" data-end="592">support@xepmarket.com</a></p>
<hr data-start="596" data-end="599" />
<h2 data-start="601" data-end="659">2. Payment Infrastructure and Accepted Cryptocurrencies</h2>
<p data-start="661" data-end="759">Payments on xepmarket.com are processed exclusively via <strong data-start="717" data-end="758"><span class="hover:entity-accent entity-underline inline cursor-pointer align-baseline"><span class="whitespace-normal">ElectraPay</span></span></strong>.</p>
<p data-start="761" data-end="807">We accept only the following cryptocurrencies:</p>
<ul data-start="809" data-end="912">
<li data-start="809" data-end="860">
<p data-start="811" data-end="860"><strong data-start="811" data-end="858"><span class="hover:entity-accent entity-underline inline cursor-pointer align-baseline"><span class="whitespace-normal">ElectraProtocol</span></span> (XEP)</strong></p>
</li>
<li data-start="861" data-end="912">
<p data-start="863" data-end="912"><strong data-start="863" data-end="910"><span class="hover:entity-accent entity-underline inline cursor-pointer align-baseline"><span class="whitespace-normal">MEMEXAI</span></span> (MMX)</strong></p>
</li>
</ul>
<p data-start="914" data-end="942">Payments must be made using:</p>
<ul data-start="944" data-end="1033">
<li data-start="944" data-end="989">
<p data-start="946" data-end="989"><strong data-start="946" data-end="987"><span class="hover:entity-accent entity-underline inline cursor-pointer align-baseline"><span class="whitespace-normal">OMNIXEP WEB WALLET</span></span></strong></p>
</li>
<li data-start="990" data-end="1033">
<p data-start="992" data-end="1033"><strong data-start="992" data-end="1033"><span class="hover:entity-accent entity-underline inline cursor-pointer align-baseline"><span class="whitespace-normal">OMNIXEP MOBILE WALLET</span></span></strong></p>
</li>
</ul>
<p data-start="1035" data-end="1124">⚠️ We do not accept credit cards, debit cards, bank transfers, or fiat currency payments.</p>
<p data-start="1126" data-end="1182">All blockchain transactions are public and irreversible.</p>
<hr data-start="1184" data-end="1187" />
<h2 data-start="1189" data-end="1232">3. What Personal Data We Collect and Why</h2>
<h3 data-start="1234" data-end="1267">3.1 Identity and Contact Data</h3>
<ul data-start="1268" data-end="1339">
<li data-start="1268" data-end="1281">
<p data-start="1270" data-end="1281">Full name</p>
</li>
<li data-start="1282" data-end="1299">
<p data-start="1284" data-end="1299">Email address</p>
</li>
<li data-start="1300" data-end="1339">
<p data-start="1302" data-end="1339">Shipping address (for physical goods)</p>
</li>
</ul>
<p data-start="1341" data-end="1355"><strong data-start="1341" data-end="1353">Purpose:</strong></p>
<ul data-start="1356" data-end="1416">
<li data-start="1356" data-end="1376">
<p data-start="1358" data-end="1376">Order processing</p>
</li>
<li data-start="1377" data-end="1397">
<p data-start="1379" data-end="1397">Customer support</p>
</li>
<li data-start="1398" data-end="1416">
<p data-start="1400" data-end="1416">Legal compliance</p>
</li>
</ul>
<p data-start="1418" data-end="1436"><strong data-start="1418" data-end="1434">Legal Basis:</strong></p>
<ul data-start="1437" data-end="1521">
<li data-start="1437" data-end="1466">
<p data-start="1439" data-end="1466">Performance of a contract</p>
</li>
<li data-start="1467" data-end="1487">
<p data-start="1469" data-end="1487">Legal obligation</p>
</li>
<li data-start="1488" data-end="1521">
<p data-start="1490" data-end="1521">User consent (where applicable)</p>
</li>
</ul>
<hr data-start="1523" data-end="1526" />
<h3 data-start="1528" data-end="1563">3.2 Blockchain Transaction Data</h3>
<ul data-start="1564" data-end="1662">
<li data-start="1564" data-end="1591">
<p data-start="1566" data-end="1591">Transaction hash (TXID)</p>
</li>
<li data-start="1592" data-end="1617">
<p data-start="1594" data-end="1617">Sender wallet address</p>
</li>
<li data-start="1618" data-end="1636">
<p data-start="1620" data-end="1636">Payment amount</p>
</li>
<li data-start="1637" data-end="1662">
<p data-start="1639" data-end="1662">Transaction timestamp</p>
</li>
</ul>
<p data-start="1664" data-end="1678"><strong data-start="1664" data-end="1676">Purpose:</strong></p>
<ul data-start="1679" data-end="1750">
<li data-start="1679" data-end="1703">
<p data-start="1681" data-end="1703">Payment verification</p>
</li>
<li data-start="1704" data-end="1724">
<p data-start="1706" data-end="1724">Fraud prevention</p>
</li>
<li data-start="1725" data-end="1750">
<p data-start="1727" data-end="1750">Accounting compliance</p>
</li>
</ul>
<p data-start="1752" data-end="1832">Blockchain data is publicly available by design and cannot be altered or erased.</p>
<hr data-start="1834" data-end="1837" />
<h3 data-start="1839" data-end="1861">3.3 Technical Data</h3>
<ul data-start="1862" data-end="1932">
<li data-start="1862" data-end="1876">
<p data-start="1864" data-end="1876">IP address</p>
</li>
<li data-start="1877" data-end="1893">
<p data-start="1879" data-end="1893">Browser type</p>
</li>
<li data-start="1894" data-end="1916">
<p data-start="1896" data-end="1916">Device information</p>
</li>
<li data-start="1917" data-end="1932">
<p data-start="1919" data-end="1932">Cookie data</p>
</li>
</ul>
<p data-start="1934" data-end="1948"><strong data-start="1934" data-end="1946">Purpose:</strong></p>
<ul data-start="1949" data-end="2019">
<li data-start="1949" data-end="1972">
<p data-start="1951" data-end="1972">Security monitoring</p>
</li>
<li data-start="1973" data-end="1993">
<p data-start="1975" data-end="1993">Preventing abuse</p>
</li>
<li data-start="1994" data-end="2019">
<p data-start="1996" data-end="2019">Performance analytics</p>
</li>
</ul>
<hr data-start="2021" data-end="2024" />
<h2 data-start="2026" data-end="2039">4. Cookies</h2>
<p data-start="2041" data-end="2052">We may use:</p>
<ul data-start="2054" data-end="2142">
<li data-start="2054" data-end="2073">
<p data-start="2056" data-end="2073">Session cookies</p>
</li>
<li data-start="2074" data-end="2099">
<p data-start="2076" data-end="2099">Shopping cart cookies</p>
</li>
<li data-start="2100" data-end="2120">
<p data-start="2102" data-end="2120">Security cookies</p>
</li>
<li data-start="2121" data-end="2142">
<p data-start="2123" data-end="2142">Analytics cookies</p>
</li>
</ul>
<p data-start="2144" data-end="2196">Users can disable cookies in their browser settings.</p>
<hr data-start="2198" data-end="2201" />
<h2 data-start="2203" data-end="2236">5. Who We Share Your Data With</h2>
<p data-start="2238" data-end="2271">We may share necessary data with:</p>
<ul data-start="2273" data-end="2414">
<li data-start="2273" data-end="2345">
<p data-start="2275" data-end="2345"><strong data-start="2275" data-end="2316"><span class="hover:entity-accent entity-underline inline cursor-pointer align-baseline"><span class="whitespace-normal">ElectraPay</span></span></strong> (for payment verification)</p>
</li>
<li data-start="2346" data-end="2367">
<p data-start="2348" data-end="2367">Hosting providers</p>
</li>
<li data-start="2368" data-end="2414">
<p data-start="2370" data-end="2414">Shipping providers (for physical deliveries)</p>
</li>
</ul>
<p data-start="2416" data-end="2477">We do not sell or share personal data for marketing purposes.</p>
<hr data-start="2479" data-end="2482" />
<h2 data-start="2484" data-end="2518">6. How Long We Retain Your Data</h2>
<ul data-start="2520" data-end="2712">
<li data-start="2520" data-end="2597">
<p data-start="2522" data-end="2597">Order and payment records: up to 10 years (legal accounting requirements)</p>
</li>
<li data-start="2598" data-end="2636">
<p data-start="2600" data-end="2636">Contact form submissions: 6 months</p>
</li>
<li data-start="2637" data-end="2665">
<p data-start="2639" data-end="2665">Security logs: 12 months</p>
</li>
<li data-start="2666" data-end="2712">
<p data-start="2668" data-end="2712">Account data: until the account is deleted</p>
</li>
</ul>
<hr data-start="2714" data-end="2717" />
<h2 data-start="2719" data-end="2736">7. Your Rights</h2>
<p data-start="2738" data-end="2796">Depending on your jurisdiction, you may have the right to:</p>
<ul data-start="2798" data-end="2925">
<li data-start="2798" data-end="2827">
<p data-start="2800" data-end="2827">Access your personal data</p>
</li>
<li data-start="2828" data-end="2850">
<p data-start="2830" data-end="2850">Request correction</p>
</li>
<li data-start="2851" data-end="2871">
<p data-start="2853" data-end="2871">Request deletion</p>
</li>
<li data-start="2872" data-end="2896">
<p data-start="2874" data-end="2896">Object to processing</p>
</li>
<li data-start="2897" data-end="2925">
<p data-start="2899" data-end="2925">Request data portability</p>
</li>
</ul>
<p data-start="2927" data-end="2980">To exercise your rights:<br data-start="2951" data-end="2954" />📧 <a class="decorated-link cursor-pointer" rel="noopener" data-start="2957" data-end="2978">support@xepmarket.com</a></p>
<hr data-start="2982" data-end="2985" />
<h2 data-start="2987" data-end="3021">8. International Data Transfers</h2>
<p data-start="3023" data-end="3214">Your data may be processed outside the European Union depending on hosting infrastructure.<br data-start="3113" data-end="3116" />We ensure appropriate safeguards are implemented to maintain GDPR-equivalent protection standards.</p>
<hr data-start="3216" data-end="3219" />
<h2 data-start="3221" data-end="3251">9. How We Protect Your Data</h2>
<p data-start="3253" data-end="3266">We implement:</p>
<ul data-start="3268" data-end="3415">
<li data-start="3268" data-end="3290">
<p data-start="3270" data-end="3290">SSL/TLS encryption</p>
</li>
<li data-start="3291" data-end="3336">
<p data-start="3293" data-end="3336">Secure blockchain verification mechanisms</p>
</li>
<li data-start="3337" data-end="3373">
<p data-start="3339" data-end="3373">Server-level firewall protection</p>
</li>
<li data-start="3374" data-end="3415">
<p data-start="3376" data-end="3415">Access control and monitoring systems</p>
</li>
</ul>
<hr data-start="3417" data-end="3420" />
<h2 data-start="3422" data-end="3451">10. Data Breach Procedures</h2>
<p data-start="3453" data-end="3483">In the event of a data breach:</p>
<ul data-start="3485" data-end="3657">
<li data-start="3485" data-end="3541">
<p data-start="3487" data-end="3541">An internal security investigation will be initiated</p>
</li>
<li data-start="3542" data-end="3589">
<p data-start="3544" data-end="3589">Affected users will be notified if required</p>
</li>
<li data-start="3590" data-end="3657">
<p data-start="3592" data-end="3657">Regulatory authorities will be informed where legally necessary</p>
</li>
</ul>
<hr data-start="3659" data-end="3662" />
<h2 data-start="3664" data-end="3696">11. Automated Decision-Making</h2>
<p data-start="3698" data-end="3809">xepmarket.com does not perform automated credit scoring, profiling, or decision-making without human oversight.</p>';
}
