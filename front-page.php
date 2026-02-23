<?php
/**
 * Front page template optimized for Crypto Merchandise & Hardware Store
 */
get_header();
?>

<?php if (xepmarket2_get_option_fast('xepmarket2_slider_enable') == '1'): ?>
    <!-- Hero Slider -->
    <div class="hero-slider-wrapper">
        <div class="main-slider">
            <?php for ($i = 1; $i <= 3; $i++):
                $title = xepmarket2_get_option_fast('xepmarket2_slider_title_' . $i);
                if (empty($title))
                    continue;
                $desc = xepmarket2_get_option_fast('xepmarket2_slider_desc_' . $i);
                $btn_text = xepmarket2_get_option_fast('xepmarket2_slider_btn_text_' . $i);
                $btn_link = xepmarket2_get_option_fast('xepmarket2_slider_btn_link_' . $i);
                $img = xepmarket2_get_option_fast('xepmarket2_slider_img_' . $i);
                ?>
                <div class="slide-item <?php echo $i === 1 ? 'active' : ''; ?>"
                    style="<?php echo $img ? "background: url('" . esc_url($img) . "') no-repeat center center / cover !important;" : ''; ?>">
                    <div class="hero-glow"></div>
                    <div class="container" style="position: relative; z-index: 5;">
                        <div class="slide-content">
                            <h1 class="animate-in"><?php echo wp_kses_post($title); ?></h1>
                            <p class="animate-in"><?php echo wp_kses_post($desc); ?></p>
                            <div class="hero-actions animate-in">
                                <?php if ($btn_text): ?>
                                    <a href="<?php echo esc_url($btn_link); ?>"
                                        class="button-premium"><?php echo esc_html($btn_text); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <div class="slider-controls">
            <button class="prev-slide"><i class="dashicons dashicons-arrow-left-alt2"></i></button>
            <button class="next-slide"><i class="dashicons dashicons-arrow-right-alt2"></i></button>
        </div>
        <div class="slider-dots"></div>
    </div>
