<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php
    // Favicon Logic: Check for WordPress native Site Icon first, then theme-specific setting
    if (!function_exists('has_site_icon') || !has_site_icon()) {
        $favicon = xepmarket2_get_option_fast('xepmarket2_favicon');
        if ($favicon) {
            echo '<link rel="icon" href="' . esc_url($favicon) . '" />';
            echo '<link rel="apple-touch-icon" href="' . esc_url($favicon) . '" />';
        }
    }
    wp_head();
    ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>

    <div id="page" class="site">
        <header id="masthead" class="site-header">
            <!-- Announcement Banner -->
            <?php
            $banner_visibility = xepmarket2_get_option_fast('xepmarket2_banner_visibility', 'both');
            $is_closed = isset($_COOKIE['omnixep_banner_closed']) && $_COOKIE['omnixep_banner_closed'] === 'true';

            if ($banner_visibility !== 'hidden' && !$is_closed):
                $banner_class = 'top-announcement-banner';
                if ($banner_visibility === 'web')
                    $banner_class .= ' desktop-only';
                if ($banner_visibility === 'mobile')
                    $banner_class .= ' mobile-only';
                ?>
                <div class="<?php echo esc_attr($banner_class); ?>"
                    style="background-color: <?php echo esc_attr(xepmarket2_get_option_fast('xepmarket2_banner_bg', '#00f2ff')); ?>; color: <?php echo esc_attr(xepmarket2_get_option_fast('xepmarket2_banner_text_color', '#000000')); ?>;">
                    <div class="container"
                        style="display: flex; align-items: center; justify-content: center; padding: 6px 0; gap: 20px;">
                        <div class="banner-content-wrapper" style="display: flex; align-items: center; gap: 15px;">
                            <span class="banner-badge"
                                style="background: rgba(0,0,0,0.1); color: inherit; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 11px;">NEW</span>
                            <span class="banner-text"
                                style="font-size: 13px;"><?php echo xepmarket2_get_option_fast('xepmarket2_banner_main', 'Welcome to the future of retail!'); ?></span>
                            <span class="banner-separator" style="opacity: 0.3;">|</span>
                            <span class="banner-promo"
                                style="font-weight: 800; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;"><?php echo xepmarket2_get_option_fast('xepmarket2_banner_discount', '🚀 Up to 50% OFF! 🚀'); ?></span>
                        </div>
                        <div class="banner-close"><i class="dashicons dashicons-no-alt"></i></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="header-container">
                <div class="site-branding">
                    <?php
                    $h_logo_type = xepmarket2_get_option_fast('xepmarket2_header_logo_type', 'text');
                    $h_logo_img = xepmarket2_get_option_fast('xepmarket2_header_logo_img');
                    $h_logo_text1 = xepmarket2_get_option_fast('xepmarket2_header_logo_text_1', 'XEP');
                    $h_logo_text2 = xepmarket2_get_option_fast('xepmarket2_header_logo_text_2', 'MARKET');
                    ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="custom-logo-link" rel="home">
                        <span class="logo-text">
                            <?php if ($h_logo_type === 'image' && $h_logo_img): ?>
                                <img src="<?php echo esc_url($h_logo_img); ?>" alt="<?php bloginfo('name'); ?>"
                                    style="max-height: 40px; width: auto; display: block;">
                            <?php else: ?>
                                <?php if ($h_logo_type === 'text_image' && $h_logo_img): ?>
                                    <img class="logo-icon" src="<?php echo esc_url($h_logo_img); ?>" alt="<?php bloginfo('name'); ?>">
                                <?php endif; ?>
                                <?php echo esc_html($h_logo_text1); ?><span class="logo-accent">
                                    <?php echo esc_html($h_logo_text2); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </a>
                </div>

                <nav id="site-navigation" class="main-navigation">
                    <?php
                    $web_menu_id = xepmarket2_get_option_fast('xepmarket2_menu_web', '');
                    $nav_args = array(
                        'menu_id' => 'primary-menu',
                        'container' => false,
                        'fallback_cb' => 'xepmarket2_menu_fallback',
                    );
                    if (!empty($web_menu_id) && is_nav_menu($web_menu_id)) {
                        $nav_args['menu'] = (int) $web_menu_id;
                    } else {
                        $nav_args['theme_location'] = 'primary';
                    }
                    wp_nav_menu($nav_args);
                    ?>
                </nav>

                <div class="header-actions">
                    <!-- Search Button (visible on both mobile & desktop) -->
                    <div class="header-search-wrapper">
                        <button id="header-search-open" class="search-btn-modern" aria-label="Open Search"
                            style="width: 45px !important; height: 45px !important; background: rgba(255,255,255,0.05) !important; border: 1px solid rgba(255,255,255,0.1) !important; border-radius: 12px !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; cursor: pointer !important; padding: 0 !important; color: #fff !important; outline: none !important;">
                            <i class="fa-solid fa-magnifying-glass"
                                style="font-size: 16px !important; pointer-events: none;"></i>
                        </button>
                    </div>

                    <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-controls="primary-menu"
                        aria-expanded="false" style="display: none;">
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                    </button>


                    <div class="header-cart desktop-only">
                        <?php if (class_exists('WooCommerce')): ?>
                            <a class="cart-contents" href="<?php echo esc_url(wc_get_cart_url()); ?>"
                                style="text-decoration: none !important; display: block !important;"
                                title="<?php esc_attr_e('View cart', 'xepmarket2'); ?>">
                                <div class="cart-icon-wrapper"
                                    style="position: relative !important; width: 45px !important; height: 45px !important; background: rgba(255,255,255,0.05) !important; border: 1px solid rgba(255,255,255,0.1) !important; border-radius: 12px !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: all 0.3s ease !important;">
                                    <i class="fa-solid fa-cart-shopping"
                                        style="font-size: 16px !important; color: #fff !important;"></i>
                                    <span class="cart-count"
                                        style="position: absolute !important; top: -5px !important; right: -5px !important; background: linear-gradient(135deg, #ff8a00, #ff5000) !important; color: #fff !important; font-size: 10px !important; font-weight: 800 !important; min-width: 18px !important; height: 18px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; border: 2px solid #05060a !important; padding: 0 4px !important;"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="header-account desktop-only">
                        <?php if (class_exists('WooCommerce')): ?>
                            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
                                style="text-decoration: none !important; display: block !important;"
                                title="<?php esc_attr_e('My Account', 'xepmarket2'); ?>">
                                <div class="account-icon-wrapper"
                                    style="width: 45px !important; height: 45px !important; background: rgba(255,255,255,0.05) !important; border: 1px solid rgba(255,255,255,0.1) !important; border-radius: 12px !important; display: flex !important; align-items: center !important; justify-content: center !important; transition: all 0.3s ease !important;">
                                    <i class="fa-solid fa-user"
                                        style="font-size: 16px !important; color: #fff !important;"></i>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile App-Like Bottom Navigation (ONLY visible on mobile via CSS) -->
        <?php if (class_exists('WooCommerce')):
            $show_home   = (string) get_option('xepmarket2_mobile_nav_show_home', '1') === '1';
            $show_shop   = (string) get_option('xepmarket2_mobile_nav_show_shop', '1') === '1';
            $show_cart   = (string) get_option('xepmarket2_mobile_nav_show_cart', '1') === '1';
            $show_account = (string) get_option('xepmarket2_mobile_nav_show_account', '1') === '1';
            $icon_home   = sanitize_text_field(get_option('xepmarket2_mobile_nav_icon_home', ''));
            $icon_shop   = sanitize_text_field(get_option('xepmarket2_mobile_nav_icon_shop', ''));
            $icon_cart   = sanitize_text_field(get_option('xepmarket2_mobile_nav_icon_cart', ''));
            $icon_account = sanitize_text_field(get_option('xepmarket2_mobile_nav_icon_account', ''));
            if (empty($icon_home))   $icon_home   = 'dashicons dashicons-admin-home';
            if (empty($icon_shop))   $icon_shop   = 'dashicons dashicons-store';
            if (empty($icon_cart))   $icon_cart   = 'dashicons dashicons-cart';
            if (empty($icon_account)) $icon_account = 'dashicons dashicons-admin-users';
            $custom_nav = get_option('xepmarket2_mobile_nav_custom_items', array());
            $custom_nav = is_array($custom_nav) ? array_slice($custom_nav, 0, 5) : array();
            $nav_order = get_option('xepmarket2_mobile_nav_order', array('home', 'shop', 'cart', 'account', 'custom_0'));
            if (!is_array($nav_order) || count($nav_order) < 5) $nav_order = array('home', 'shop', 'cart', 'account', 'custom_0');
            $has_any_custom = false;
            foreach ($custom_nav as $c) {
                $s = isset($c['show']) ? (bool) $c['show'] : false;
                $u = isset($c['url']) ? trim($c['url']) : '';
                if ($s && $u !== '') { $has_any_custom = true; break; }
            }
            if ($show_home || $show_shop || $show_cart || $show_account || $has_any_custom):
            ?>
            <div class="mobile-app-nav">
                <?php
                $nav_items = array();
                $item_map = array(
                    'home'    => $show_home ? array('url' => home_url('/'), 'label' => 'Home', 'icon' => $icon_home, 'active' => is_front_page(), 'cart' => false) : null,
                    'shop'    => $show_shop ? array('url' => wc_get_page_permalink('shop'), 'label' => 'Shop', 'icon' => $icon_shop, 'active' => is_post_type_archive('product') || is_tax('product_cat'), 'cart' => false) : null,
                    'cart'    => $show_cart ? array('url' => wc_get_cart_url(), 'label' => 'Cart', 'icon' => $icon_cart, 'active' => is_cart(), 'cart' => true) : null,
                    'account' => $show_account ? array('url' => wc_get_page_permalink('myaccount'), 'label' => 'Account', 'icon' => $icon_account, 'active' => is_account_page(), 'cart' => false) : null,
                );
                for ($i = 0; $i < 5; $i++) {
                    $id = isset($nav_order[$i]) ? $nav_order[$i] : '';
                    if ($id === 'home' || $id === 'shop' || $id === 'cart' || $id === 'account') {
                        if (!empty($item_map[$id])) $nav_items[] = $item_map[$id];
                    } elseif (preg_match('/^custom_(\d+)$/', $id, $m)) {
                        $ci = (int) $m[1];
                        if (isset($custom_nav[$ci])) {
                            $c = $custom_nav[$ci];
                            $s = isset($c['show']) ? (bool) $c['show'] : false;
                            $u = isset($c['url']) ? trim($c['url']) : '';
                            if ($s && $u !== '') {
                                $nav_items[] = array('url' => $u, 'label' => !empty($c['label']) ? $c['label'] : __('Link', 'xepmarket2'), 'icon' => !empty($c['icon']) ? $c['icon'] : 'dashicons dashicons-admin-links', 'active' => false, 'cart' => false);
                            }
                        }
                    }
                }
                $nav_items = array_slice($nav_items, 0, 5);
                foreach ($nav_items as $item):
                    if ($item['cart']):
                ?>
                <a href="<?php echo esc_url($item['url']); ?>" class="app-nav-item <?php echo $item['active'] ? 'active' : ''; ?>">
                    <div class="app-nav-cart-icon">
                        <i class="<?php echo esc_attr($item['icon']); ?>"></i>
                        <span class="app-cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
                    </div>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
                <?php else: ?>
                <a href="<?php echo esc_url($item['url']); ?>" class="app-nav-item <?php echo $item['active'] ? 'active' : ''; ?>">
                    <i class="<?php echo esc_attr($item['icon']); ?>"></i>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
                <?php endif; endforeach; ?>
            </div>
            <?php
            endif;
        endif; ?>

        <!-- Mobile Navigation Drawer (hidden by default) -->
        <div class="mobile-navigation">
            <div class="mobile-menu-header">
                <div class="mobile-logo">
                    <span class="logo-text" style="font-size: 20px;">
                        <?php if ($h_logo_type === 'image' && $h_logo_img): ?>
                            <img src="<?php echo esc_url($h_logo_img); ?>" alt="<?php bloginfo('name'); ?>"
                                style="max-height: 30px; width: auto; display: block;">
                        <?php else: ?>
                            <?php echo esc_html($h_logo_text1); ?><span
                                class="logo-accent"><?php echo esc_html($h_logo_text2); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="mobile-menu-close" id="mobile-menu-close">&times;</div>
            </div>
            <div class="mobile-menu-content">

                <?php
                $mobile_menu_id = xepmarket2_get_option_fast('xepmarket2_menu_mobile', '');
                $mobile_nav_args = array(
                    'container' => false,
                    'menu_class' => 'mobile-menu-list',
                    'fallback_cb' => false,
                );
                if (!empty($mobile_menu_id) && is_nav_menu($mobile_menu_id)) {
                    $mobile_nav_args['menu'] = (int) $mobile_menu_id;
                } else {
                    $mobile_nav_args['theme_location'] = 'primary';
                }
                wp_nav_menu($mobile_nav_args);
                ?>
            </div>
        </div>
        <div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>

        <div id="content" class="site-content">