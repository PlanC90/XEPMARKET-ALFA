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
                                <?php echo esc_html($h_logo_text1); ?><span class="logo-accent">
                                    <?php echo esc_html($h_logo_text2); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </a>
                </div>

                <nav id="site-navigation" class="main-navigation">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'menu_id' => 'primary-menu',
                        'container' => false,
                        'fallback_cb' => 'xepmarket2_menu_fallback',
                    ));
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
        <?php if (class_exists('WooCommerce')): ?>
            <div class="mobile-app-nav">
                <a href="<?php echo esc_url(home_url('/')); ?>"
                    class="app-nav-item <?php echo is_front_page() ? 'active' : ''; ?>">
                    <i class="dashicons dashicons-admin-home"></i>
                    <span>Home</span>
                </a>
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
                    class="app-nav-item <?php echo is_post_type_archive('product') || is_tax('product_cat') ? 'active' : ''; ?>">
                    <i class="dashicons dashicons-store"></i>
                    <span>Shop</span>
                </a>
                <a href="<?php echo esc_url(wc_get_cart_url()); ?>"
                    class="app-nav-item <?php echo is_cart() ? 'active' : ''; ?>">
                    <div class="app-nav-cart-icon">
                        <i class="dashicons dashicons-cart"></i>
                        <span class="app-cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
                    </div>
                    <span>Cart</span>
                </a>
                <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
                    class="app-nav-item <?php echo is_account_page() ? 'active' : ''; ?>">
                    <i class="dashicons dashicons-admin-users"></i>
                    <span>Account</span>
                </a>
            </div>
        <?php endif; ?>

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
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'container' => false,
                    'menu_class' => 'mobile-menu-list',
                    'fallback_cb' => false,
                ));
                ?>
            </div>
        </div>
        <div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>

        <div id="content" class="site-content">