<?php else: ?>
    <!-- Static Hero Section -->
    <div class="hero-section">
        <div class="hero-glow"></div>
        <div class="container">
            <h1 class="animate-in">
                <?php echo xepmarket2_get_option_fast('xepmarket2_hero_title', 'Elevate Your <br><span class="logo-accent">Crypto Lifestyle</span>'); ?>
            </h1>
            <p class="animate-in">
                <?php echo xepmarket2_get_option_fast('xepmarket2_hero_subtitle', 'Premium hardware wallets, exclusive crypto apparel, and unique digital collectibles. Secure your assets and show your passion. Pay exclusively with <strong class="xep-text">XEP</strong> for secure and instant delivery.'); ?>
            </p>
            <div class="hero-actions animate-in">
                <a href="<?php echo esc_url(xepmarket2_get_option_fast('xepmarket2_hero_btn1_link', function_exists('wc_get_page_id') ? get_permalink(wc_get_page_id('shop')) : '#')); ?>"
                    class="button-premium">
                    <?php echo esc_html(xepmarket2_get_option_fast('xepmarket2_hero_btn1_text', 'Shop Crypto Gear')); ?>
                </a>
                <a href="<?php echo esc_url(xepmarket2_get_option_fast('xepmarket2_hero_btn2_link', 'https://t.me/xepmarket_official')); ?>"
                    target="_blank" class="button-outline">
                    <i class="dashicons dashicons-telegram"></i>
                    <?php echo esc_html(xepmarket2_get_option_fast('xepmarket2_hero_btn2_text', 'Join Community')); ?>
                </a>
            </div>

            <div class="trust-badges animate-in"
                style="margin-top: 60px; display: flex; justify-content: center; gap: 40px;">
                <div class="trust-item">
                    <i class="dashicons dashicons-admin-network"></i>
                    <span>Secure Payments</span>
                </div>
                <div class="trust-item">
                    <i class="dashicons dashicons-products"></i>
                    <span>Premium Quality</span>
                </div>
                <div class="trust-item">
                    <i class="dashicons dashicons-location-alt"></i>
                    <span>Free Global Shipping</span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<main id="primary" class="container site-main">

    <!-- Highlights Grid -->
    <section class="software-categories animate-in">
        <?php
        $default_icons = ['dashicons-location-alt', 'dashicons-admin-site-alt3', 'dashicons-thumbs-up', 'dashicons-tag'];
        $default_titles = ['FREE SHIPPING', 'WORLDWIDE DELIVERY', '100% SATISFACTION', 'UP TO 80% OFF'];
        $default_descs = ['On all orders across the store', 'Shipping to every corner of the world', 'Guaranteed customer happiness', 'Massive sale on innovative tech'];

        for ($i = 1; $i <= 4; $i++):
            $icon = xepmarket2_get_option_fast('xepmarket2_highlight_icon_' . $i, $default_icons[$i - 1]);
            $title = xepmarket2_get_option_fast('xepmarket2_highlight_title_' . $i, $default_titles[$i - 1]);
            $desc = xepmarket2_get_option_fast('xepmarket2_highlight_desc_' . $i, $default_descs[$i - 1]);

            if (empty($title))
                continue;
            ?>
            <div class="category-card glass-card <?php echo $i === 4 ? 'discount-highlight-card' : ''; ?>">
                <i class="dashicons <?php echo esc_attr($icon); ?>"></i>
                <h3><?php echo esc_html($title); ?></h3>
                <p><?php echo esc_html($desc); ?></p>
            </div>
        <?php endfor; ?>
    </section>

    <!-- Sale Products Slider -->
    <?php if (xepmarket2_get_option_fast('xepmarket2_flash_deals_enable', '1') == '1'): ?>
        <section class="sale-products-slider animate-in" style="margin-bottom: 80px;">
            <div class="section-title"
                style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; text-align: left;">
                <div style="flex: 1;">
                    <h2 style="margin-bottom: 5px;">
                        <?php echo wp_kses_post(xepmarket2_get_option_fast('xepmarket2_flash_deals_title', 'Flash <span class="logo-accent">Deals</span>')); ?>
                    </h2>
                    <p style="margin: 0; max-width: none;">
                        <?php echo esc_html(xepmarket2_get_option_fast('xepmarket2_flash_deals_subtitle', 'Exclusive discounts on premium gear.')); ?>
                    </p>
                </div>
                <div class="sale-slider-nav" style="display: flex; gap: 10px;">
                    <button class="sale-prev"
                        style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center;"><i
                            class="dashicons dashicons-arrow-left-alt2"></i></button>
                    <button class="sale-next"
                        style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center;"><i
                            class="dashicons dashicons-arrow-right-alt2"></i></button>
                </div>
            </div>

            <div class="sale-carousel-wrapper">
                <?php
                // Sadece indirimli ürünler, ayarlanan özellikler ile
                $sale_limit = xepmarket2_get_option_fast('xepmarket2_flash_deals_limit', '12');
                $sale_columns = xepmarket2_get_option_fast('xepmarket2_flash_deals_columns', '4');
                echo do_shortcode('[products on_sale="true" limit="' . esc_attr($sale_limit) . '" columns="' . esc_attr($sale_columns) . '"]');
                ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="featured-products">
        <div class="section-title animate-in">
            <h2><?php echo wp_kses_post(xepmarket2_get_option_fast('xepmarket2_featured_title', 'Trending Gear')); ?>
            </h2>
            <p><?php echo esc_html(xepmarket2_get_option_fast('xepmarket2_featured_subtitle', 'Must-have items for every Web3 enthusiast')); ?>
            </p>
        </div>

        <?php
        $limit = xepmarket2_get_option_fast('xepmarket2_featured_limit', '20');
        $columns = xepmarket2_get_option_fast('xepmarket2_featured_columns', '4');
        echo do_shortcode('[products limit="' . esc_attr($limit) . '" columns="' . esc_attr($columns) . '"]');
        ?>
    </section>

    <!-- Modern Support Section -->
    <section class="support-highlights-modern animate-in">
        <div class="support-glass-card">
            <div class="support-header">
                <div class="live-indicator">
                    <span class="pulse-dot"></span>
                    LIVE 24/7
                </div>
                <h2><?php echo wp_kses_post(xepmarket2_get_option_fast('xepmarket2_support_title', 'Web3 Native <span class="logo-accent">Support</span>')); ?>
                </h2>
                <p><?php echo esc_html(xepmarket2_get_option_fast('xepmarket2_support_desc', 'Need help with your order or crypto payment? Our decentralized support team is ready to help you navigate the future of commerce.')); ?>
                </p>
            </div>

            <div class="support-grid">
                <?php
                $email = xepmarket2_get_option_fast('xepmarket2_support_email', 'crypto@xepmarket.com');
                if ($email): ?>
                    <a href="mailto:<?php echo esc_attr($email); ?>" class="support-box">
                        <div class="support-icon-circle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                            </svg>
                        </div>
                        <div class="support-text">
                            <span>Email Support</span>
                            <strong><?php echo esc_html($email); ?></strong>
                        </div>
                    </a>
                <?php endif; ?>

                <?php
                $telegram = xepmarket2_get_option_fast('xepmarket2_support_telegram', '@xepmarket');
                if ($telegram):
                    $tg_link = str_starts_with($telegram, '@') ? 'https://t.me/' . substr($telegram, 1) : $telegram;
                    ?>
                    <a href="<?php echo esc_url($tg_link); ?>" target="_blank" class="support-box">
                        <div class="support-icon-circle telegram">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.51-.46-.01-1.33-.26-1.98-.47-.8-.26-1.42-.4-1.36-.85.03-.23.35-.47.95-.71 3.71-1.61 6.19-2.67 7.42-3.19 3.53-1.48 4.26-1.74 4.74-1.75.11 0 .34.03.5.16.12.11.16.27.18.37.02.13.03.35.02.48z" />
                            </svg>
                        </div>
                        <div class="support-text">
                            <span>Support Telegram</span>
                            <strong><?php echo esc_html($telegram); ?></strong>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <div class="support-glow-overlay"></div>
        </div>
    </section>

</main>

<?php
get_footer();
