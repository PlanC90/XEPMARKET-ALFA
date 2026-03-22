<?php
get_header();
?>

<main id="primary" class="site-main">
    <?php if (is_shop() || is_product_category() || is_product_tag()): ?>
        <section class="shop-hero-section">
            <div class="hero-glow"></div>
            <div class="container">
                <div class="shop-header-content">
                    <?php
                    /**
                     * Hook: woocommerce_before_main_content.
                     *
                     * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
                     * @hooked woocommerce_breadcrumb - 20
                     * @hooked WC_Structured_Data::generate_website_data() - 30
                     */
                    do_action('woocommerce_before_main_content');
                    ?>

                    <h1 class="shop-main-title">
                        <?php woocommerce_page_title(); ?>
                    </h1>

                    <?php
                    /**
                     * Hook: woocommerce_archive_description.
                     */
                    do_action('woocommerce_archive_description');
                    ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="container shop-listing-area">
        <div class="woocommerce-page-wrapper">
            <?php woocommerce_content(); ?>
        </div>
    </div>
</main>

<?php
get_footer();
