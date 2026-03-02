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
            'content' => '<!-- wp:html --><div class="xep-swap-container" style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 24px; border: 1px solid var(--border-glass);"><iframe src="https://wap.electraproject.org/swap" width="100%" height="800" style="border:none; border-radius: 15px; background: transparent;" title="XEP Swap Interface"></iframe></div><!-- /wp:html -->',
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
            // Update content if page exists but content is empty
            if (empty($exists->post_content) && !empty($data['content'])) {
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
        // ── Site Colors ──
        'xepmarket2_color_primary' => '#00f2ff',
        'xepmarket2_color_secondary' => '#7000ff',
        'xepmarket2_color_bg' => '#05060a',
        'xepmarket2_color_text' => '#ffffff',
        'xepmarket2_color_text_muted' => '#a0a0b8',

        // ── Header Logo ──
        'xepmarket2_header_logo_type' => 'text',
        'xepmarket2_header_logo_text_1' => 'XEP',
        'xepmarket2_header_logo_text_2' => 'MARKET',

        // ── Footer Logo ──
        'xepmarket2_footer_logo_type' => 'text',
        'xepmarket2_footer_logo_text_1' => 'XEP',
        'xepmarket2_footer_logo_text_2' => 'MARKET',

        // ── Slider Enable ──
        'xepmarket2_slider_enable' => '1',

        // ── Slide 1 ──
        'xepmarket2_slider_title_1' => 'Free <span class="logo-accent">Worldwide</span> Shipping',
        'xepmarket2_slider_desc_1' => 'We deliver the future of tech to your doorstep, anywhere in the world. Zero shipping costs, 100% secure.',
        'xepmarket2_slider_btn_text_1' => 'Learn More',
        'xepmarket2_slider_btn_link_1' => '/shop',
        'xepmarket2_slider_img_1' => $template_uri . '/assets/images/slide2.png?v=1.5',

        // ── Slide 2 ──
        'xepmarket2_slider_title_2' => 'Innovative <span class="logo-accent">Tech Products</span>',
        'xepmarket2_slider_desc_2' => 'Explore the latest in cutting-edge technology. From smart devices to premium gadgets, we bring you the future.',
        'xepmarket2_slider_btn_text_2' => 'Discover More',
        'xepmarket2_slider_btn_link_2' => '/shop',
        'xepmarket2_slider_img_2' => $template_uri . '/assets/images/slide_tech.png?v=1.6',

        // ── Slide 3 ──
        'xepmarket2_slider_title_3' => 'Flash Sale: <span class="logo-accent">80% OFF</span>',
        'xepmarket2_slider_desc_3' => 'Limited time offer on select premium items. Enhance your lifestyle with massive discounts.',
        'xepmarket2_slider_btn_text_3' => 'Claim Discount',
        'xepmarket2_slider_btn_link_3' => '/shop',
        'xepmarket2_slider_img_3' => $template_uri . '/assets/images/slide3.png?v=1.5',

        // ── Static Hero (when slider disabled) ──
        'xepmarket2_hero_title' => 'Elevate Your <br><span class="logo-accent">Crypto Lifestyle</span>',
        'xepmarket2_hero_subtitle' => 'Premium hardware wallets, exclusive crypto apparel, and unique digital collectibles. Secure your assets and show your passion. Pay exclusively with XEP for secure and instant delivery.',
        'xepmarket2_hero_btn1_text' => 'Shop Crypto Gear',
        'xepmarket2_hero_btn1_link' => '/shop',
        'xepmarket2_hero_btn2_text' => 'Join Community',
        'xepmarket2_hero_btn2_link' => 'https://t.me/xepmarket_official',

        // ── Highlights (4 items) ──
        'xepmarket2_highlight_icon_1' => 'dashicons-location-alt',
        'xepmarket2_highlight_title_1' => 'FREE SHIPPING',
        'xepmarket2_highlight_desc_1' => 'On all orders across the store',
        'xepmarket2_highlight_icon_2' => 'dashicons-admin-site-alt3',
        'xepmarket2_highlight_title_2' => 'WORLDWIDE DELIVERY',
        'xepmarket2_highlight_desc_2' => 'Shipping to every corner of the world',
        'xepmarket2_highlight_icon_3' => 'dashicons-thumbs-up',
        'xepmarket2_highlight_title_3' => '100% SATISFACTION',
        'xepmarket2_highlight_desc_3' => 'Guaranteed customer happiness',
        'xepmarket2_highlight_icon_4' => 'dashicons-tag',
        'xepmarket2_highlight_title_4' => 'UP TO 80% OFF',
        'xepmarket2_highlight_desc_4' => 'Massive sale on innovative tech',

        // ── Featured Products ──
        'xepmarket2_featured_title' => 'Trending Gear',
        'xepmarket2_featured_subtitle' => 'Must-have items for every Web3 enthusiast',
        'xepmarket2_featured_limit' => '20',
        'xepmarket2_featured_columns' => '4',

        // ── Support Section ──
        'xepmarket2_support_title' => 'Web3 Native <span class="logo-accent">Support</span>',
        'xepmarket2_support_desc' => 'Need help with your order or crypto payment? Our decentralized support team is ready to help you navigate the future of commerce.',
        'xepmarket2_support_email' => 'crypto@xepmarket.com',
        'xepmarket2_support_telegram' => '@xepmarket',

        // ── Modules ──
        'xepmarket2_mod_omnixep_restrict' => '1',
        'xepmarket2_mod_custom_checkout' => '1',
        'xepmarket2_mod_sale_badges' => '1',
        'xepmarket2_mod_breadcrumb_modern' => '1',

        // ── Banner (Announcement) ──
        'xepmarket2_banner_main' => 'Welcome to the future of retail! Secure your Crypto Gear today. Pay exclusively with XEP.',
        'xepmarket2_banner_discount' => '🚀 Limited Time Offer: Up to 50% OFF! 🚀',
        'xepmarket2_banner_bg' => '#00f2ff',
        'xepmarket2_banner_text_color' => '#000000',
        'xepmarket2_banner_visibility' => 'both',

        // ── Footer Description ──
        'xepmarket2_footer_desc' => 'Your premium destination for Web3 lifestyle gear. From hardware security to exclusive crypto apparel, we bring the blockchain to your doorstep.',

        // ── Payment Tokens ──
        'xepmarket2_token_name_1' => 'XEP',
        'xepmarket2_token_status_1' => 'live',
        'xepmarket2_token_name_2' => 'MMX',
        'xepmarket2_token_status_2' => 'soon',

        // ── Social Media ──
        'xepmarket2_social_telegram' => 'https://t.me/xepmarket_official',

        // ── SEO Defaults ──
        'xepmarket2_seo_title_suffix' => ' | XepMarket Premium Web3 Gear',
        'xepmarket2_seo_description' => 'XepMarket is your premium destination for Web3 gear, crypto apparel, and hardware security. Official store for the Electra Protocol (XEP) ecosystem.',
        'xepmarket2_seo_keywords' => 'XEP, Electra Protocol, Web3 Gear, Crypto Apparel, Hardware Wallet, XepMarket, Blockchain Lifestyle',
        'xepmarket2_seo_ai_business_name' => 'XepMarket',
        'xepmarket2_seo_ai_crawler_allow' => 'allow',
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
    $site_name = 'XepMarket';
    return '<!-- wp:heading -->
<h2>Who We Are</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . $site_name . ' is an e-commerce platform operated under the Electra Protocol ecosystem. Our website address is: ' . home_url() . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>What Personal Data We Collect and Why</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>Orders and Payments</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>When you place an order, we collect your name, email address, and shipping address to fulfill your purchase. Payment is processed exclusively through the OmniXEP cryptocurrency payment gateway. We store the transaction ID (TXID) on the blockchain for verification purposes. We do NOT store any private keys or wallet passwords.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Cookies</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We use essential cookies to maintain your shopping cart and session. If you log in, we set temporary cookies to verify your browser accepts cookies. These expire when you close your browser. Login cookies last for two days, and screen options cookies last for a year.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Analytics</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We may use Google Analytics to understand how visitors interact with our website. This data is anonymized and used solely to improve user experience.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Who We Share Your Data With</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We do not sell or rent your personal data to third parties. Shipping information is shared with our logistics partners solely for order delivery. Blockchain transactions are publicly verifiable by nature of cryptocurrency technology.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>How Long We Retain Your Data</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Order data is retained for tax and accounting purposes as required by law. If you have an account, your personal profile information is retained until you request deletion. You may request data export or deletion at any time by contacting us.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Your Rights</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You have the right to access, correct, or delete your personal data. You may also request a portable file of the personal data we hold about you. To exercise these rights, please contact us at the email address on our website.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Contact Information</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>For any privacy-related inquiries, please reach out through our Telegram channel or the support section on our website.</p>
<!-- /wp:paragraph -->';
}
